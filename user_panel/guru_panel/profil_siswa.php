<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

// Ambil user_id dari URL
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Validasi user_id
if (!$user_id || !isset($_SESSION['logged_users']['guru'][$user_id])) {
    header("Location: ../../auth/login_user.php?role=guru");
    exit();
}

// Ambil data guru dari session
$user_data = $_SESSION['logged_users']['guru'][$user_id];
$guru_id = $user_data['user_id'];
$guru_nama = $user_data['nama'];

// Koneksi database
include_once __DIR__ . '../../../db.php';

// Cek apakah guru adalah wali kelas
$tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
$query_wali = "SELECT * FROM wali_kelas WHERE guru_id = ? AND tahun_ajaran = ?";
$stmt_wali = mysqli_prepare($conn, $query_wali);

if (!$stmt_wali) {
    die("Error prepare statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt_wali, 'is', $guru_id, $tahun_ajaran);
mysqli_stmt_execute($stmt_wali);
$result_wali = mysqli_stmt_get_result($stmt_wali);

if (mysqli_num_rows($result_wali) == 0) {
    mysqli_stmt_close($stmt_wali);
    header("Location: ../dashboard_guru.php?user_id=$user_id&error=not_wali_kelas");
    exit();
}

$wali_kelas_data = mysqli_fetch_assoc($result_wali);
$kelas_wali = $wali_kelas_data['kelas'];
mysqli_stmt_close($stmt_wali);

// Ekstrak tingkat dari nama kelas
$tingkat_kelas = 'X';
if (strpos($kelas_wali, 'XII') !== false) {
    $tingkat_kelas = 'XII';
} elseif (strpos($kelas_wali, 'XI') !== false) {
    $tingkat_kelas = 'XI';
} elseif (strpos($kelas_wali, 'X') !== false) {
    $tingkat_kelas = 'X';
}

// Ambil data siswa
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_jurusan = isset($_GET['jurusan']) ? mysqli_real_escape_string($conn, $_GET['jurusan']) : '';
$filter_jk = isset($_GET['jk']) ? mysqli_real_escape_string($conn, $_GET['jk']) : '';

$query_siswa = "SELECT s.*, 
                COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.id END) as total_kehadiran,
                COUNT(DISTINCT a.tanggal) as total_pertemuan
                FROM siswa s
                LEFT JOIN absensi a ON s.id = a.student_id
                WHERE s.kelas = ?";

if ($search != '') {
    $query_siswa .= " AND (s.nama LIKE '%$search%' OR s.nis LIKE '%$search%' OR s.email LIKE '%$search%')";
}

if ($filter_jurusan != '') {
    $query_siswa .= " AND s.jurusan = '$filter_jurusan'";
}

if ($filter_jk != '') {
    $query_siswa .= " AND s.jenis_kelamin = '$filter_jk'";
}

$query_siswa .= " GROUP BY s.id ORDER BY s.nama ASC";

$stmt_siswa = mysqli_prepare($conn, $query_siswa);

if (!$stmt_siswa) {
    die("Error prepare statement siswa: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt_siswa, 's', $kelas_wali);
mysqli_stmt_execute($stmt_siswa);
$result_siswa = mysqli_stmt_get_result($stmt_siswa);

// Hitung statistik kelas
$total_siswa = mysqli_num_rows($result_siswa);
$siswa_laki = 0;
$siswa_perempuan = 0;

mysqli_data_seek($result_siswa, 0);
while ($row = mysqli_fetch_assoc($result_siswa)) {
    if ($row['jenis_kelamin'] == 'L') {
        $siswa_laki++;
    } elseif ($row['jenis_kelamin'] == 'P') {
        $siswa_perempuan++;
    }
}
mysqli_data_seek($result_siswa, 0);

// Ambil daftar jurusan unik untuk filter
$query_jurusan = "SELECT DISTINCT jurusan FROM siswa WHERE kelas = ? AND jurusan IS NOT NULL ORDER BY jurusan";
$stmt_jurusan = mysqli_prepare($conn, $query_jurusan);
mysqli_stmt_bind_param($stmt_jurusan, 's', $kelas_wali);
mysqli_stmt_execute($stmt_jurusan);
$result_jurusan = mysqli_stmt_get_result($stmt_jurusan);
$daftar_jurusan = array();
while ($row = mysqli_fetch_assoc($result_jurusan)) {
    $daftar_jurusan[] = $row['jurusan'];
}
mysqli_stmt_close($stmt_jurusan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Siswa Kelas <?php echo htmlspecialchars($kelas_wali); ?> - EduAttend</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .main-content {
            margin-left: 20px;
            margin-right: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header-section {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            text-align: center;
        }

        .header-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            font-size: 14px;
            opacity: 0.95;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .subtitle-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-button {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.2s;
            margin-top: 8px;
        }

        .back-button:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 24px;
        }

        .stat-card {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-card i {
            font-size: 24px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin: 8px 0 4px;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 500;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-control {
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: #f7fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .table-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-container {
            overflow-x: auto;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
        }

        .student-table thead {
            background: #f7fafc;
        }

        .student-table thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .student-table tbody tr {
            border-bottom: 1px solid #f0f4f8;
            transition: background 0.15s;
        }

        .student-table tbody tr:hover {
            background: #f7fafc;
        }

        .student-table tbody td {
            padding: 16px;
            font-size: 14px;
            color: #2d3748;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-bold {
            font-weight: 600;
        }

        .text-muted {
            color: #718096;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-male {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-female {
            background: #fce7f3;
            color: #be185d;
        }

        .badge-jurusan {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fed7aa;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alamat-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: #2d3748;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .empty-state p {
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin: 10px;
                padding: 15px;
            }

            .header-title h1 {
                font-size: 22px;
            }

            .header-subtitle {
                flex-direction: column;
                gap: 8px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .table-container {
                overflow-x: scroll;
            }

            .student-table {
                min-width: 1200px;
            }
        }

        /* Print Styles */
        @media print {
            .filter-section,
            .back-button,
            .sidebar {
                display: none !important;
            }

            .main-content {
                margin: 0;
            }

            body {
                background: white;
            }

            .header-section {
                background: none;
                border: 2px solid #000;
                color: #000;
            }

            .stat-card {
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_guru.php'; ?>

    <div class="main-content">
        <div class="container">
            <!-- Header Section - CENTERED -->
            <div class="header-section">
                <div class="header-top">
                    <div class="header-title">
                        <h1>Data Siswa Kelas <?php echo htmlspecialchars($kelas_wali); ?></h1>
                        <div class="header-subtitle">
                            <span class="subtitle-item">
                                <i class="fas fa-user-tie"></i>
                                <span><?php echo htmlspecialchars($guru_nama); ?></span>
                            </span>
                            <span class="subtitle-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo htmlspecialchars($tahun_ajaran); ?></span>
                            </span>
                            <span class="subtitle-item">
                                <i class="fas fa-layer-group"></i>
                                <span>Tingkat <?php echo htmlspecialchars($tingkat_kelas); ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo $total_siswa; ?></div>
                        <div class="stat-label">Total Siswa</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-mars"></i>
                        <div class="stat-number"><?php echo $siswa_laki; ?></div>
                        <div class="stat-label">Laki-laki</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-venus"></i>
                        <div class="stat-number"><?php echo $siswa_perempuan; ?></div>
                        <div class="stat-label">Perempuan</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>
                    Filter & Pencarian
                </div>
                <form method="GET" action="">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label>Cari Siswa</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Nama, NIS, atau Email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <label>Jenis Kelamin</label>
                            <select name="jk" class="form-control">
                                <option value="">Semua</option>
                                <option value="L" <?php echo $filter_jk == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo $filter_jk == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jurusan</label>
                            <select name="jurusan" class="form-control">
                                <option value="">Semua</option>
                                <?php foreach ($daftar_jurusan as $jur): ?>
                                <option value="<?php echo htmlspecialchars($jur); ?>" 
                                        <?php echo $filter_jurusan == $jur ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($jur); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <?php if($search != '' || $filter_jurusan != '' || $filter_jk != ''): ?>
                        <a href="?user_id=<?php echo $user_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <?php if (mysqli_num_rows($result_siswa) > 0): ?>
            <div class="table-section">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-table"></i>
                        Daftar Siswa
                    </div>
                </div>
                <div class="table-container">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th class="text-center" width="50">No</th>
                                <th width="100">NIS</th>
                                <th width="200">Nama Siswa</th>
                                <th width="220">Email</th>
                                <th width="110">Tanggal Lahir</th>
                                <th width="130">Jenis Kelamin</th>
                                <th width="80">Jurusan</th>
                                <th width="80">Kelas</th>
                                <th width="250">Alamat</th>
                                <th class="text-center" width="90">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($result_siswa, 0);
                            $no = 1;
                            while ($siswa = mysqli_fetch_assoc($result_siswa)): 
                                $persentase_kehadiran = 0;
                                if ($siswa['total_pertemuan'] > 0) {
                                    $persentase_kehadiran = round(($siswa['total_kehadiran'] / $siswa['total_pertemuan']) * 100, 1);
                                }
                                
                                // Tentukan badge class
                                $badge_class = 'badge-success';
                                if ($persentase_kehadiran < 75) {
                                    $badge_class = 'badge-danger';
                                } elseif ($persentase_kehadiran < 90) {
                                    $badge_class = 'badge-warning';
                                }
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $no++; ?></td>
                                <td class="text-bold"><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                <td>
                                    <div class="text-bold"><?php echo htmlspecialchars($siswa['nama']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($siswa['email'])): ?>
                                    <div class="email-cell" title="<?php echo htmlspecialchars($siswa['email']); ?>">
                                        <i class=""></i> <?php echo htmlspecialchars($siswa['email']); ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($siswa['tanggal_lahir']) {
                                        $tgl_lahir = new DateTime($siswa['tanggal_lahir']);
                                        echo $tgl_lahir->format('d M Y');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($siswa['jenis_kelamin']): ?>
                                    <span class="badge <?php echo $siswa['jenis_kelamin'] == 'L' ? 'badge-male' : 'badge-female'; ?>">
                                        <?php echo $siswa['jenis_kelamin'] == 'L' ? '♂ Laki-laki' : '♀ Perempuan'; ?>
                                    </span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-jurusan">
                                        <?php echo htmlspecialchars($siswa['jurusan'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="text-bold"><?php echo htmlspecialchars($siswa['kelas'] ?? '-'); ?></td>
                                <td>
                                    <div class="alamat-cell" title="<?php echo htmlspecialchars($siswa['alamat'] ?? '-'); ?>">
                                        <?php echo htmlspecialchars($siswa['alamat'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $persentase_kehadiran; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak Ada Data</h3>
                <p>Tidak ada siswa yang sesuai dengan filter pencarian.</p>
                <?php if($search != '' || $filter_jurusan != '' || $filter_jk != ''): ?>
                <a href="?user_id=<?php echo $user_id; ?>" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="fas fa-redo"></i> Reset Filter
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
if (isset($stmt_siswa)) {
    mysqli_stmt_close($stmt_siswa);
}
mysqli_close($conn);
?>