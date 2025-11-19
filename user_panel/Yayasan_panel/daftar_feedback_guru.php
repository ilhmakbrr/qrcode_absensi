<?php
require_once '../auth_user/auth_check_yayasan.php';

// Koneksi database
include '../../db.php';
include 'func_kinerja.php';

$ketua_id = $_SESSION['user_id'];
$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);

// Filter
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query feedback
$query = "
    SELECT f.*, 
           g.nama as nama_guru, 
           g.nip, 
           g.mata_pelajaran,
           k.nama as nama_ketua
    FROM feedback_guru f
    LEFT JOIN guru g ON f.guru_id = g.id
    LEFT JOIN ketua_yayasan k ON f.ketua_yayasan_id = k.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filter_kategori)) {
    $query .= " AND f.kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}

if (!empty($filter_status)) {
    $query .= " AND f.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (g.nama LIKE ? OR g.nip LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query .= " ORDER BY f.tanggal DESC, f.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result_feedback = mysqli_stmt_get_result($stmt);

// Statistik
$query_stats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN kategori = 'pujian' THEN 1 ELSE 0 END) as pujian,
        SUM(CASE WHEN kategori = 'peringatan' THEN 1 ELSE 0 END) as peringatan
    FROM feedback_guru
";
$result_stats = mysqli_query($conn, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Guru - EduAttend</title>
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

        /* Filter */
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

        .btn-filter {
            background: #1d1d1f;
            color: white;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-add {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add:hover {
            background: #1a252f;
            color: white;
        }

        /* Feedback Cards */
        .feedback-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .feedback-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f5f5f7;
        }

        .feedback-guru-info {
            flex: 1;
        }

        .guru-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 0.25rem;
        }

        .guru-meta {
            font-size: 0.875rem;
            color: #86868b;
        }

        .feedback-meta {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .feedback-date {
            font-size: 0.875rem;
            color: #86868b;
        }

        .badge-kategori {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .badge-pujian {
            background: #d7f5e3;
            color: #0e6b31;
        }

        .badge-peringatan {
            background: #f8d7da;
            color: #842029;
        }

        .badge-saran {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-lainnya {
            background: #e2e3e5;
            color: #41464b;
        }

        .badge-status {
            padding: 0.25rem 0.75rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-pending {
            background: #fff3cd;
            color: #997404;
        }

        .badge-selesai {
            background: #d7f5e3;
            color: #0e6b31;
        }

        .feedback-content {
            margin-bottom: 1rem;
        }

        .feedback-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #86868b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .feedback-text {
            font-size: 0.9375rem;
            color: #1d1d1f;
            line-height: 1.6;
        }

        .feedback-tindaklanjut {
            background: #f5f5f7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .feedback-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #f5f5f7;
        }

        .feedback-author {
            font-size: 0.875rem;
            color: #86868b;
        }

        .btn-detail {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #e5e5e7;
            background: white;
            color: #1d1d1f;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e5e7;
        }

        .empty-state i {
            font-size: 4rem;
            color: #e5e5e7;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-comments me-2"></i> Feedback Guru
            </span>
            <a href="pilih_guru_feedback.php" class="btn-add">
                <i class="fas fa-plus"></i>Tambah Feedback
            </a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Manajemen Feedback Guru</h1>
            <p class="page-subtitle">Kelola catatan, pujian, dan peringatan untuk guru</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Feedback</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value text-warning"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pujian</div>
                <div class="stat-value text-success"><?php echo $stats['pujian']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Peringatan</div>
                <div class="stat-value text-danger"><?php echo $stats['peringatan']; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="">Semua Kategori</option>
                            <option value="pujian" <?php echo $filter_kategori === 'pujian' ? 'selected' : ''; ?>>Pujian</option>
                            <option value="peringatan" <?php echo $filter_kategori === 'peringatan' ? 'selected' : ''; ?>>Peringatan</option>
                            <option value="saran" <?php echo $filter_kategori === 'saran' ? 'selected' : ''; ?>>Saran</option>
                            <option value="lainnya" <?php echo $filter_kategori === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="selesai" <?php echo $filter_status === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
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

        <!-- Feedback List -->
        <?php if (mysqli_num_rows($result_feedback) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Belum Ada Feedback</h3>
                <p>Belum ada catatan feedback untuk guru</p>
                <a href="pilih_guru_feedback.php" class="btn-add mt-3">
                    <i class="fas fa-plus"></i>Tambah Feedback Pertama
                </a>
            </div>
        <?php else: ?>
            <?php while ($feedback = mysqli_fetch_assoc($result_feedback)): ?>
                <div class="feedback-card">
                    <div class="feedback-header">
                        <div class="feedback-guru-info">
                            <div class="guru-name"><?php echo htmlspecialchars($feedback['nama_guru']); ?></div>
                            <div class="guru-meta">
                                NIP: <?php echo htmlspecialchars($feedback['nip']); ?> • 
                                <?php echo htmlspecialchars($feedback['mata_pelajaran']); ?>
                            </div>
                        </div>
                        <div class="feedback-meta">
                            <span class="feedback-date">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d M Y', strtotime($feedback['tanggal'])); ?>
                            </span>
                            <span class="badge-kategori badge-<?php echo $feedback['kategori']; ?>">
                                <?php echo ucfirst($feedback['kategori']); ?>
                            </span>
                            <span class="badge-status badge-<?php echo $feedback['status']; ?>">
                                <?php echo ucfirst($feedback['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="feedback-content">
                        <div class="feedback-label">Catatan</div>
                        <div class="feedback-text"><?php echo nl2br(htmlspecialchars($feedback['catatan'])); ?></div>

                        <?php if (!empty($feedback['tindak_lanjut'])): ?>
                            <div class="feedback-tindaklanjut">
                                <div class="feedback-label">Tindak Lanjut</div>
                                <div class="feedback-text"><?php echo nl2br(htmlspecialchars($feedback['tindak_lanjut'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="feedback-footer">
                        <div class="feedback-author">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($feedback['nama_ketua'] ?? 'Unknown'); ?>
                        </div>
                        <a href="detail_kinerja_guru.php?id=<?php echo $feedback['guru_id']; ?>" class="btn-detail">
                            <i class="fas fa-eye me-1"></i>Lihat Kinerja Guru
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>