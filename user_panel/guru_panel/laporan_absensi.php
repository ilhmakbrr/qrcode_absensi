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

// Filter parameters - HANYA BULAN
$jurusan_filter = isset($_GET['jurusan']) ? trim($_GET['jurusan']) : '';
$kelas_filter   = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$bulan_filter   = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m'); // Default bulan ini

// Tentukan rentang tanggal berdasarkan bulan yang dipilih
$tanggal_dari = '';
$tanggal_sampai = '';

if (!empty($bulan_filter) && preg_match('/^\d{4}-\d{2}$/', $bulan_filter)) {
    $tanggal_dari = $bulan_filter . '-01';
    $tanggal_sampai = date('Y-m-t', strtotime($tanggal_dari)); // Last day of month
}

$students_data = [];
$total_hari_sekolah = 0;

// Cek apakah filter minimal sudah diisi
$filters_filled = !empty($jurusan_filter) && !empty($kelas_filter) && !empty($bulan_filter);

if ($filters_filled) {
    // Hitung total hari sekolah berdasarkan mata pelajaran guru
    $query_days = "SELECT COUNT(DISTINCT tanggal) as total_days 
                   FROM absensi 
                   WHERE jurusan = ? 
                   AND kelas = ? 
                   AND mata_pelajaran = ?
                   AND tanggal BETWEEN ? AND ?";
    
    $stmt_days = mysqli_prepare($conn, $query_days);
    mysqli_stmt_bind_param($stmt_days, "sssss", $jurusan_filter, $kelas_filter, $mata_pelajaran, $tanggal_dari, $tanggal_sampai);
    mysqli_stmt_execute($stmt_days);
    $result_days = mysqli_stmt_get_result($stmt_days);
    
    if ($result_days) {
        $row_days = mysqli_fetch_assoc($result_days);
        $total_hari_sekolah = $row_days['total_days'];
    }
    mysqli_stmt_close($stmt_days);

    // Ambil data siswa dari kelas yang dipilih
    $query_students = "SELECT DISTINCT s.id, s.nis, s.nama 
                       FROM siswa s 
                       WHERE s.jurusan = ? 
                       AND s.kelas = ?
                       ORDER BY s.nis ASC";
    
    $stmt_students = mysqli_prepare($conn, $query_students);
    mysqli_stmt_bind_param($stmt_students, "ss", $jurusan_filter, $kelas_filter);
    mysqli_stmt_execute($stmt_students);
    $result_students = mysqli_stmt_get_result($stmt_students);
    
    if ($result_students) {
        while ($student = mysqli_fetch_assoc($result_students)) {
            // Hitung kehadiran per siswa berdasarkan mata pelajaran
            $query_attendance = "SELECT status, COUNT(*) as jumlah 
                                FROM absensi 
                                WHERE student_id = ?
                                AND jurusan = ?
                                AND kelas = ?
                                AND mata_pelajaran = ?
                                AND tanggal BETWEEN ? AND ?
                                GROUP BY status";
            
            $stmt_attendance = mysqli_prepare($conn, $query_attendance);
            mysqli_stmt_bind_param($stmt_attendance, "isssss", 
                $student['id'], 
                $jurusan_filter, 
                $kelas_filter, 
                $mata_pelajaran,
                $tanggal_dari, 
                $tanggal_sampai
            );
            mysqli_stmt_execute($stmt_attendance);
            $result_attendance = mysqli_stmt_get_result($stmt_attendance);
            
            $attendance_data = [
                'hadir' => 0,
                'alpha' => 0,
                'sakit' => 0,
                'izin' => 0
            ];
            
            if ($result_attendance) {
                while ($row = mysqli_fetch_assoc($result_attendance)) {
                    $status = strtolower($row['status']);
                    if (isset($attendance_data[$status])) {
                        $attendance_data[$status] = $row['jumlah'];
                    }
                }
            }
            mysqli_stmt_close($stmt_attendance);
            
            $total_kehadiran = array_sum($attendance_data);
            $persentase_kehadiran = $total_hari_sekolah > 0 ? 
                round(($attendance_data['hadir'] / $total_hari_sekolah) * 100, 1) : 0;
            
            $students_data[] = [
                'nis' => $student['nis'],
                'nama' => $student['nama'],
                'hadir' => $attendance_data['hadir'],
                'alpha' => $attendance_data['alpha'],
                'sakit' => $attendance_data['sakit'],
                'izin' => $attendance_data['izin'],
                'total_kehadiran' => $total_kehadiran,
                'persentase' => $persentase_kehadiran
            ];
        }
    }
    mysqli_stmt_close($stmt_students);
}

// Hitung statistik kelas
$total_siswa = count($students_data);
$rata_rata_kehadiran = 0;
if ($total_siswa > 0) {
    $total_persentase = array_sum(array_column($students_data, 'persentase'));
    $rata_rata_kehadiran = round($total_persentase / $total_siswa, 1);
}

// Format nama bulan dalam Bahasa Indonesia
function getNamaBulan($bulan_value) {
    $bulan_indo = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    
    if (preg_match('/^(\d{4})-(\d{2})$/', $bulan_value, $matches)) {
        $tahun = $matches[1];
        $bulan = $matches[2];
        return $bulan_indo[$bulan] . ' ' . $tahun;
    }
    return $bulan_value;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kehadiran Bulanan - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Times New Roman', serif;
            background: #f8f9fa;
        }
        
        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        
        .header .guru-info {
            margin-top: 10px;
            font-size: 0.9rem;
            opacity: 0.9;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: inline-block;
        }

        .filter-form {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .filter-form h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
        }
        
        .form-select, .form-control {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }

        /* Header Laporan */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        .report-header .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: #2c3e50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .report-header h1 {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .report-header h2 {
            font-size: 1.4rem;
            color: #34495e;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .report-header .school-info {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .report-header .report-title {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .report-info .info-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }

        .report-info .info-box h6 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1rem;
            text-transform: uppercase;
        }

        .report-info .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #dee2e6;
        }

        .report-info .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .report-info .info-label {
            font-weight: 500;
            color: #555;
        }

        .report-info .info-value {
            font-weight: bold;
            color: #2c3e50;
        }

        /* Tabel */
        .table-container {
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        thead th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            border-right: 1px solid #fff;
        }

        tbody td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }

        tbody td:first-child {
            font-weight: bold;
        }

        tbody td:nth-child(2) {
            text-align: left;
            font-weight: 500;
        }

        tbody td:nth-child(3) {
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:hover {
            background-color: #e3f2fd;
        }

        .percentage-cell {
            font-weight: bold;
        }

        .percentage-excellent { color: #27ae60; }
        .percentage-good { color: #2980b9; }
        .percentage-fair { color: #f39c12; }
        .percentage-poor { color: #e74c3c; }

        /* Ringkasan */
        .summary-section {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }

        .summary-section h5 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .summary-item .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .summary-item .value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .summary-item .label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 500;
        }

        .summary-total { border-color: #3498db; color: #3498db; }
        .summary-average { border-color: #27ae60; color: #27ae60; }
        .summary-days { border-color: #f39c12; color: #f39c12; }

        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .signature-box .title {
            font-weight: bold;
            margin-bottom: 60px;
            color: #2c3e50;
        }

        .signature-box .line {
            border-bottom: 2px solid #2c3e50;
            margin-bottom: 5px;
        }

        .signature-box .name {
            font-weight: 500;
            color: #555;
        }

        /* Print Styles */
        @media print {
            .sidebar, 
            .filter-form, 
            .btn,
            .no-print,
            .header {
                display: none !important;
            }
            
            @page {
                size: A4;
                margin: 15mm 10mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .report-header {
                page-break-after: avoid !important;
            }
            
            thead {
                display: table-header-group !important;
            }
            
            tr {
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>
    
<?php include 'sidebar_guru.php'; ?>

<div class="main-content">
    <div class="header no-print">
        <h1><i class="fas fa-file-alt"></i> Laporan Kehadiran Bulanan</h1>
        <p>Laporan rekapitulasi kehadiran siswa per bulan</p>
        <div class="guru-info">
        <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($nama); ?> 
                | <i class=""></i> <?php echo htmlspecialchars($mata_pelajaran); ?>
                | <i class=""></i><?php echo htmlspecialchars($nip); ?>
        </div>
    </div>
    
    <!-- Form Filter -->
    <div class="filter-form no-print">
        <h5><i class="fas fa-filter"></i> Filter Laporan</h5>
        <p class="text-muted mb-3">Pilih jurusan, kelas, dan bulan untuk menampilkan laporan kehadiran</p>
        
        <form method="GET" class="row g-3">
            <!-- Hidden input user_id -->
            <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">
            
            <div class="col-md-4">
                <label class="form-label">Jurusan <span style="color: red;">*</span></label>
                <select name="jurusan" id="jurusanSelect" class="form-select" required>
                    <option value="">-- Pilih Jurusan --</option>
                    <option value="IPA" <?= ($jurusan_filter == 'IPA') ? 'selected' : '' ?>>IPA (Ilmu Pengetahuan Alam)</option>
                    <option value="IPS" <?= ($jurusan_filter == 'IPS') ? 'selected' : '' ?>>IPS (Ilmu Pengetahuan Sosial)</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Kelas <span style="color: red;">*</span></label>
                <select name="kelas" id="kelasSelect" class="form-select" required>
                    <option value="">-- Pilih Kelas --</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Bulan <span style="color: red;">*</span></label>
                <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan_filter) ?>" required>
            </div>

            <div class="col-12">
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tampilkan Laporan
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    
                    <?php if ($filters_filled && $total_siswa > 0): ?>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Cetak Laporan
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$filters_filled): ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Lengkapi Filter</strong><br>
            Silakan pilih jurusan, kelas, dan bulan terlebih dahulu untuk menampilkan laporan kehadiran.
        </div>
    <?php else: ?>

        <!-- Header Laporan -->
        <div class="report-header">
            <div class="school-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>SMA PGRI 2 Padang</h1>
            <h2>Laporan Kehadiran Siswa Bulanan</h2>
            <div class="school-info">
                Jl. Linggar Jati no.1 Lubuk Begalung, Sumatera Barat 25173<br>
                Telepon: (0751) 72293 | Email: smapgri2padang@kemdikbud.go.id
            </div>
            
            <div class="report-title">
                Kelas <?= htmlspecialchars($kelas_filter) ?> - <?= htmlspecialchars($jurusan_filter) ?> | 
                Mata Pelajaran: <?= htmlspecialchars($mata_pelajaran) ?> |
                Periode: <?= getNamaBulan($bulan_filter) ?>
            </div>
        </div>

        <!-- Info Laporan -->
        <div class="report-info">
            <div class="info-box">
                <h6><i class="fas fa-info-circle"></i> Informasi Kelas</h6>
                <div class="info-item">
                    <span class="info-label">Jurusan</span>
                    <span class="info-value"><?= htmlspecialchars($jurusan_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($kelas_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Mata Pelajaran</span>
                    <span class="info-value"><?= htmlspecialchars($mata_pelajaran) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Wali Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($nama) ?></span>
                </div>
            </div>
            
            <div class="info-box">
                <h6><i class="fas fa-calendar-alt"></i> Periode Laporan</h6>
                <div class="info-item">
                    <span class="info-label">Bulan</span>
                    <span class="info-value"><?= getNamaBulan($bulan_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Mulai</span>
                    <span class="info-value"><?= date('d F Y', strtotime($tanggal_dari)) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Selesai</span>
                    <span class="info-value"><?= date('d F Y', strtotime($tanggal_sampai)) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Cetak</span>
                    <span class="info-value"><?= date('d F Y') ?></span>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No.</th>
                        <th style="width: 200px;">Nama Siswa</th>
                        <th style="width: 100px;">NIS</th>
                        <th style="width: 80px;">Total Hari</th>
                        <th style="width: 60px;">Hadir</th>
                        <th style="width: 60px;">Alpha</th>
                        <th style="width: 60px;">Izin</th>
                        <th style="width: 60px;">Sakit</th>
                        <th style="width: 100px;">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_siswa > 0): ?>
                        <?php $no = 1; foreach ($students_data as $student): ?>
                        <?php
                        $persentase_class = '';
                        if ($student['persentase'] >= 90) $persentase_class = 'percentage-excellent';
                        elseif ($student['persentase'] >= 75) $persentase_class = 'percentage-good';
                        elseif ($student['persentase'] >= 60) $persentase_class = 'percentage-fair';
                        else $persentase_class = 'percentage-poor';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($student['nama']) ?></td>
                            <td><?= htmlspecialchars($student['nis']) ?></td>
                            <td><?= $total_hari_sekolah ?></td>
                            <td><?= $student['hadir'] ?></td>
                            <td><?= $student['alpha'] ?></td>
                            <td><?= $student['izin'] ?></td>
                            <td><?= $student['sakit'] ?></td>
                            <td class="percentage-cell <?= $persentase_class ?>">
                                <?= $student['persentase'] ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                                <strong>Belum Ada Data</strong><br>
                                Tidak ada data kehadiran untuk bulan <strong><?= getNamaBulan($bulan_filter) ?></strong><br>
                                Mata Pelajaran: <strong><?= htmlspecialchars($mata_pelajaran) ?></strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_siswa > 0): ?>
        <!-- Ringkasan -->
        <div class="summary-section">
            <h5><i class="fas fa-chart-bar"></i> Ringkasan Laporan</h5>
            
            <div class="summary-grid">
                <div class="summary-item summary-total">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="value"><?= $total_siswa ?></div>
                    <div class="label">Total Siswa</div>
                </div>
                
                <div class="summary-item summary-average">
                    <div class="icon"><i class="fas fa-percentage"></i></div>
                    <div class="value"><?= $rata_rata_kehadiran ?>%</div>
                    <div class="label">Rata-rata Kehadiran</div>
                </div>
                
                <div class="summary-item summary-days">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="value"><?= $total_hari_sekolah ?></div>
                    <div class="label">Hari Pertemuan</div>
                </div>
            </div>

            <div class="notes-section">
                <h6><i class="fas fa-sticky-note"></i> Catatan:</h6>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Laporan ini khusus untuk mata pelajaran <strong><?= htmlspecialchars($mata_pelajaran) ?></strong></li>
                    <li>Persentase kehadiran dihitung berdasarkan jumlah hari hadir dibagi total hari pertemuan</li>
                    <li>Total hari pertemuan: <strong><?= $total_hari_sekolah ?> hari</strong> untuk bulan <?= getNamaBulan($bulan_filter) ?></li>
                    <li>Standar kehadiran minimal adalah 75% untuk dapat mengikuti evaluasi</li>
                    <?php if ($rata_rata_kehadiran < 75): ?>
                    <li style="color: #e74c3c; font-weight: bold;">⚠️ Rata-rata kehadiran kelas di bawah standar minimal (<?= $rata_rata_kehadiran ?>%)</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Tanda Tangan -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="title">Mengetahui,<br>Kepala Sekolah</div>
                <div class="line"></div>
                <div class="name">Ilham Akbar, S.Kom</div>
                <div style="font-size: 0.9rem; color: #666;">NIP. 196701011990031004</div>
            </div>
            
            <div class="signature-box">
                <div class="title">Padang, <?= date('d F Y') ?><br>Guru Mata Pelajaran</div>
                <div class="line"></div>
                <div class="name"><?= htmlspecialchars($nama) ?></div>
                <div style="font-size: 0.9rem; color: #666;">NIP. <?= htmlspecialchars($nip) ?></div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    console.log('=== Laporan Absensi Script Loaded ===');
    
    const kelasIPA = ['X IPA 1', 'X IPA 2', 'XI IPA 1', 'XI IPA 2', 'XII IPA 1', 'XII IPA 2'];
    const kelasIPS = ['X IPS 1', 'X IPS 2', 'XI IPS 1', 'XI IPS 2', 'XII IPS 1', 'XII IPS 2'];
    const currentUserId = <?php echo $current_user_id; ?>;

    function updateKelasDropdown() {
        const jurusan = document.getElementById('jurusanSelect').value;
        const kelasSelect = document.getElementById('kelasSelect');
        const urlParams = new URLSearchParams(window.location.search);
        const selectedValue = urlParams.get('kelas') || '';

        console.log('Update kelas untuk jurusan:', jurusan);
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
        
        console.log('Kelas options updated:', kelasList.length);
    }

    function resetFilter() {
        console.log('Reset filter');
        window.location.href = window.location.pathname + '?user_id=' + currentUserId;
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded');
        console.log('Current User ID:', currentUserId);
        
        updateKelasDropdown();
        
        document.getElementById('jurusanSelect').addEventListener('change', function() {
            console.log('Jurusan changed');
            updateKelasDropdown();
        });
        
        console.log('Initialization complete');
    });
</script>

</body>
</html>