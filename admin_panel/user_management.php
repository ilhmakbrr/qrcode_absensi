/*
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

/*
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
                    
                    $query = "INSERT INTO siswa (nis, nama, tanggal_lahir, jenis_kelamin, email, password, kelas, jurusan, alamat) 
                              VALUES ('$nis', '$nama', '$tanggal_lahir', '$jenis_kelamin', '$email', '$password', '$kelas', '$jurusan', '$alamat')";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data siswa berhasil ditambahkan!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;

                case 'add_wali_kelas':
                    $guru_id = (int)$_POST['guru_id'];
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
                    
                    $check_query = "SELECT * FROM wali_kelas WHERE kelas = '$kelas' AND tahun_ajaran = '$tahun_ajaran'";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("Kelas ini sudah memiliki wali kelas untuk tahun ajaran tersebut!");
                    }
                    
                    $check_guru_query = "SELECT * FROM wali_kelas WHERE guru_id = $guru_id AND tahun_ajaran = '$tahun_ajaran'";
                    $check_guru_result = mysqli_query($conn, $check_guru_query);
                    
                    if (mysqli_num_rows($check_guru_result) > 0) {
                        throw new Exception("Guru ini sudah menjadi wali kelas di kelas lain untuk tahun ajaran tersebut!");
                    }
                    
                    $query = "INSERT INTO wali_kelas (guru_id, kelas, tahun_ajaran) VALUES ($guru_id, '$kelas', '$tahun_ajaran')";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Wali kelas berhasil ditambahkan!";
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
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $query = "UPDATE siswa SET nis = '$nis', nama = '$nama', tanggal_lahir = '$tanggal_lahir', 
                                  jenis_kelamin = '$jenis_kelamin', email = '$email', password = '$password', 
                                  kelas = '$kelas', jurusan = '$jurusan', alamat = '$alamat' WHERE id = $id";
                    } else {
                        $query = "UPDATE siswa SET nis = '$nis', nama = '$nama', tanggal_lahir = '$tanggal_lahir', 
                                  jenis_kelamin = '$jenis_kelamin', email = '$email', kelas = '$kelas', 
                                  jurusan = '$jurusan', alamat = '$alamat' WHERE id = $id";
                    }
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data siswa berhasil diupdate!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;

                case 'update_wali_kelas':
                    $id = (int)$_POST['id'];
                    $guru_id = (int)$_POST['guru_id'];
                    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
                    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
                    
                    $check_query = "SELECT * FROM wali_kelas WHERE kelas = '$kelas' AND tahun_ajaran = '$tahun_ajaran' AND id != $id";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception("Kelas ini sudah memiliki wali kelas lain untuk tahun ajaran tersebut!");
                    }
                    
                    $query = "UPDATE wali_kelas SET guru_id = $guru_id, kelas = '$kelas', tahun_ajaran = '$tahun_ajaran' WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data wali kelas berhasil diupdate!";
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
                    
                case 'delete_siswa':
                    $id = (int)$_POST['id'];
                    $query = "DELETE FROM siswa WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data siswa berhasil dihapus!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;

                case 'delete_wali_kelas':
                    $id = (int)$_POST['id'];
                    $query = "DELETE FROM wali_kelas WHERE id = $id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Data wali kelas berhasil dihapus!";
                    } else {
                        throw new Exception("Error: " . mysqli_error($conn));
                    }
                    break;
            }
        }
        
        if ($success) {
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
    
    $query_siswa = "SELECT COUNT(*) AS total FROM siswa";
    $result_siswa = mysqli_query($conn, $query_siswa);
    
    if (!$result_siswa) {
        throw new Exception("Query siswa gagal: " . mysqli_error($conn));
    }
    
    $total_students = mysqli_fetch_assoc($result_siswa)['total'];
    
    $query_wali = "SELECT COUNT(*) AS total FROM wali_kelas";
    $result_wali = mysqli_query($conn, $query_wali);

    if (!$result_wali) {
        throw new Exception("Query wali kelas gagal: " . mysqli_error($conn));
    }

    $total_wali_kelas = mysqli_fetch_assoc($result_wali)['total'];

} catch (Exception $e) {
    error_log("Database error in user_management.php: " . $e->getMessage());
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

$query_students = "SELECT * FROM siswa ORDER BY nama ASC";
$result_students = mysqli_query($conn, $query_students);

if (!$result_students) {
    die("Query gagal: " . mysqli_error($conn));
}

$students = [];
while ($row = mysqli_fetch_assoc($result_students)) {
    $students[] = $row;
}

$query_wali_kelas = "SELECT wk.*, g.nip, g.nama, g.email 
                      FROM wali_kelas wk 
                      JOIN guru g ON wk.guru_id = g.id 
                      ORDER BY wk.kelas ASC";
$result_wali_kelas = mysqli_query($conn, $query_wali_kelas);

if (!$result_wali_kelas) {
    die("Query gagal: " . mysqli_error($conn));
}

$wali_kelas = [];
while ($row = mysqli_fetch_assoc($result_wali_kelas)) {
    $wali_kelas[] = $row;
}

$tahun_aktif = date('Y') . '/' . (date('Y') + 1);
$query_guru_available = "SELECT g.* FROM guru g 
                         WHERE g.id NOT IN (
                             SELECT guru_id FROM wali_kelas WHERE tahun_ajaran = '$tahun_aktif'
                         )
                         ORDER BY g.nama ASC";
$result_guru_available = mysqli_query($conn, $query_guru_available);

$guru_available = [];
while ($row = mysqli_fetch_assoc($result_guru_available)) {
    $guru_available[] = $row;
}

$kelas_list = ['X IPA 1', 'X IPA 2', 'X IPS 1', 'X IPS 2',
              'XI IPA 1', 'XI IPA 2', 'XI IPS 1', 'XI IPS 2',
              'XII IPA 1', 'XII IPA 2', 'XII IPS 1', 'XII IPS 2'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.blue { background: #3b82f6; }
        .stat-icon.green { background: #10b981; }
        .stat-icon.purple { background: #8b5cf6; }
        .stat-icon.orange { background: #f59e0b; }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
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

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
            gap: 0.5rem;
        }

        .nav-tabs .nav-link {
            background: white;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .nav-tabs .nav-link:hover {
            background: #f9fafb;
            color: #111827;
            border-color: #d1d5db;
        }

        .nav-tabs .nav-link.active {
            background: #1f2937;
            color: white;
            border-color: #1f2937;
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
            background: #1f2937;
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-add:hover {
            background: #111827;
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

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

            .stats-grid {
                grid-template-columns: 1fr;
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
            <h1 class="page-title">User Management</h1>
            <p class="page-subtitle">Kelola data guru, siswa, dan wali kelas</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_teachers); ?></div>
                <div class="stat-label">Total Guru</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_students); ?></div>
                <div class="stat-label">Total Siswa</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_wali_kelas); ?></div>
                <div class="stat-label">Total Wali Kelas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_teachers + $total_students); ?></div>
                <div class="stat-label">Total Pengguna</div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab">
                    <i class="fas fa-chalkboard-teacher"></i> Data Guru
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                    <i class="fas fa-user-graduate"></i> Data Siswa
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="wali-kelas-tab" data-bs-toggle="tab" data-bs-target="#wali-kelas" type="button" role="tab">
                    <i class="fas fa-user-tie"></i> Data Wali Kelas
                </button>
            </li>
        </ul>

        <div class="tab-content" id="userTabsContent">
            <!-- Teachers Tab -->
            <div class="tab-pane fade show active" id="teachers" role="tabpanel">
                <div class="management-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Data Guru
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
                                    <button class="btn btn-action btn-edit" onclick="editTeacher(<?php echo $teacher['id']; ?>)">
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

            <!-- Students Tab -->
            <div class="tab-pane fade" id="students" role="tabpanel">
                <div class="management-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-user-graduate"></i>
                            Data Siswa
                        </h3>
                        <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                            <i class="fas fa-plus"></i> Tambah Siswa
                        </button>
                    </div>
                    
                    <div class="table-container">
                    <table class="table">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
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
                                    <td><?php echo htmlspecialchars($student['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($student['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['jurusan']); ?></td>
                                    <td><?php echo htmlspecialchars($student['kelas']); ?></td>
                                    <td><span class="badge badge-success">Aktif</span></td>
                                    <td>
                                        <button class="btn btn-action btn-edit" onclick="editStudent(<?php echo $student['id']; ?>)">
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

            <!-- Wali Kelas Tab -->
            <div class="tab-pane fade" id="wali-kelas" role="tabpanel">
                <div class="management-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-user-tie"></i>
                            Data Wali Kelas
                        </h3>
                        <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addWaliKelasModal">
                            <i class="fas fa-plus"></i> Tambah Wali Kelas
                        </button>
                    </div>
                    
                    <div class="table-container">
                    <table class="table">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>NIP</th>
                                    <th>Nama Guru</th>
                                    <th>Email</th>
                                    <th>Kelas</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Status</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($wali_kelas as $wk): 
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($wk['nip']); ?></td>
                                    <td><?php echo htmlspecialchars($wk['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($wk['email']); ?></td>
                                    <td><span class="badge badge-warning"><?php echo htmlspecialchars($wk['kelas']); ?></span></td>
                                    <td><?php echo htmlspecialchars($wk['tahun_ajaran']); ?></td>
                                    <td><span class="badge badge-success">Aktif</span></td>
                                    <td>
                                        <button class="btn btn-action btn-edit" onclick="editWaliKelas(<?php echo $wk['id']; ?>)">
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

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-graduate"></i> Tambah Siswa Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_siswa">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIS <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nis" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="tanggal_lahir" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select class="form-select" name="jenis_kelamin" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required placeholder="Minimal 6 karakter">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                                    <select class="form-select" name="jurusan" id="add_student_jurusan" required>
                                        <option value="">Pilih Jurusan</option>
                                        <option value="IPA">IPA</option>
                                        <option value="IPS">IPS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                    <select class="form-select" name="kelas" id="add_student_kelas" required disabled>
                                        <option value="">Pilih Kelas</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat" rows="3" required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Simpan Siswa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Wali Kelas Modal -->
    <div class="modal fade" id="addWaliKelasModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie"></i> Tambah Wali Kelas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_wali_kelas">
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                            <select class="form-select" name="guru_id" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['nama']); ?> (<?php echo htmlspecialchars($teacher['nip']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $kelas_item): ?>
                                <option value="<?php echo $kelas_item; ?>"><?php echo $kelas_item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="tahun_ajaran" 
                                   value="<?php echo date('Y') . '/' . (date('Y') + 1); ?>" required>
                            <small class="text-muted">Format: 2024/2025</small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Simpan Wali Kelas
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

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Edit Data Siswa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editStudentForm">
                        <input type="hidden" name="action" value="update_siswa">
                        <input type="hidden" name="id" id="edit_student_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIS <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nis" id="edit_student_nis" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" id="edit_student_nama" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="tanggal_lahir" id="edit_student_tanggal_lahir" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select class="form-select" name="jenis_kelamin" id="edit_student_jenis_kelamin" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="edit_student_email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" id="edit_student_password" 
                                   placeholder="Kosongkan jika tidak ingin mengubah password">
                            <small class="text-muted">* Kosongkan jika tidak ingin mengubah password</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                                    <select class="form-select" name="jurusan" id="edit_student_jurusan" required>
                                        <option value="">Pilih Jurusan</option>
                                        <option value="IPA">IPA</option>
                                        <option value="IPS">IPS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                    <select class="form-select" name="kelas" id="edit_student_kelas" required disabled>
                                        <option value="">Pilih Kelas</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat" id="edit_student_alamat" rows="3" required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Update Siswa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Wali Kelas Modal -->
    <div class="modal fade" id="editWaliKelasModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Edit Wali Kelas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editWaliKelasForm">
                        <input type="hidden" name="action" value="update_wali_kelas">
                        <input type="hidden" name="id" id="edit_wali_kelas_id">
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                            <select class="form-select" name="guru_id" id="edit_wali_kelas_guru_id" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['nama']); ?> (<?php echo htmlspecialchars($teacher['nip']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas" id="edit_wali_kelas_kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $kelas_item): ?>
                                <option value="<?php echo $kelas_item; ?>"><?php echo $kelas_item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="tahun_ajaran" 
                                   id="edit_wali_kelas_tahun_ajaran" required>
                            <small class="text-muted">Format: 2024/2025</small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-add">
                                <i class="fas fa-save"></i> Update Wali Kelas
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
                    <p>Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" id="delete_action">
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
    const kelasData = {
        'IPA': [
            { value: 'X IPA 1', text: 'X IPA 1' },
            { value: 'X IPA 2', text: 'X IPA 2' },
            { value: 'XI IPA 1', text: 'XI IPA 1' },
            { value: 'XI IPA 2', text: 'XI IPA 2' },
            { value: 'XII IPA 1', text: 'XII IPA 1' },
            { value: 'XII IPA 2', text: 'XII IPA 2' }
        ],
        'IPS': [
            { value: 'X IPS 1', text: 'X IPS 1' },
            { value: 'X IPS 2', text: 'X IPS 2' },
            { value: 'XI IPS 1', text: 'XI IPS 1' },
            { value: 'XI IPS 2', text: 'XI IPS 2' },
            { value: 'XII IPS 1', text: 'XII IPS 1' },
            { value: 'XII IPS 2', text: 'XII IPS 2' }
        ]
    };

    function populateKelasDropdown(jurusanSelectId, kelasSelectId) {
        const jurusanSelect = document.getElementById(jurusanSelectId);
        const kelasSelect = document.getElementById(kelasSelectId);

        if (jurusanSelect && kelasSelect) {
            jurusanSelect.addEventListener('change', function() {
                const selectedJurusan = this.value;
                
                kelasSelect.innerHTML = '<option value="">Pilih Kelas</option>';
                
                if (selectedJurusan === '') {
                    kelasSelect.disabled = true;
                } else {
                    kelasSelect.disabled = false;
                    
                    const kelasOptions = kelasData[selectedJurusan];
                    
                    kelasOptions.forEach(function(kelas) {
                        const option = document.createElement('option');
                        option.value = kelas.value;
                        option.textContent = kelas.text;
                        kelasSelect.appendChild(option);
                    });
                }
            });
        }
    }

    populateKelasDropdown('add_student_jurusan', 'add_student_kelas');
    populateKelasDropdown('edit_student_jurusan', 'edit_student_kelas');

    function editTeacher(id) {
        fetch(`get_user_data.php?type=guru&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_teacher_id').value = data.data.id;
                    document.getElementById('edit_teacher_nip').value = data.data.nip;
                    document.getElementById('edit_teacher_nama').value = data.data.nama;
                    document.getElementById('edit_teacher_email').value = data.data.email;
                    document.getElementById('edit_teacher_mata_pelajaran').value = data.data.mata_pelajaran;
                    
                    new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
                } else {
                    alert('Error loading teacher data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading teacher data');
            });
    }

    function editStudent(id) {
        fetch(`get_user_data.php?type=siswa&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_student_id').value = data.data.id;
                    document.getElementById('edit_student_nis').value = data.data.nis;
                    document.getElementById('edit_student_nama').value = data.data.nama;
                    document.getElementById('edit_student_tanggal_lahir').value = data.data.tanggal_lahir || '';
                    document.getElementById('edit_student_jenis_kelamin').value = data.data.jenis_kelamin || '';
                    document.getElementById('edit_student_email').value = data.data.email;
                    document.getElementById('edit_student_alamat').value = data.data.alamat || '';
                    
                    const jurusanSelect = document.getElementById('edit_student_jurusan');
                    jurusanSelect.value = data.data.jurusan;
                    
                    const event = new Event('change');
                    jurusanSelect.dispatchEvent(event);
                    
                    setTimeout(() => {
                        document.getElementById('edit_student_kelas').value = data.data.kelas;
                    }, 100);
                    
                    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
                } else {
                    alert('Error loading student data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading student data');
            });
    }

    function editWaliKelas(id) {
        fetch(`get_user_data.php?type=wali_kelas&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_wali_kelas_id').value = data.data.id;
                    document.getElementById('edit_wali_kelas_guru_id').value = data.data.guru_id;
                    document.getElementById('edit_wali_kelas_kelas').value = data.data.kelas;
                    document.getElementById('edit_wali_kelas_tahun_ajaran').value = data.data.tahun_ajaran;
                    
                    new bootstrap.Modal(document.getElementById('editWaliKelasModal')).show();
                } else {
                    alert('Error loading wali kelas data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading wali kelas data');
            });
    }

    function deleteTeacher(id) {
        document.getElementById('delete_action').value = 'delete_guru';
        document.getElementById('delete_id').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function deleteStudent(id) {
        document.getElementById('delete_action').value = 'delete_siswa';
        document.getElementById('delete_id').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function deleteWaliKelas(id) {
        document.getElementById('delete_action').value = 'delete_wali_kelas';
        document.getElementById('delete_id').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        const tanggalLahirInputs = document.querySelectorAll('input[name="tanggal_lahir"]');
        const today = new Date().toISOString().split('T')[0];
        
        tanggalLahirInputs.forEach(input => {
            input.setAttribute('max', today);
        });
    });
    </script>
</body>
</html>