<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Jika tidak ada user_id di URL, cek apakah hanya ada 1 guru yang login
if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

// Validasi apakah user_id valid dan ada di session
if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: ../auth/login_user.php?role=guru");
    exit();
}

// Ambil data user dari session
$user_data = $_SESSION['logged_users']['guru'][$current_user_id];
$user_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];
$email = $user_data['email'];
$mata_pelajaran = $user_data['mata_pelajaran'];

include '../../db.php';
include '../auth_user/auth_check_guru.php'; 

// Ambil filter dengan validasi
$jurusan_filter = isset($_GET['jurusan']) ? trim($_GET['jurusan']) : '';
$kelas_filter   = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$periode_filter = isset($_GET['periode']) ? trim($_GET['periode']) : 'harian';
$tanggal_filter = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$minggu_filter  = isset($_GET['minggu']) ? trim($_GET['minggu']) : '';
$bulan_filter   = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';

// Validasi periode filter
$valid_periode = ['harian', 'mingguan', 'bulanan'];
if (!in_array($periode_filter, $valid_periode)) {
    $periode_filter = 'harian';
}

// Cek apakah filter minimal sudah diisi
$all_filters_filled = !empty($jurusan_filter) && !empty($kelas_filter);

// Validasi filter sesuai periode
if ($all_filters_filled) {
    if ($periode_filter === 'harian' && empty($tanggal_filter)) {
        $all_filters_filled = false;
    } elseif ($periode_filter === 'mingguan' && empty($minggu_filter)) {
        $all_filters_filled = false;
    } elseif ($periode_filter === 'bulanan' && empty($bulan_filter)) {
        $all_filters_filled = false;
    }
}

$absensi = [];
$totalHadir = 0;
$totalAlpha = 0;
$totalSakit = 0;
$totalIzin  = 0;

if ($all_filters_filled) {
    // Gunakan prepared statement untuk keamanan
    $query = "SELECT a.tanggal, a.kelas, a.jurusan, a.mata_pelajaran, s.nis, s.nama, a.status 
              FROM absensi a
              JOIN siswa s ON a.student_id = s.id
              WHERE a.jurusan = ? AND a.kelas = ? AND a.mata_pelajaran = ?";
    
    $params = [$jurusan_filter, $kelas_filter, $mata_pelajaran];
    $types = "sss";
    
    // Filter berdasarkan periode
    if ($periode_filter === 'harian' && !empty($tanggal_filter)) {
        $query .= " AND a.tanggal = ?";
        $params[] = $tanggal_filter;
        $types .= "s";
    } elseif ($periode_filter === 'mingguan' && !empty($minggu_filter)) {
        // Format input: 2025-W41 (tahun-minggu)
        if (preg_match('/^(\d{4})-W(\d{2})$/', $minggu_filter, $matches)) {
            $year = (int)$matches[1];
            $week = (int)$matches[2];
    
            // Hitung tanggal awal dan akhir minggu ISO
            $startOfWeek = date('Y-m-d', strtotime($year . "W" . str_pad($week, 2, '0', STR_PAD_LEFT)));
            $endOfWeek = date('Y-m-d', strtotime($startOfWeek . ' +6 days'));
    
            $query .= " AND a.tanggal BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            $types .= "ss";
        }
    
    } elseif ($periode_filter === 'bulanan' && !empty($bulan_filter)) {
        // Format: 2025-01 (tahun-bulan)
        if (preg_match('/^\d{4}-\d{2}$/', $bulan_filter)) {
            $query .= " AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
            $params[] = $bulan_filter;
            $types .= "s";
        }
    }

    $query .= " ORDER BY a.tanggal DESC, a.kelas ASC, s.nis ASC";

    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        // Bind parameters dynamically
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $absensi[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);

        // Hitung total status
        foreach ($absensi as $row) {
            $status = strtolower($row['status']);
            switch ($status) {
                case 'hadir': $totalHadir++; break;
                case 'alpha': $totalAlpha++; break;
                case 'sakit': $totalSakit++; break;
                case 'izin':  $totalIzin++; break;
            }
        }
    }
}

// Format tanggal untuk tampilan
function formatTanggal($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $timestamp = strtotime($tanggal);
    $day = date('d', $timestamp);
    $month = $bulan[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    return "$day $month $year";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin: 0;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 1rem;
        }

        .header .guru-info {
            margin-top: 10px;
            font-size: 0.9rem;
            opacity: 0.9;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: inline-block;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th:first-child, td:first-child {
            text-align: center;
            width: 60px;
            font-weight: bold;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-hadir {
            color: #27ae60;
            font-weight: bold;
        }

        .status-alpha {
            color: #e74c3c;
            font-weight: bold;
        }

        .status-sakit {
            color: #f39c12;
            font-weight: bold;
        }

        .status-izin {
            color: #3498db;
            font-weight: bold;
        }

        .data-info {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #0c5460;
        }

        .data-info i {
            margin-right: 8px;
        }

        .filter-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        .filter-form h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .required-indicator {
            color: #e74c3c;
            margin-left: 3px;
        }

        .periode-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        .periode-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border-radius: 5px 5px 0 0;
        }

        .periode-tab:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .periode-tab.active {
            background: #3498db;
            color: white;
        }

        .periode-input-group {
            display: none;
        }

        .periode-input-group.active {
            display: block;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-select, .form-control {
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .main-content {
                margin: 10px;
                padding: 15px;
            }
            
            .periode-tabs {
                flex-direction: column;
            }
            
            .table-container {
                padding: 10px;
            }
        }
    </style>
</head>
<body> 

<?php include 'sidebar_guru.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-clipboard-check"></i> Riwayat Absensi</h1>
            <p>Menampilkan data absensi siswa secara transparan dan terstruktur</p>
            <div class="guru-info">
                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($nama); ?> 
                | <i class=""></i> <?php echo htmlspecialchars($mata_pelajaran); ?>
                | <i class=""></i><?php echo htmlspecialchars($nip); ?>
            </div>
        </div>

        <div class="filter-form">
            <h5><i class="fas fa-filter"></i> Filter Data Absensi</h5>
            <p class="text-muted mb-3">Silakan isi semua filter di bawah untuk menampilkan data absensi</p>
            
            <form method="GET" class="row g-3" id="filterForm">
                <!-- PENTING: Hidden input untuk user_id -->
                <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">
                
                <div class="col-12">
                    <div class="periode-tabs">
                        <button type="button" class="periode-tab <?= $periode_filter === 'harian' ? 'active' : '' ?>" 
                                data-periode="harian">
                            <i class="fas fa-calendar-day"></i> Harian
                        </button>
                        <button type="button" class="periode-tab <?= $periode_filter === 'mingguan' ? 'active' : '' ?>" 
                                data-periode="mingguan">
                            <i class="fas fa-calendar-week"></i> Mingguan
                        </button>
                        <button type="button" class="periode-tab <?= $periode_filter === 'bulanan' ? 'active' : '' ?>" 
                                data-periode="bulanan">
                            <i class="fas fa-calendar-alt"></i> Bulanan
                        </button>
                    </div>
                    <input type="hidden" name="periode" id="periodeInput" value="<?= htmlspecialchars($periode_filter) ?>">
                </div>

                <div class="col-md-4">
                    <label for="jurusan" class="form-label">
                        Pilih Jurusan <span class="required-indicator">*</span>
                    </label>
                    <select name="jurusan" id="jurusan" class="form-select" required>
                        <option value="">-- Pilih Jurusan --</option>
                        <option value="IPA" <?= $jurusan_filter == 'IPA' ? 'selected' : '' ?>>IPA (Ilmu Pengetahuan Alam)</option>
                        <option value="IPS" <?= $jurusan_filter == 'IPS' ? 'selected' : '' ?>>IPS (Ilmu Pengetahuan Sosial)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="kelas" class="form-label">
                        Pilih Kelas <span class="required-indicator">*</span>
                    </label>
                    <select name="kelas" id="kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                    </select>
                </div>

                <div class="col-md-4 periode-input-group <?= $periode_filter === 'harian' ? 'active' : '' ?>" id="harianGroup">
                    <label for="tanggal" class="form-label">
                        Pilih Tanggal <span class="required-indicator">*</span>
                    </label>
                    <input type="date" id="tanggal" name="tanggal" class="form-control"
                           value="<?= htmlspecialchars($tanggal_filter) ?>">
                </div>

                <div class="col-md-4 periode-input-group <?= $periode_filter === 'mingguan' ? 'active' : '' ?>" id="mingguanGroup">
                    <label for="minggu" class="form-label">
                        Pilih Minggu <span class="required-indicator">*</span>
                    </label>
                    <input type="week" id="minggu" name="minggu" class="form-control"
                           value="<?= htmlspecialchars($minggu_filter) ?>">
                </div>

                <div class="col-md-4 periode-input-group <?= $periode_filter === 'bulanan' ? 'active' : '' ?>" id="bulananGroup">
                    <label for="bulan" class="form-label">
                        Pilih Bulan <span class="required-indicator">*</span>
                    </label>
                    <input type="month" id="bulan" name="bulan" class="form-control"
                           value="<?= htmlspecialchars($bulan_filter) ?>">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tampilkan Data
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fas fa-redo"></i> Reset Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if (!$all_filters_filled): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Lengkapi Filter</strong><br>
                Silahkan pilih Jurusan, Kelas, dan Periode waktu untuk menampilkan data riwayat absensi.
            </div>
        <?php else: ?>
            
            <div class="data-info">
                <i class="fas fa-info-circle"></i>
                Menampilkan <strong><?= count($absensi) ?></strong> data absensi
                dengan filter:
                <span class="badge bg-primary">Jurusan: <?= htmlspecialchars($jurusan_filter) ?></span>
                <span class="badge bg-success">Kelas: <?= htmlspecialchars($kelas_filter) ?></span>
                <span class="badge bg-warning">Mapel: <?= htmlspecialchars($mata_pelajaran) ?></span>
                <?php if ($periode_filter === 'harian' && !empty($tanggal_filter)): ?>
                    <span class="badge bg-info">Tanggal: <?= formatTanggal($tanggal_filter) ?></span>
                <?php elseif ($periode_filter === 'mingguan' && !empty($minggu_filter)): ?>
                    <span class="badge bg-info">Minggu: <?= htmlspecialchars($minggu_filter) ?></span>
                <?php elseif ($periode_filter === 'bulanan' && !empty($bulan_filter)): ?>
                    <?php
                    $timestamp = strtotime($bulan_filter . '-01');
                    $bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    $bulanNama = $bulanIndo[(int)date('n', $timestamp)];
                    $tahun = date('Y', $timestamp);
                    ?>
                    <span class="badge bg-info">Bulan: <?= "$bulanNama $tahun" ?></span>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Jurusan</th>
                            <th>Kelas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($absensi) > 0): ?>
                            <?php $nomor = 1; ?>
                            <?php foreach ($absensi as $row): ?>
                                <tr>
                                    <td><?= $nomor++ ?></td>
                                    <td><?= formatTanggal($row['tanggal']) ?></td>
                                    <td><?= htmlspecialchars($row['nis']) ?></td>
                                    <td><?= htmlspecialchars($row['nama']) ?></td>
                                    <td><?= htmlspecialchars($row['jurusan']) ?></td>
                                    <td><?= htmlspecialchars($row['kelas']) ?></td>
                                    <td class="status-<?= strtolower($row['status']) ?>">
                                        <?php 
                                        $statusLabels = [
                                            'hadir' => '<i class="fas fa-check-circle"></i> Hadir',
                                            'alpha' => '<i class="fas fa-times-circle"></i> Alpha', 
                                            'sakit' => '<i class="fas fa-heartbeat"></i> Sakit',
                                            'izin' => '<i class="fas fa-hand-paper"></i> Izin'
                                        ];
                                        echo $statusLabels[strtolower($row['status'])] ?? htmlspecialchars(ucfirst($row['status']));
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #7f8c8d; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    <strong>Tidak ada data absensi</strong><br>
                                    <small>Data absensi untuk mata pelajaran <strong><?= htmlspecialchars($mata_pelajaran) ?></strong> tidak ditemukan</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($absensi) > 0): ?>
                <div class="mt-4 p-3 bg-light rounded border">
                    <h6 class="mb-3"><i class="fas fa-chart-pie"></i> Rekapitulasi Kehadiran:</h6>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <div class="card text-center border-success">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><?= $totalHadir ?></h5>
                                    <p class="card-text mb-0">Hadir</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card text-center border-warning">
                                <div class="card-body">
                                    <h5 class="card-title text-warning"><?= $totalSakit ?></h5>
                                    <p class="card-text mb-0">Sakit</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card text-center border-info">
                                <div class="card-body">
                                    <h5 class="card-title text-info"><?= $totalIzin ?></h5>
                                    <p class="card-text mb-0">Izin</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card text-center border-danger">
                                <div class="card-body">
                                    <h5 class="card-title text-danger"><?= $totalAlpha ?></h5>
                                    <p class="card-text mb-0">Alpha</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        console.log('=== Riwayat Absensi Script Loaded ===');
        
        const kelasIPA = ['X IPA 1', 'X IPA 2', 'XI IPA 1', 'XI IPA 2', 'XII IPA 1', 'XII IPA 2'];
        const kelasIPS = ['X IPS 1', 'X IPS 2', 'XI IPS 1', 'XI IPS 2', 'XII IPS 1', 'XII IPS 2'];
        const currentUserId = <?php echo $current_user_id; ?>;

        function updateKelasDropdown() {
            const jurusan = document.getElementById('jurusan').value;
            const kelasSelect = document.getElementById('kelas');
            const urlParams = new URLSearchParams(window.location.search);
            const selectedValue = urlParams.get('kelas') || '';

            console.log('Update kelas dropdown untuk jurusan:', jurusan);
            kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';

            let kelasList = [];
            if (jurusan === 'IPA') kelasList = kelasIPA;
            else if (jurusan === 'IPS') kelasList = kelasIPS;

            kelasList.forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas;
                option.text = kelas;
                if (kelas === selectedValue) option.selected = true;
                kelasSelect.appendChild(option);
            });
            
            console.log('Kelas dropdown updated:', kelasList.length, 'options');
        }

        function changePeriode(periode, autoSubmit = false) {
            console.log('Changing periode to:', periode, 'autoSubmit:', autoSubmit);
            
            document.getElementById('periodeInput').value = periode;

            // Update active tab
            document.querySelectorAll('.periode-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-periode') === periode) {
                    tab.classList.add('active');
                }
            });

            // Hide all input groups and remove required
            document.querySelectorAll('.periode-input-group').forEach(group => {
                group.classList.remove('active');
            });
            
            document.getElementById('tanggal').removeAttribute('required');
            document.getElementById('minggu').removeAttribute('required');
            document.getElementById('bulan').removeAttribute('required');

            // Show relevant input group and add required
            if (periode === 'harian') {
                document.getElementById('harianGroup').classList.add('active');
                document.getElementById('tanggal').setAttribute('required', 'required');
            } else if (periode === 'mingguan') {
                document.getElementById('mingguanGroup').classList.add('active');
                document.getElementById('minggu').setAttribute('required', 'required');
            } else if (periode === 'bulanan') {
                document.getElementById('bulananGroup').classList.add('active');
                document.getElementById('bulan').setAttribute('required', 'required');
            }

            console.log('Periode UI updated');
        }

        function resetFilter() {
            console.log('Reset filter');
            window.location.href = window.location.pathname + '?user_id=' + currentUserId;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            console.log('Current User ID:', currentUserId);
            
            // Update kelas dropdown based on selected jurusan
            updateKelasDropdown();

            // Add change listener to jurusan
            document.getElementById('jurusan').addEventListener('change', function() {
                console.log('Jurusan changed');
                updateKelasDropdown();
            });

            // Add click listeners to periode tabs
            document.querySelectorAll('.periode-tab').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const periode = this.getAttribute('data-periode');
                    console.log('Tab clicked:', periode);
                    changePeriode(periode, false);
                });
            });

            // Initialize periode display
            const urlParams = new URLSearchParams(window.location.search);
            const currentPeriode = urlParams.get('periode') || 'harian';
            console.log('Current periode from URL:', currentPeriode);
            changePeriode(currentPeriode, false);
            
            console.log('Initialization complete');
        });
    </script>
</body>
</html>