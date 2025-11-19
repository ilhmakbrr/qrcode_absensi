<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ketua_yayasan') {
    header("Location: login_yayasan.php?error=unauthorized");
    exit();
}

include '../../db.php';

// Get semua guru
$query_guru = "SELECT * FROM guru ORDER BY nama ASC";
$result_guru = mysqli_query($conn, $query_guru);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Guru - EduAttend</title>
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

        .main-content {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #1d1d1f;
        }

        .guru-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .guru-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .guru-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #2c3e50;
        }

        .guru-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .guru-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .guru-details h5 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .guru-details p {
            font-size: 0.875rem;
            color: #86868b;
            margin: 0;
        }

        .btn-select {
            width: 100%;
            padding: 0.75rem;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-select:hover {
            background: #1a252f;
            color: white;
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a href="daftar_feedback_guru.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Pilih Guru untuk Feedback</h1>
            <p class="text-muted">Pilih guru yang ingin Anda berikan feedback</p>
        </div>

        <div class="guru-grid">
            <?php while ($guru = mysqli_fetch_assoc($result_guru)): ?>
                <div class="guru-card">
                    <div class="guru-info">
                        <div class="guru-avatar">
                            <?php echo strtoupper(substr($guru['nama'], 0, 2)); ?>
                        </div>
                        <div class="guru-details">
                            <h5><?php echo htmlspecialchars($guru['nama']); ?></h5>
                            <p>NIP: <?php echo htmlspecialchars($guru['nip']); ?></p>
                            <p><?php echo htmlspecialchars($guru['mata_pelajaran']); ?></p>
                        </div>
                    </div>
                    <a href="tambah_feedback_guru.php?guru_id=<?php echo $guru['id']; ?>" class="btn-select">
                        <i class="fas fa-plus me-2"></i>Pilih Guru
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>