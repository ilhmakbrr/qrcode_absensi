<?php
require_once '../auth_user/auth_check_yayasan.php';

// Koneksi database
include '../../db.php';
include 'func_kinerja.php';

$ketua_id = $_SESSION['user_id'];
$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);

// Get periode dari parameter atau default bulan ini
$periode = isset($_GET['periode']) ? $_GET['periode'] : date('Y-m');
$filter_mapel = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get list periode (6 bulan terakhir)
$periode_list = getPeriodeList(6);

// Get semua mata pelajaran untuk filter
$query_mapel = "SELECT DISTINCT mata_pelajaran FROM guru WHERE mata_pelajaran IS NOT NULL ORDER BY mata_pelajaran ASC";
$result_mapel = mysqli_query($conn, $query_mapel);

// Get data kinerja semua guru
$guru_kinerja = getAllGuruKinerja($conn, $periode);

// Filter berdasarkan mata pelajaran
if (!empty($filter_mapel)) {
    $guru_kinerja = array_filter($guru_kinerja, function($guru) use ($filter_mapel) {
        return $guru['mata_pelajaran'] === $filter_mapel;
    });
}

// Filter berdasarkan search
if (!empty($search)) {
    $guru_kinerja = array_filter($guru_kinerja, function($guru) use ($search) {
        return stripos($guru['nama'], $search) !== false || stripos($guru['nip'], $search) !== false;
    });
}

// Sorting berdasarkan skor (tertinggi ke terendah)
usort($guru_kinerja, function($a, $b) {
    return $b['skor_kinerja'] <=> $a['skor_kinerja'];
});

// Hitung statistik global
$total_guru = count($guru_kinerja);
$rata_rata_skor = $total_guru > 0 ? array_sum(array_column($guru_kinerja, 'skor_kinerja')) / $total_guru : 0;
$guru_excellent = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'excellent'));
$guru_poor = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'poor'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kinerja Guru - EduAttend</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 0.9375rem;
            color: #86868b;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: #86868b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #1d1d1f;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 0.5rem;
        }

        .form-select, .form-control {
            border: 1px solid #e5e5e7;
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: #1d1d1f;
            box-shadow: 0 0 0 3px rgba(29, 29, 31, 0.1);
        }

        .btn-filter {
            background: #1d1d1f;
            color: white;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: #424245;
            transform: translateY(-1px);
        }

        /* Table */
        .table-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e5e7;
            overflow: hidden;
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
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #f5f5f7;
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        .guru-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .guru-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .guru-details {
            flex: 1;
        }

        .guru-name {
            font-weight: 500;
            color: #1d1d1f;
            font-size: 0.9375rem;
            margin-bottom: 0.125rem;
        }

        .guru-nip {
            font-size: 0.8125rem;
            color: #86868b;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f5f5f7;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .badge-status {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            border: none;
        }

        .btn-detail {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #e5e5e7;
            background: white;
            color: #1d1d1f;
            transition: all 0.3s ease;
        }

        .btn-detail:hover {
            background: #1d1d1f;
            color: white;
            border-color: #1d1d1f;
        }

        .btn-print {
            background: white;
            color: #1d1d1f;
            border: 1px solid #e5e5e7;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-print:hover {
            background: #f5f5f7;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #86868b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_yayasan.php">
                <i class="fas fa-arrow-left me-2"></i> Kinerja Guru
            </a>
            <a href="laporan_kinerja_guru.php?periode=<?php echo $periode; ?>" class="btn btn-print" target="_blank">
                <i class="fas fa-print me-2"></i>Cetak Laporan
            </a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Monitoring Kinerja Guru</h1>
            <p class="page-subtitle">Periode: <?php echo formatBulanIndonesia($periode); ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Guru</div>
                <div class="stat-value"><?php echo $total_guru; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Rata-rata Skor</div>
                <div class="stat-value"><?php echo number_format($rata_rata_skor, 1); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Guru Excellent</div>
                <div class="stat-value text-success"><?php echo $guru_excellent; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Perlu Perhatian</div>
                <div class="stat-value text-danger"><?php echo $guru_poor; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Periode</label>
                        <select name="periode" class="form-select">
                            <?php foreach ($periode_list as $p): ?>
                                <option value="<?php echo $p['value']; ?>" <?php echo $periode === $p['value'] ? 'selected' : ''; ?>>
                                    <?php echo $p['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="mapel" class="form-select">
                            <option value="">Semua Mata Pelajaran</option>
                            <?php while ($mapel = mysqli_fetch_assoc($result_mapel)): ?>
                                <option value="<?php echo $mapel['mata_pelajaran']; ?>" 
                                    <?php echo $filter_mapel === $mapel['mata_pelajaran'] ? 'selected' : ''; ?>>
                                    <?php echo $mapel['mata_pelajaran']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Cari Guru</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama atau NIP..." 
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-filter w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Guru</th>
                            <th>Mata Pelajaran</th>
                            <th>Jumlah Kelas</th>
                            <th>Kehadiran Siswa</th>
                            <th>Skor</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($guru_kinerja)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Tidak ada data guru untuk periode ini</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($guru_kinerja as $guru): ?>
                                <?php $badge = getBadgeKategori($guru['kategori']); ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td>
                                        <div class="guru-info">
                                            <div class="guru-avatar">
                                                <?php echo strtoupper(substr($guru['nama'], 0, 2)); ?>
                                            </div>
                                            <div class="guru-details">
                                                <div class="guru-name"><?php echo htmlspecialchars($guru['nama']); ?></div>
                                                <div class="guru-nip">NIP: <?php echo htmlspecialchars($guru['nip']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($guru['mata_pelajaran']); ?></td>
                                    <td><?php echo $guru['total_kelas']; ?> Kelas</td>
                                    <td>
                                        <div class="mb-1"><?php echo number_format($guru['persentase_hadir'], 1); ?>%</div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                style="width: <?php echo $guru['persentase_hadir']; ?>%; 
                                                background-color: <?php echo getProgressColor($guru['persentase_hadir']); ?>">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($guru['skor_kinerja'], 1); ?></strong> / 100
                                    </td>
                                    <td>
                                        <span class="badge-status" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['text']; ?>">
                                            <?php echo $badge['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="detail_kinerja_guru.php?id=<?php echo $guru['id']; ?>&periode=<?php echo $periode; ?>" 
                                            class="btn btn-detail">
                                            <i class="fas fa-eye me-1"></i>Detail
                                        </a>
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