<?php
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();

    include '../../db.php';
    include '../auth_user/auth_check_guru.php'; 

    // Ambil user_id dari parameter URL
    $current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

    // Jika tidak ada user_id, ambil dari session pertama (fallback)
    if (!$current_user_id) {
        if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
            $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
        }
    }

    // ✅ VALIDASI PENTING: Pastikan user_id yang diakses memang milik session yang login
    if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
        // Redirect ke login jika user_id tidak valid atau tidak ada di session
        header("Location: ../auth/login_user.php?role=guru&error=unauthorized");
        exit();
    }

    // ✅ VALIDASI TAMBAHAN: Cek apakah user ini benar-benar pemilik session
    // Ambil data dari session
    $user_data = $_SESSION['logged_users']['guru'][$current_user_id];

    // Ambil data dari database untuk verifikasi tambahan
    $verify_query = "SELECT id, nip, nama, email, mata_pelajaran FROM guru WHERE id = '$current_user_id'";
    $verify_result = mysqli_query($conn, $verify_query);

    if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
        // ID tidak valid di database
        header("Location: ../auth/login_user.php?role=guru&error=invalid_user");
        exit();
    }

    $db_user = mysqli_fetch_assoc($verify_result);

    // Pastikan NIP di session sama dengan NIP di database
    if ($user_data['nip'] !== $db_user['nip']) {
        // Ada ketidakcocokan data - possible session hijacking
        unset($_SESSION['logged_users']['guru'][$current_user_id]);
        header("Location: ../auth/login_user.php?role=guru&error=session_mismatch");
        exit();
    }

    // Semua validasi lolos, gunakan data dari session
    $guru_id = $user_data['user_id'];
    $nip = $user_data['nip'];
    $nama = $user_data['nama'];
    $email = $user_data['email'];
    $mata_pelajaran = $user_data['mata_pelajaran'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'tambah':
            $hari = mysqli_real_escape_string($conn, $_POST['hari']);
            $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
            $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
            $jurusan = mysqli_real_escape_string($conn, $_POST['jurusan']);
            $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
            
            // Validasi waktu
            if ($jam_mulai >= $jam_selesai) {
                echo json_encode(['success' => false, 'message' => 'Jam selesai harus lebih besar dari jam mulai!']);
                exit();
            }
            
            // Cek konflik jadwal (guru yang sama pada waktu yang sama)
            $check_conflict = "SELECT * FROM jadwal_guru 
                             WHERE nip = '$nip' 
                             AND hari = '$hari' 
                             AND status = 'aktif'
                             AND (
                                 (jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') OR
                                 (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai') OR
                                 (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai')
                             )";
            
            $conflict_result = mysqli_query($conn, $check_conflict);
            
            if (mysqli_num_rows($conflict_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Konflik jadwal! Anda sudah memiliki jadwal pada waktu tersebut.']);
                exit();
            }
            
            // Cek konflik kelas (kelas yang sama pada waktu yang sama dengan guru berbeda)
            $check_kelas = "SELECT g.nama FROM jadwal_guru jg 
                          JOIN guru g ON jg.nip = g.nip
                          WHERE jg.kelas = '$kelas' 
                          AND jg.hari = '$hari' 
                          AND jg.status = 'aktif'
                          AND jg.nip != '$nip'
                          AND (
                              (jg.jam_mulai <= '$jam_mulai' AND jg.jam_selesai > '$jam_mulai') OR
                              (jg.jam_mulai < '$jam_selesai' AND jg.jam_selesai >= '$jam_selesai') OR
                              (jg.jam_mulai >= '$jam_mulai' AND jg.jam_selesai <= '$jam_selesai')
                          )";
            
            $kelas_result = mysqli_query($conn, $check_kelas);
            
            if (mysqli_num_rows($kelas_result) > 0) {
                $guru_konflik = mysqli_fetch_assoc($kelas_result);
                echo json_encode(['success' => false, 'message' => 'Konflik kelas! Kelas ' . $kelas . ' sudah dijadwalkan oleh ' . $guru_konflik['nama'] . ' pada waktu tersebut.']);
                exit();
            }
            
            // ✅ DIPERBAIKI: Menggunakan $mata_pelajaran bukan $mata_pelajaran_guru
            // Insert jadwal baru
            $insert_query = "INSERT INTO jadwal_guru (guru_id, nip, hari, jam_mulai, jam_selesai, mata_pelajaran, jurusan, kelas) 
                           VALUES ('$guru_id', '$nip', '$hari', '$jam_mulai', '$jam_selesai', '$mata_pelajaran', '$jurusan', '$kelas')";
            
            if (mysqli_query($conn, $insert_query)) {
                echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit();
            
        case 'edit':
            $id = (int)$_POST['id'];
            $hari = mysqli_real_escape_string($conn, $_POST['hari']);
            $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
            $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
            $jurusan = mysqli_real_escape_string($conn, $_POST['jurusan']);
            $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
            
            // Validasi waktu
            if ($jam_mulai >= $jam_selesai) {
                echo json_encode(['success' => false, 'message' => 'Jam selesai harus lebih besar dari jam mulai!']);
                exit();
            }
            
            // Cek konflik jadwal (kecuali jadwal yang sedang diedit)
            $check_conflict = "SELECT * FROM jadwal_guru 
                             WHERE nip = '$nip' 
                             AND hari = '$hari' 
                             AND status = 'aktif'
                             AND id != $id
                             AND (
                                 (jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') OR
                                 (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai') OR
                                 (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai')
                             )";
            
            $conflict_result = mysqli_query($conn, $check_conflict);
            
            if (mysqli_num_rows($conflict_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Konflik jadwal! Anda sudah memiliki jadwal pada waktu tersebut.']);
                exit();
            }
            
            // Update jadwal
            $update_query = "UPDATE jadwal_guru 
                           SET hari = '$hari', jam_mulai = '$jam_mulai', jam_selesai = '$jam_selesai', 
                               jurusan = '$jurusan', kelas = '$kelas', updated_at = NOW()
                           WHERE id = $id AND nip = '$nip'";
            
            if (mysqli_query($conn, $update_query)) {
                echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit();
            
        case 'hapus':
            $id = (int)$_POST['id'];
            
            $delete_query = "UPDATE jadwal_guru SET status = 'tidak_aktif' WHERE id = $id AND nip = '$nip'";
            
            if (mysqli_query($conn, $delete_query)) {
                echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit();
            
        case 'get_jadwal':
            $id = (int)$_POST['id'];
            $query = "SELECT * FROM jadwal_guru WHERE id = $id AND nip = '$nip' AND status = 'aktif'";
            $result = mysqli_query($conn, $query);
            
            if ($row = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan']);
            }
            exit();
    }
}

// Ambil data jadwal guru
$jadwal_query = "SELECT * FROM jadwal_guru
                WHERE nip = '$nip' AND status = 'aktif' 
                ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jam_mulai";
$jadwal_result = mysqli_query($conn, $jadwal_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    <style>
            body {
                display: flex;
                min-height: 100vh;
                margin: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f8f9fa;
            }

            .main-content {
                margin-left: 20px;
                margin-right: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
                flex: 1;
                padding: 30px;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            .modal-content {
            border-radius: 15px;
            }

            .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            }

            .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: scale(1.02);
            }

            .table-container {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin: 0;
        }
        .header .guru-info {
            margin-top: 10px;
            font-size: 0.9rem;
            opacity: 0.9;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: inline-block;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 25px;
            border-left: 5px solid #3498db;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 25px;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }

        .badge-hari {
            background: #3498db;
            color: white;
        }

        .badge-jam {
            background: #27ae60;
            color: white;
        }

        .day-label {
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .main-content {
                margin-left: 20px;
            }
        }
    </style>
</head>
<body>
   <?php include 'sidebar_guru.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1><i class="bi bi-people-fill"></i> Jadwal Mengajar</h1>
            <p>Kelola jadwal mengajar Anda dengan mudah dan efisien</p>
            <div class="guru-info">
                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($nama); ?> 
                | <i class=""></i> <?php echo htmlspecialchars($mata_pelajaran); ?>
                | <i class=""></i><?php echo htmlspecialchars($nip); ?>
            </div>
        </div>

        <!-- Tombol Tambah Jadwal -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary"><i class="bi bi-calendar3"></i> Daftar Jadwal Mengajar</h5>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#jadwalModal">
                    <i class="fas fa-plus"></i> Tambah Jadwal
                </button>
            </div>

            <!-- Modal Tambah/Edit Jadwal -->
            <div class="modal fade" id="jadwalModal" tabindex="-1" aria-labelledby="jadwalModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="formTitle"><i class="fas fa-plus-circle"></i> Tambah Jadwal Mengajar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="jadwalForm">
                    <input type="hidden" id="editId" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                        <label for="hari" class="form-label">Hari <span class="text-danger">*</span></label>
                        <select id="hari" class="form-select" required>
                            <option value="">-- Pilih Hari --</option>
                            <option value="Senin">Senin</option>
                            <option value="Selasa">Selasa</option>
                            <option value="Rabu">Rabu</option>
                            <option value="Kamis">Kamis</option>
                            <option value="Jumat">Jumat</option>
                            <option value="Sabtu">Sabtu</option>
                        </select>
                        </div>

                        <div class="col-md-4">
                        <label for="jamMulai" class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                        <input type="time" id="jamMulai" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                        <label for="jamSelesai" class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                        <input type="time" id="jamSelesai" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                        <label for="jurusan" class="form-label">Jurusan <span class="text-danger">*</span></label>
                        <select id="jurusan" class="form-select" onchange="updateKelasOptions()" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <option value="IPA">IPA</option>
                            <option value="IPS">IPS</option>
                        </select>
                        </div>

                        <div class="col-md-6">
                        <label for="kelas" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select id="kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                        </select>
                        </div>
                    </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" form="jadwalForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> <span id="btnText">Simpan Jadwal</span>
                    </button>
                </div>
                </div>
            </div>
            </div>


        <!-- Tabel Jadwal -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Hari</th>
                            <th>Waktu</th>
                            <th>Mata Pelajaran</th>
                            <th>Jurusan</th>
                            <th>Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($jadwal_result) > 0): 
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($jadwal_result)): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <span class="day-label <?php echo strtolower($row['hari']); ?>"><?php echo $row['hari']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo substr($row['jam_mulai'], 0, 5) . ' - ' . substr($row['jam_selesai'], 0, 5); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['mata_pelajaran']); ?></strong></td>
                                <td><span class=""><?php echo $row['jurusan']; ?></span></td>
                                <td><span class=""><?php echo htmlspecialchars($row['kelas']); ?></span></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="editJadwal(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="hapusJadwal(<?php echo $row['id']; ?>, '<?php echo $row['hari'] . ', ' . $row['kelas']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i><br>
                                    <strong>Belum ada jadwal mengajar</strong><br>
                                    <small class="text-muted">Silakan tambahkan jadwal mengajar baru</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus jadwal mengajar ini?</p>
                    <div id="deleteInfo" class="alert alert-warning"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/jdwlpengajar_hadler.js"></script>
   
</body>
</html>