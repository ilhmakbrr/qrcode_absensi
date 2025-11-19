<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}
include '../db.php';

// Folder untuk menyimpan foto
$upload_dir = '../uploads/ketua_yayasan/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_ketua_yayasan':
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
                    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    
                    if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ketua_yayasan WHERE email = '$email'")) > 0) {
                        throw new Exception("Email sudah terdaftar!");
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
                        $foto_name = 'ketua_' . time() . '_' . uniqid() . '.' . $extension;
                        $foto_path = $upload_dir . $foto_name;
                        
                        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                            throw new Exception("Gagal mengupload foto!");
                        }
                    }
                    
                    $query = "INSERT INTO ketua_yayasan (nama, tanggal_lahir, alamat, email, password, foto) 
                              VALUES ('$nama', '$tanggal_lahir', '$alamat', '$email', '$password', " . 
                              ($foto_name ? "'$foto_name'" : "NULL") . ")";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data Ketua Yayasan berhasil ditambahkan!";
                    }
                    break;
                    
                case 'update_ketua_yayasan':
                    $id = (int)$_POST['id'];
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
                    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    
                    // Cek apakah email sudah digunakan oleh ketua yayasan lain
                    $check_email = mysqli_query($conn, "SELECT id FROM ketua_yayasan WHERE email = '$email' AND id != $id");
                    if (mysqli_num_rows($check_email) > 0) {
                        throw new Exception("Email sudah digunakan oleh ketua yayasan lain!");
                    }
                    
                    // Ambil data foto lama
                    $old_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM ketua_yayasan WHERE id = $id"));
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
                        $foto_name = 'ketua_' . time() . '_' . uniqid() . '.' . $extension;
                        $foto_path = $upload_dir . $foto_name;
                        
                        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                            throw new Exception("Gagal mengupload foto!");
                        }
                    }
                    
                    // Build query
                    $query = "UPDATE ketua_yayasan SET 
                              nama = '$nama', 
                              tanggal_lahir = '$tanggal_lahir', 
                              alamat = '$alamat', 
                              email = '$email'";
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $query .= ", password = '$password'";
                    }
                    
                    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                        $query .= ", foto = '$foto_name'";
                    }
                    
                    $query .= " WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data Ketua Yayasan berhasil diupdate!";
                    } else {
                        throw new Exception("Gagal mengupdate data: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'delete_ketua_yayasan':
                    $id = (int)$_POST['id'];
                    
                    // Ambil data foto untuk dihapus
                    $data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM ketua_yayasan WHERE id = $id"));
                    
                    if (mysqli_query($conn, "DELETE FROM ketua_yayasan WHERE id = $id")) {
                        // Hapus file foto jika ada
                        if ($data['foto'] && file_exists($upload_dir . $data['foto'])) {
                            unlink($upload_dir . $data['foto']);
                        }
                        $success = "Data Ketua Yayasan berhasil dihapus!";
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

if (isset($_GET['success'])) $success = $_GET['success'];

$total_ketua = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM ketua_yayasan"))['total'];
$ketua_list = [];
$result = mysqli_query($conn, "SELECT * FROM ketua_yayasan ORDER BY nama ASC");
while ($row = mysqli_fetch_assoc($result)) $ketua_list[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Ketua Yayasan - Admin Panel</title>
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
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
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
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 2rem;
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
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .card-title-custom {
            font-size: 1.25rem;
            font-weight: 700;
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

        .table thead th {
            background: #f9fafb;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-action {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: none;
            margin: 0 0.125rem;
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
        }

        .modal-header {
            background: #f9fafb;
            padding: 1.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 0.625rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        /* Style untuk foto profil di tabel */
        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }

        .profile-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
        }

        /* Style untuk preview foto */
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
    </style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Manajemen Data Ketua Yayasan</h1>
        </div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-user-shield"></i></div>
            <div class="stats-number"><?php echo number_format($total_ketua); ?></div>
            <div class="stats-label">Total Ketua Yayasan</div>
        </div>
        <div class="management-card">
            <div class="card-header-custom">
                <h3 class="card-title-custom"><i class="fas fa-list"></i> Daftar Ketua Yayasan</h3>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Ketua Yayasan
                </button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Tanggal Lahir</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($ketua_list as $k): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php if ($k['foto'] && file_exists($upload_dir . $k['foto'])): ?>
                                    <img src="<?php echo $upload_dir . $k['foto']; ?>" class="profile-img" alt="Foto">
                                <?php else: ?>
                                    <div class="profile-placeholder">
                                        <?php echo strtoupper(substr($k['nama'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($k['nama']); ?></td>
                            <td><?php echo htmlspecialchars($k['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($k['tanggal_lahir'])); ?></td>
                            <td><?php echo substr(htmlspecialchars($k['alamat']), 0, 50) . (strlen($k['alamat']) > 50 ? '...' : ''); ?></td>
                            <td><span class="badge badge-success">Aktif</span></td>
                            <td>
                                <button class="btn btn-action btn-edit" onclick="editData(<?php echo htmlspecialchars(json_encode($k)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-action btn-delete" onclick="deleteData(<?php echo $k['id']; ?>)">
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

    <!-- Modal Tambah -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-shield"></i> Tambah Ketua Yayasan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_ketua_yayasan">
                        
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

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Lahir *</label>
                            <input type="date" class="form-control" name="tanggal_lahir" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat *</label>
                            <textarea class="form-control" name="alamat" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-add w-100"><i class="fas fa-save"></i> Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Ketua Yayasan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_ketua_yayasan">
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

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Lahir *</label>
                            <input type="date" class="form-control" name="tanggal_lahir" id="edit_tanggal" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat *</label>
                            <textarea class="form-control" name="alamat" id="edit_alamat" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-add w-100"><i class="fas fa-save"></i> Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data ketua yayasan ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_ketua_yayasan">
                        <input type="hidden" name="id" id="delete_id">
                        <div class="d-flex gap-2 justify-content-end">
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
    
    // Fungsi untuk edit data
    function editData(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_tanggal').value = data.tanggal_lahir;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_alamat').value = data.alamat;
        
        // Tampilkan foto saat ini jika ada
        const currentFoto = document.getElementById('current_foto');
        const currentFotoContainer = document.getElementById('current_foto_container');
        
        if (data.foto) {
            currentFoto.src = '../uploads/ketua_yayasan/' + data.foto;
            currentFotoContainer.style.display = 'block';
        } else {
            currentFotoContainer.style.display = 'none';
        }
        
        // Reset preview edit
        document.getElementById('preview_edit').style.display = 'none';
        document.getElementById('foto_edit').value = '';
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    
    // Fungsi untuk delete data
    function deleteData(id) {
        document.getElementById('delete_id').value = id;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    // Reset form dan preview saat modal ditutup
    document.getElementById('addModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('addForm').reset();
        document.getElementById('preview_add').style.display = 'none';
    });
    
    document.getElementById('editModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('editForm').reset();
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