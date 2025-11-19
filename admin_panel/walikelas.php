<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_wali_kelas':
                    $guru_id = (int)$_POST['guru_id'];
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
                    
                    $check_query = "SELECT * FROM wali_kelas WHERE kelas = '$kelas' AND tahun_ajaran = '$tahun_ajaran'";
                    if (mysqli_num_rows(mysqli_query($conn, $check_query)) > 0) {
                        throw new Exception("Kelas ini sudah memiliki wali kelas untuk tahun ajaran tersebut!");
                    }
                    
                    $query = "INSERT INTO wali_kelas (guru_id, kelas, tahun_ajaran) VALUES ($guru_id, '$kelas', '$tahun_ajaran')";
                    if (mysqli_query($conn, $query)) {
                        $success = "Wali kelas berhasil ditambahkan!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'update_wali_kelas':
                    $id = (int)$_POST['id'];
                    $guru_id = (int)$_POST['guru_id'];
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
                    
                    // Cek apakah kelas sudah digunakan oleh wali kelas lain
                    $check_query = "SELECT * FROM wali_kelas WHERE kelas = '$kelas' AND tahun_ajaran = '$tahun_ajaran' AND id != $id";
                    if (mysqli_num_rows(mysqli_query($conn, $check_query)) > 0) {
                        throw new Exception("Kelas ini sudah memiliki wali kelas lain untuk tahun ajaran tersebut!");
                    }
                    
                    $query = "UPDATE wali_kelas SET guru_id = $guru_id, kelas = '$kelas', tahun_ajaran = '$tahun_ajaran' WHERE id = $id";
                    if (mysqli_query($conn, $query)) {
                        $success = "Data wali kelas berhasil diupdate!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
                    
                case 'delete_wali_kelas':
                    $id = (int)$_POST['id'];
                    if (mysqli_query($conn, "DELETE FROM wali_kelas WHERE id = $id")) {
                        $success = "Data wali kelas berhasil dihapus!";
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

if (isset($_GET['success'])) $success = $_GET['success'];

$total_wali = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM wali_kelas"))['total'];
$query_wali = "SELECT wk.*, g.nip, g.nama, g.email FROM wali_kelas wk JOIN guru g ON wk.guru_id = g.id ORDER BY wk.kelas ASC";
$wali_kelas = [];
$result = mysqli_query($conn, $query_wali);
while ($row = mysqli_fetch_assoc($result)) $wali_kelas[] = $row;

$query_guru = "SELECT * FROM guru ORDER BY nama ASC";
$teachers = [];
$result = mysqli_query($conn, $query_guru);
while ($row = mysqli_fetch_assoc($result)) $teachers[] = $row;

$kelas_list = ['X IPA 1','X IPA 2','X IPS 1','X IPS 2','XI IPA 1','XI IPA 2','XI IPS 1','XI IPS 2','XII IPA 1','XII IPA 2','XII IPS 1','XII IPS 2'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Wali Kelas - Admin Panel</title>
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
            background: #f8f9fa; }
        .main-content {
            margin: 20px; 
            padding: 30px;
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
             }
        .page-header { 
            margin-bottom: 2rem; 
        }
        .page-title { 
            font-size: 1.75rem;
            font-weight: 700; 
            color: #111827; 
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
            background: rgba(255,255,255,0.2); 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 2rem; 
            margin-bottom: 1rem; }
        .stats-number { 
            font-size: 3rem; 
            font-weight: 800; 
        }
        .stats-label { 
            font-size: 1.125rem; 
            opacity: 0.9; 
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
            align-items: center; 
            margin-bottom: 1.5rem; 
            padding-bottom: 1rem; 
            border-bottom: 2px solid #f3f4f6; 
        }
        .card-title-custom { 
            font-size: 1.25rem; 
            font-weight: 700; 
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
            padding: 1rem; 
            font-weight: 600; 
            font-size: 0.8125rem; 
            text-transform: uppercase; 
            color: #374151; 
            border: none; 
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
        .table tbody td:first-child { 
            text-align: center; 
            font-weight: 600; 
            color: #6b7280; 
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
        .badge-warning { 
            background: #fef3c7; 
            color: #92400e;
         }
        .btn-action { 
            padding: 0.5rem 0.75rem; 
            border-radius: 6px; 
            border: none; 
            margin: 0 0.125rem; 
            transition: all 0.2s ease; 
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
            padding: 0.625rem; 
            border: 1px solid #d1d5db; 
            transition: all 0.2s ease; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #8b5cf6; 
            box-shadow: 0 0 0 3px rgba(139,92,246,0.1); 
        }
        .form-label { 
            font-weight: 600; 
            color: #374151; 
            margin-bottom: 0.5rem; 
            font-size: 0.875rem; 
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
            <h1 class="page-title">Manajemen Data Wali Kelas</h1>
            <p class="page-subtitle">Kelola penugasan wali kelas untuk setiap kelas</p>
        </div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stats-number"><?php echo number_format($total_wali); ?></div>
            <div class="stats-label">Total Wali Kelas</div>
        </div>
        <div class="management-card">
            <div class="card-header-custom">
                <h3 class="card-title-custom"><i class="fas fa-list"></i> Daftar Wali Kelas</h3>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Wali Kelas
                </button>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">No</th><th>NIP</th><th>Nama Guru</th><th>Email</th><th>Kelas</th><th>Tahun Ajaran</th><th>Status</th><th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($wali_kelas as $wk): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($wk['nip']); ?></td>
                            <td><?php echo htmlspecialchars($wk['nama']); ?></td>
                            <td><?php echo htmlspecialchars($wk['email']); ?></td>
                            <td><span class="badge badge-warning"><?php echo htmlspecialchars($wk['kelas']); ?></span></td>
                            <td><?php echo htmlspecialchars($wk['tahun_ajaran']); ?></td>
                            <td><span class="badge badge-success">Aktif</span></td>
                            <td>
                                <button class="btn btn-action btn-edit" onclick='editWaliKelas(<?php echo htmlspecialchars(json_encode($wk)); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-action btn-delete" onclick="deleteWaliKelas(<?php echo $wk['id']; ?>)">
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
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-tie"></i> Tambah Wali Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_wali_kelas">
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                            <select class="form-select" name="guru_id" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nama']); ?> (<?php echo htmlspecialchars($t['nip']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $k): ?>
                                <option value="<?php echo $k; ?>"><?php echo $k; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="tahun_ajaran" value="<?php echo date('Y') . '/' . (date('Y')+1); ?>" placeholder="2024/2025" required>
                            <small class="text-muted">Format: YYYY/YYYY</small>
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
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Wali Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_wali_kelas">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                            <select class="form-select" name="guru_id" id="edit_guru_id" required>
                                <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nama']); ?> (<?php echo htmlspecialchars($t['nip']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas" id="edit_kelas" required>
                                <?php foreach ($kelas_list as $k): ?>
                                <option value="<?php echo $k; ?>"><?php echo $k; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="tahun_ajaran" id="edit_tahun" required>
                            <small class="text-muted">Format: YYYY/YYYY</small>
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
                    <p>Apakah Anda yakin ingin menghapus data wali kelas ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_wali_kelas">
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
    function editWaliKelas(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_guru_id').value = data.guru_id;
        document.getElementById('edit_kelas').value = data.kelas;
        document.getElementById('edit_tahun').value = data.tahun_ajaran;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    
    function deleteWaliKelas(id) {
        document.getElementById('delete_id').value = id;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
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