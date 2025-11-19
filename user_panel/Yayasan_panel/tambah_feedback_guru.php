<?php
require_once '../auth_user/auth_check_yayasan.php';

// Koneksi database
include '../../db.php';
include 'func_kinerja.php';

$ketua_id = $_SESSION['user_id'];
$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);

$guru_id = isset($_GET['guru_id']) ? intval($_GET['guru_id']) : 0;

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

// Proses tambah feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ketua_id = $_SESSION['user_id'];
    $tanggal = $_POST['tanggal'];
    $kategori = $_POST['kategori'];
    $catatan = $_POST['catatan'];
    $tindak_lanjut = $_POST['tindak_lanjut'];
    $status = $_POST['status'];
    
    $query = "INSERT INTO feedback_guru (guru_id, ketua_yayasan_id, tanggal, kategori, catatan, tindak_lanjut, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iisssss", $guru_id, $ketua_id, $tanggal, $kategori, $catatan, $tindak_lanjut, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: detail_kinerja_guru.php?id=$guru_id&success=feedback_added");
        exit();
    } else {
        $error = "Gagal menambahkan feedback";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Feedback Guru</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 2rem;
        }

        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f5f5f7;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #86868b;
            font-size: 0.9375rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid #e5e5e7;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.9375rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1d1d1f;
            box-shadow: 0 0 0 3px rgba(29, 29, 31, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-primary {
            background: #1d1d1f;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #424245;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #1d1d1f;
            border: 1px solid #e5e5e7;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: #f5f5f7;
        }

        .guru-info-box {
            background: #f5f5f7;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .guru-info-box h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .guru-info-box p {
            font-size: 0.875rem;
            color: #86868b;
            margin: 0;
        }

        .alert {
            border-radius: 12px;
            border: none;
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a href="detail_kinerja_guru.php?id=<?php echo $guru_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">Tambah Feedback Guru</h1>
                <p class="form-subtitle">Berikan catatan, pujian, atau peringatan kepada guru</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="guru-info-box">
                <h5><?php echo htmlspecialchars($guru['nama']); ?></h5>
                <p>NIP: <?php echo htmlspecialchars($guru['nip']); ?> • <?php echo htmlspecialchars($guru['mata_pelajaran']); ?></p>
            </div>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select" name="kategori" required>
                            <option value="pujian">Pujian</option>
                            <option value="peringatan">Peringatan</option>
                            <option value="saran">Saran</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Catatan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="catatan" placeholder="Tulis catatan feedback untuk guru..." required></textarea>
                        <small class="text-muted">Jelaskan feedback secara detail dan konstruktif</small>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Tindak Lanjut</label>
                        <textarea class="form-control" name="tindak_lanjut" placeholder="Rencana tindak lanjut (opsional)"></textarea>
                        <small class="text-muted">Apa yang perlu dilakukan guru berdasarkan feedback ini?</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <a href="detail_kinerja_guru.php?id=<?php echo $guru_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>