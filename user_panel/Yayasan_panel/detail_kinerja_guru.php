<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ketua_yayasan') {
    header("Location: login_yayasan.php?error=unauthorized");
    exit();
}

include '../../db.php';
include 'func_kinerja.php';

// Get parameter
$guru_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$periode = isset($_GET['periode']) ? $_GET['periode'] : date('Y-m');

if ($guru_id === 0) {
    header("Location: kinerja_guru.php");
    exit();
}

// Get data guru
$query_guru = "SELECT * FROM guru WHERE id = ?";
$stmt = mysqli_prepare($conn, $query_guru);
mysqli_stmt_bind_param($stmt, "i", $guru_id);
mysqli_stmt_execute($stmt);
$result_guru = mysqli_stmt_get_result($stmt);
$guru = mysqli_fetch_assoc($result_guru);

if (!$guru) {
    header("Location: kinerja_guru.php?error=guru_not_found");
    exit();
}

// Get kinerja guru
$kinerja = hitungKinerjaGuru($conn, $guru_id, $periode);
$badge = getBadgeKategori($kinerja['kategori']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kinerja - <?php echo htmlspecialchars($guru['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .navbar-custom {
            background: #ffffff;
            border-bottom: 1px solid #e5e5e7;
            padding: 1rem 2rem;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
            color: #1d1d1f !important;
        }

        .main-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Profile Section */
        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 2rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
        }

        .profile-info h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .profile-meta {
            color: #86868b;
            font-size: 0.9375rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f5f5f7;
        }

        .profile-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .profile-label {
            font-size: 0.8125rem;
            color: #86868b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-value {
            font-size: 0.9375rem;
            font-weight: 500;
            color: #1d1d1f;
        }
        .btn-primary {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #1a252f;
            color: white;
            transform: translateY(-1px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.primary {
            background: #e3f2fd;
            color: #1976d2;
        }

        .stat-icon.success {
            background: #e8f5e9;
            color: #388e3c;
        }

        .stat-icon.warning {
            background: #fff3e0;
            color: #f57c00;
        }

        .stat-title {
            font-size: 0.8125rem;
            color: #86868b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-content {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #1d1d1f;
            line-height: 1;
        }

        .stat-unit {
            font-size: 1.125rem;
            color: #86868b;
        }

        .stat-detail {
            font-size: 0.875rem;
            color: #86868b;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f5f5f7;
            margin-top: 0.5rem;
        }

        .progress-bar {
            border-radius: 10px;
        }

        /* Score Card */
        .score-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
            text-align: center;
            margin-bottom: 2rem;
        }

        .score-label {
            font-size: 0.875rem;
            color: #86868b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
        }

        .score-gauge {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 1.5rem;
        }

        .score-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                <?php echo getProgressColor($kinerja['skor_kinerja']); ?> 0deg,
                <?php echo getProgressColor($kinerja['skor_kinerja']); ?> <?php echo ($kinerja['skor_kinerja'] / 100) * 360; ?>deg,
                #f5f5f7 <?php echo ($kinerja['skor_kinerja'] / 100) * 360; ?>deg,
                #f5f5f7 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .score-inner {
            width: 160px;
            height: 160px;
            background: white;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .score-number {
            font-size: 3rem;
            font-weight: 700;
            color: #1d1d1f;
            line-height: 1;
        }

        .score-max {
            font-size: 1rem;
            color: #86868b;
        }

        .badge-status-large {
            padding: 0.75rem 2rem;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            display: inline-block;
        }

        /* Table */
        .table-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e5e7;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f5f5f7;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1d1d1f;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #f5f5f7;
            color: #1d1d1f;
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-top: 1px solid #f5f5f7;
        }

        .badge-kelas {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .btn-back {
            background: white;
            color: #1d1d1f;
            border: 1px solid #e5e5e7;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #f5f5f7;
            color: #1d1d1f;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .score-gauge {
                width: 150px;
                height: 150px;
            }

            .score-inner {
                width: 120px;
                height: 120px;
            }

            .score-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a href="kinerja_guru.php?periode=<?php echo $periode; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="tambah_feedback_guru.php?guru_id=<?php echo $guru_id; ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Feedback
            </a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($guru['nama'], 0, 2)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($guru['nama']); ?></h2>
                    <div class="profile-meta">
                        NIP: <?php echo htmlspecialchars($guru['nip']); ?> • 
                        <?php echo htmlspecialchars($guru['mata_pelajaran']); ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-grid">
                <div class="profile-item">
                    <span class="profile-label">Email</span>
                    <span class="profile-value"><?php echo htmlspecialchars($guru['email'] ?? '-'); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Mata Pelajaran</span>
                    <span class="profile-value"><?php echo htmlspecialchars($guru['mata_pelajaran']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Periode</span>
                    <span class="profile-value"><?php echo formatBulanIndonesia($periode); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Total Kelas</span>
                    <span class="profile-value"><?php echo count($kinerja['kelas_diajar']); ?> Kelas</span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-title">Total Pertemuan</div>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $kinerja['total_pertemuan']; ?></div>
                    <div class="stat-unit">Pertemuan</div>
                </div>
                <div class="stat-detail">Dalam periode <?php echo formatBulanIndonesia($periode); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-title">Siswa Hadir</div>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $kinerja['total_siswa_hadir']; ?></div>
                    <div class="stat-unit">/ <?php echo $kinerja['total_siswa_seharusnya']; ?></div>
                </div>
                <div class="stat-detail">
                    Alpha: <?php echo $kinerja['total_siswa_alpha']; ?>, 
                    Izin: <?php echo $kinerja['total_siswa_izin']; ?>, 
                    Sakit: <?php echo $kinerja['total_siswa_sakit']; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-title">Persentase Kehadiran</div>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($kinerja['persentase_hadir'], 1); ?></div>
                    <div class="stat-unit">%</div>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" 
                        style="width: <?php echo $kinerja['persentase_hadir']; ?>%; 
                        background-color: <?php echo getProgressColor($kinerja['persentase_hadir']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Score Card -->
        <div class="score-card">
            <div class="score-label">Skor Kinerja Keseluruhan</div>
            <div class="score-gauge">
                <div class="score-circle">
                    <div class="score-inner">
                        <div class="score-number"><?php echo number_format($kinerja['skor_kinerja'], 0); ?></div>
                        <div class="score-max">/100</div>
                    </div>
                </div>
            </div>
            <span class="badge-status-large" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['text']; ?>">
                <?php echo $badge['label']; ?>
            </span>
        </div>

        <!-- Table Kelas -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title">Detail Kelas Yang Diajar</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Jurusan</th>
                            <th>Total Siswa</th>
                            <th>Rata-rata Hadir</th>
                            <th>Persentase</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kinerja['kelas_diajar'])): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                    <p class="text-muted">Belum ada data kelas untuk periode ini</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($kinerja['kelas_diajar'] as $kelas): ?>
                                <?php $badge_kelas = getBadgeKategori($kelas['kategori']); ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($kelas['kelas']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($kelas['mata_pelajaran']); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['jurusan']); ?></td>
                                    <td><?php echo $kelas['total_siswa']; ?> Siswa</td>
                                    <td><?php echo $kelas['total_hadir'] ?? 0; ?> Siswa</td>
                                    <td>
                                        <div class="mb-1"><strong><?php echo number_format($kelas['persentase_hadir'], 1); ?>%</strong></div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                style="width: <?php echo $kelas['persentase_hadir']; ?>%; 
                                                background-color: <?php echo getProgressColor($kelas['persentase_hadir']); ?>">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-kelas" style="background: <?php echo $badge_kelas['bg']; ?>; color: <?php echo $badge_kelas['text']; ?>">
                                            <?php echo $badge_kelas['label']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>