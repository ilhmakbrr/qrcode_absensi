<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}

// Koneksi ke database
include '../db.php';

// Cek apakah koneksi berhasil
if (!$conn) {
    die("Error: Tidak dapat terhubung ke database. Silakan coba lagi nanti.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_guru':
                    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $mata_pelajaran = mysqli_real_escape_string($conn, $_POST['mata_pelajaran']);
                    
                    $check_query = "SELECT * FROM guru WHERE nip = '$nip' OR email = '$email'";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("NIP atau Email sudah terdaftar!");
                    }
                    
                    $query = "INSERT INTO guru (nip, nama, email, password, mata_pelajaran) VALUES ('$nip', '$nama', '$email', '$password', '$mata_pelajaran')";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data guru berhasil ditambahkan!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'update_guru':
                    $id = (int)$_POST['id'];
                    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
                    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $mata_pelajaran = mysqli_real_escape_string($conn, $_POST['mata_pelajaran']);
                    
                    $check_query = "SELECT * FROM guru WHERE (nip = '$nip' OR email = '$email') AND id != $id";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("NIP atau Email sudah terdaftar!");
                    }
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $query = "UPDATE guru SET nip = '$nip', nama = '$nama', email = '$email', password = '$password', mata_pelajaran = '$mata_pelajaran' WHERE id = $id";
                    } else {
                        $query = "UPDATE guru SET nip = '$nip', nama = '$nama', email = '$email', mata_pelajaran = '$mata_pelajaran' WHERE id = $id";
                    }
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data guru berhasil diupdate!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'delete_guru':
                    $id = (int)$_POST['id'];
                    $query = "DELETE FROM guru WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data guru berhasil dihapus!";
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

try {
    $query_guru = "SELECT COUNT(*) AS total FROM guru";
    $result_guru = mysqli_query($conn, $query_guru);
    
    if (!$result_guru) {
        throw new Exception("Query guru gagal: " . mysqli_error($conn));
    }
    
    $total_teachers = mysqli_fetch_assoc($result_guru)['total'];

} catch (Exception $e) {
    error_log("Database error in manage_guru.php: " . $e->getMessage());
    die("Error: Terjadi masalah dengan database. Silakan hubungi administrator.");
}

$query_teachers = "SELECT * FROM guru ORDER BY nama ASC";
$result_teachers = mysqli_query($conn, $query_teachers);

if (!$result_teachers) {
    die("Query gagal: " . mysqli_error($conn));
}

$teachers = [];
while ($row = mysqli_fetch_assoc($result_teachers)) {
    $teachers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Guru - Admin Panel</title>
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8f9fa;
        }

        .main-content {
            margin: 20px;
            flex: 1;
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

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #6b7280;
        }

        .stats-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: black;
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

        .table thead th[width="50"] {
            text-align: center;
        }

        .table tbody td:first-child {
            text-align: center;
            font-weight: 600;
            color: #6b7280;
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

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f9fafb;
            color: #374151;
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-close {
            background-color: transparent;
            border: none;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
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
            <h1 class="page-title">Manajemen Data Guru</h1>
            <p class="page-subtitle">Kelola data guru dan tenaga pendidik</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stats-number"><?php echo number_format($total_teachers); ?></div>
            <div class="stats-label">Total Guru Terdaftar</div>
        </div>

        <div class="management-card">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-list"></i>
                    Daftar Guru
                </h3>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus"></i> Tambah Guru
                </button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Mata Pelajaran</th>
                            <th>Status</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($teachers as $teacher): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($teacher['nip']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['nama']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['mata_pelajaran']); ?></td>
                            <td><span class="badge badge-success">Aktif</span></td>
                            <td>
                                <button class="btn btn-action btn-edit" onclick='editTeacher(<?php echo htmlspecialchars(json_encode($teacher)); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-action btn-delete" onclick="deleteTeacher(<?php echo $teacher['id']; ?>)">
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

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chalkboard-teacher"></i> Tambah Guru Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_guru">
                        <div class="mb-3">
                            <label class="form-label">NIP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nip" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mata_pelajaran" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Simpan Guru
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Edit Data Guru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editTeacherForm">
                        <input type="hidden" name="action" value="update_guru">
                        <input type="hidden" name="id" id="edit_teacher_id">
                        <div class="mb-3">
                            <label class="form-label">NIP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nip" id="edit_teacher_nip" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" id="edit_teacher_nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="edit_teacher_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" id="edit_teacher_password" 
                                   placeholder="Kosongkan jika tidak ingin mengubah">
                            <small class="text-muted">* Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mata_pelajaran" id="edit_teacher_mata_pelajaran" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Update Guru
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data guru ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_guru">
                        <input type="hidden" name="id" id="delete_id">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function editTeacher(data) {
        document.getElementById('edit_teacher_id').value = data.id;
        document.getElementById('edit_teacher_nip').value = data.nip;
        document.getElementById('edit_teacher_nama').value = data.nama;
        document.getElementById('edit_teacher_email').value = data.email;
        document.getElementById('edit_teacher_mata_pelajaran').value = data.mata_pelajaran;
        
        const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
        editModal.show();
    }

    function deleteTeacher(id) {
        document.getElementById('delete_id').value = id;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }

    // Auto close alert setelah 5 detik
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 5000);
        });
    });
    </script>
</body>
</html>