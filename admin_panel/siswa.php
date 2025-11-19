<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}

include '../db.php';

if (!$conn) {
    die("Error: Tidak dapat terhubung ke database.");
}

// Folder untuk menyimpan foto
$upload_dir = '../uploads/siswa/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_siswa':
                    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
                    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $jurusan = mysqli_real_escape_string($conn, $_POST['jurusan']);
                    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                    
                    $check_query = "SELECT * FROM siswa WHERE nis = '$nis' OR email = '$email'";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("NIS atau Email sudah terdaftar!");
                    }
                    
                    // Handle upload foto
                    $foto_name = null;
                    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        $max_size = 2 * 1024 * 1024; // 2MB
                        
                        if (!in_array($_FILES['foto']['type'], $allowed_types)) {
                            throw new Exception("Format foto harus JPG, JPEG, atau PNG!");
                        }
                        
                        if ($_FILES['foto']['size'] > $max_size) {
                            throw new Exception("Ukuran foto maksimal 2MB!");
                        }
                        
                        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $foto_name = 'siswa_' . time() . '_' . uniqid() . '.' . $extension;
                        $foto_path = $upload_dir . $foto_name;
                        
                        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                            throw new Exception("Gagal mengupload foto!");
                        }
                    }
                    
                    $query = "INSERT INTO siswa (nis, nama, tanggal_lahir, jenis_kelamin, email, password, foto, kelas, jurusan, alamat) 
                              VALUES ('$nis', '$nama', '$tanggal_lahir', '$jenis_kelamin', '$email', '$password', " . 
                              ($foto_name ? "'$foto_name'" : "NULL") . ", '$kelas', '$jurusan', '$alamat')";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data siswa berhasil ditambahkan!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'update_siswa':
                    $id = (int)$_POST['id'];
                    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
                    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $jurusan = mysqli_real_escape_string($conn, $_POST['jurusan']);
                    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                    
                    $check_query = "SELECT * FROM siswa WHERE (nis = '$nis' OR email = '$email') AND id != $id";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("NIS atau Email sudah terdaftar!");
                    }
                    
                    // Ambil data foto lama
                    $old_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM siswa WHERE id = $id"));
                    $foto_name = $old_data['foto'];
                    
                    // Handle upload foto baru
                    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        $max_size = 2 * 1024 * 1024; // 2MB
                        
                        if (!in_array($_FILES['foto']['type'], $allowed_types)) {
                            throw new Exception("Format foto harus JPG, JPEG, atau PNG!");
                        }
                        
                        if ($_FILES['foto']['size'] > $max_size) {
                            throw new Exception("Ukuran foto maksimal 2MB!");
                        }
                        
                        // Hapus foto lama jika ada
                        if ($foto_name && file_exists($upload_dir . $foto_name)) {
                            unlink($upload_dir . $foto_name);
                        }
                        
                        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $foto_name = 'siswa_' . time() . '_' . uniqid() . '.' . $extension;
                        $foto_path = $upload_dir . $foto_name;
                        
                        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                            throw new Exception("Gagal mengupload foto!");
                        }
                    }
                    
                    // Build query
                    $query = "UPDATE siswa SET 
                              nis = '$nis', 
                              nama = '$nama', 
                              tanggal_lahir = '$tanggal_lahir', 
                              jenis_kelamin = '$jenis_kelamin', 
                              email = '$email', 
                              kelas = '$kelas', 
                              jurusan = '$jurusan', 
                              alamat = '$alamat'";
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $query .= ", password = '$password'";
                    }
                    
                    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                        $query .= ", foto = '$foto_name'";
                    }
                    
                    $query .= " WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data siswa berhasil diupdate!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'delete_siswa':
                    $id = (int)$_POST['id'];
                    
                    // Ambil data foto untuk dihapus
                    $data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM siswa WHERE id = $id"));
                    
                    $query = "DELETE FROM siswa WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        // Hapus file foto jika ada
                        if ($data['foto'] && file_exists($upload_dir . $data['foto'])) {
                            unlink($upload_dir . $data['foto']);
                        }
                        $success = "Data siswa berhasil dihapus!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
            }
        }
        
        if (isset($success)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
            exit();
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

$query_siswa = "SELECT COUNT(*) AS total FROM siswa";
$total_students = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa))['total'];

$query_students = "SELECT * FROM siswa ORDER BY nama ASC";
$result_students = mysqli_query($conn, $query_students);
$students = [];
while ($row = mysqli_fetch_assoc($result_students)) {
    $students[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image" href="../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }

        .main-content {
            margin: 20px;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: #6b7280;
        }

        .stats-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .stats-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .management-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            border: 1px solid #e5e7eb;
        }

        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .card-title-custom {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-add:hover {
            background: #2c3e50;
            color: white;
            transform: translateY(-2px);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }

        .table thead th {
            background: #f9fafb;
            color: #374151;
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 0.875rem;
            color: #374151;
        }

        .table tbody tr:hover {
            background: #f9fafb;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }

        .profile-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-action {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            transition: all 0.2s ease;
            margin: 0 0.125rem;
            font-size: 0.875rem;
        }

        .btn-edit {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            background: #f9fafb;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.125rem;
            color: #111827;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .foto-preview-container {
            margin-top: 10px;
            text-align: center;
        }

        .foto-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            display: none;
        }

        .current-foto {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 0.625rem;
            background: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            background: #f3f4f6;
            border-color: #3498db;
        }

        .file-input-label i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .card-header-custom {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
</style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Manajemen Data Siswa</h1>
            <p class="page-subtitle">Kelola data siswa dan peserta didik</p>
        </div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stats-number"><?php echo number_format($total_students); ?></div>
            <div class="stats-label">Total Siswa Terdaftar</div>
        </div>
        <div class="management-card">
            <div class="card-header-custom">
                <h3 class="card-title-custom"><i class="fas fa-list"></i> Daftar Siswa</h3>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus"></i> Tambah Siswa
                </button>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Foto</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Jurusan</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($students as $student): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php if ($student['foto'] && file_exists($upload_dir . $student['foto'])): ?>
                                    <img src="<?php echo $upload_dir . $student['foto']; ?>" class="profile-img" alt="Foto">
                                <?php else: ?>
                                    <div class="profile-placeholder">
                                        <?php echo strtoupper(substr($student['nama'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['nis']); ?></td>
                            <td><?php echo htmlspecialchars($student['nama']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['jurusan']); ?></td>
                            <td><?php echo htmlspecialchars($student['kelas']); ?></td>
                            <td><span class="badge badge-success">Aktif</span></td>
                            <td>
                                <button class="btn btn-action btn-edit" onclick='editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-action btn-delete" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-graduate"></i> Tambah Siswa Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_siswa">
                        
                        <div class="mb-3">
                            <label class="form-label">Foto Profil</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="foto" id="foto_add" accept="image/jpeg,image/jpg,image/png" onchange="previewImage(this, 'preview_add')">
                                <label for="foto_add" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Pilih Foto (JPG, JPEG, PNG - Max 2MB)</span>
                                </label>
                            </div>
                            <div class="foto-preview-container">
                                <img id="preview_add" class="foto-preview" alt="Preview">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nis" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal_lahir" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_kelamin" required>
                                    <option value="">Pilih</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                                <select class="form-select" name="jurusan" id="add_jurusan" required>
                                    <option value="">Pilih Jurusan</option>
                                    <option value="IPA">IPA</option>
                                    <option value="IPS">IPS</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <select class="form-select" name="kelas" id="add_kelas" required disabled>
                                    <option value="">Pilih Kelas</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat" rows="3" required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_siswa">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Foto Profil</label>
                            <div class="text-center mb-2" id="current_foto_container">
                                <img id="current_foto" class="current-foto" alt="Foto Saat Ini">
                            </div>
                            <div class="file-input-wrapper">
                                <input type="file" name="foto" id="foto_edit" accept="image/jpeg,image/jpg,image/png" onchange="previewImage(this, 'preview_edit')">
                                <label for="foto_edit" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Ganti Foto (JPG, JPEG, PNG - Max 2MB)</span>
                                </label>
                            </div>
                            <div class="foto-preview-container">
                                <img id="preview_edit" class="foto-preview" alt="Preview">
                            </div>
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah foto</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nis" id="edit_nis" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" id="edit_nama" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal_lahir" id="edit_tanggal_lahir" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_kelamin" id="edit_jenis_kelamin" required>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                                <select class="form-select" name="jurusan" id="edit_jurusan" required>
                                    <option value="IPA">IPA</option>
                                    <option value="IPS">IPS</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <select class="form-select" name="kelas" id="edit_kelas" required>
                                    <option value="">Pilih Kelas</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat" id="edit_alamat" rows="3" required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add"><i class="fas fa-save"></i> Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data siswa ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_siswa">
                        <input type="hidden" name="id" id="delete_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Fungsi untuk preview gambar
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            // Validasi ukuran file
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB!');
                input.value = '';
                preview.style.display = 'none';
                return;
            }
            
            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format file harus JPG, JPEG, atau PNG!');
                input.value = '';
                preview.style.display = 'none';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }

    const kelasData = {
        'IPA': ['X IPA 1','X IPA 2','XI IPA 1','XI IPA 2','XII IPA 1','XII IPA 2'],
        'IPS': ['X IPS 1','X IPS 2','XI IPS 1','XI IPS 2','XII IPS 1','XII IPS 2']
    };
    
    // Populate kelas untuk Add form
    document.getElementById('add_jurusan').addEventListener('change', function() {
        const kelas = document.getElementById('add_kelas');
        kelas.innerHTML = '<option value="">Pilih Kelas</option>';
        if (this.value) {
            kelas.disabled = false;
            kelasData[this.value].forEach(k => {
                kelas.innerHTML += `<option value="${k}">${k}</option>`;
            });
        } else {
            kelas.disabled = true;
        }
    });
    
    // Populate kelas untuk Edit form
    document.getElementById('edit_jurusan').addEventListener('change', function() {
        const kelas = document.getElementById('edit_kelas');
        const currentKelas = kelas.getAttribute('data-current-value');
        kelas.innerHTML = '<option value="">Pilih Kelas</option>';
        if (this.value) {
            kelas.disabled = false;
            kelasData[this.value].forEach(k => {
                const selected = k === currentKelas ? 'selected' : '';
                kelas.innerHTML += `<option value="${k}" ${selected}>${k}</option>`;
            });
        } else {
            kelas.disabled = true;
        }
    });
    
    function editStudent(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nis').value = data.nis;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_tanggal_lahir').value = data.tanggal_lahir;
        document.getElementById('edit_jenis_kelamin').value = data.jenis_kelamin;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_jurusan').value = data.jurusan;
        document.getElementById('edit_alamat').value = data.alamat;
        
        // Tampilkan foto saat ini jika ada
        const currentFoto = document.getElementById('current_foto');
        const currentFotoContainer = document.getElementById('current_foto_container');
        
        if (data.foto) {
            currentFoto.src = '../uploads/siswa/' + data.foto;
            currentFotoContainer.style.display = 'block';
        } else {
            currentFotoContainer.style.display = 'none';
        }
        
        // Reset preview edit
        document.getElementById('preview_edit').style.display = 'none';
        document.getElementById('foto_edit').value = '';
        
        // Set current kelas value untuk dipilih setelah populate
        const kelasSelect = document.getElementById('edit_kelas');
        kelasSelect.setAttribute('data-current-value', data.kelas);
        
        // Trigger change event untuk populate kelas
        document.getElementById('edit_jurusan').dispatchEvent(new Event('change'));
        
        // Show modal
        const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
        editModal.show();
    }
    
    function deleteStudent(id) {
        document.getElementById('delete_id').value = id;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    // Reset form dan preview saat modal ditutup
    document.getElementById('addStudentModal').addEventListener('hidden.bs.modal', function () {
        document.querySelector('#addStudentModal form').reset();
        document.getElementById('preview_add').style.display = 'none';
        document.getElementById('add_kelas').disabled = true;
    });
    
    document.getElementById('editStudentModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('preview_edit').style.display = 'none';
    });
    
    // Auto close alert setelah 5 detik
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
    </script>
</body>
</html>