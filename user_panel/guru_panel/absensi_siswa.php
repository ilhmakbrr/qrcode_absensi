<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Jika tidak ada user_id di URL, cek apakah hanya ada 1 guru yang login
if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

// Validasi apakah user_id valid dan ada di session
if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: ../auth/login_user.php?role=guru");
    exit();
}

// Ambil data user dari session
$user_data = $_SESSION['logged_users']['guru'][$current_user_id];
$user_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];
$email = $user_data['email'];
$mata_pelajaran = $user_data['mata_pelajaran'];

include '../auth_user/auth_check_guru.php'; 
include '../../db.php';

// Ambil jadwal mengajar guru untuk filter
$jadwal_query = "SELECT DISTINCT jurusan, kelas, mata_pelajaran 
                 FROM jadwal_guru 
                 WHERE nip = '$nip' AND status = 'aktif'
                 ORDER BY jurusan, kelas";
$jadwal_result = mysqli_query($conn, $jadwal_query);

$jadwal_guru = [];
while ($row = mysqli_fetch_assoc($jadwal_result)) {
    $key = $row['jurusan'] . '|' . $row['kelas'];
    $jadwal_guru[$key] = true;
}

// Convert ke JSON untuk JavaScript
$jadwal_guru_json = json_encode($jadwal_guru);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Siswa - <?php echo htmlspecialchars($nama); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
  
    <script>
        // Data guru yang sedang login - HARUS ADA sebelum script lain
        const currentGuruData = {
            user_id: <?php echo intval($user_id); ?>,
            nip: '<?php echo addslashes($nip); ?>',
            nama: '<?php echo addslashes($nama); ?>',
            email: '<?php echo addslashes($email); ?>',
            mata_pelajaran: '<?php echo addslashes($mata_pelajaran); ?>'
        };
        
        // Debug log - akan terlihat di browser console
        const jadwalGuruData = <?php echo $jadwal_guru_json; ?>;
        console.log('=== Data Guru Loaded ===');
        console.log('User ID:', currentGuruData.user_id);
        console.log('Nama:', currentGuruData.nama);
        console.log('NIP:', currentGuruData.nip);
        console.log('Mata Pelajaran:', currentGuruData.mata_pelajaran);
        console.log('Jadwal Mengajar:', jadwalGuruData);
        console.log('========================');
    </script>
    
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

        .header p {
            margin: 5px 0 0;
            font-size: 1rem;
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

        /* Filter Form Styling - sama seperti riwayat */
        .filter-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        .filter-form h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .required-indicator {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-select, .form-control {
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
            border: none;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Table Container - sama seperti riwayat */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Styling khusus untuk kolom nomor */
        th:first-child, td:first-child {
            text-align: center;
            width: 60px;
            font-weight: bold;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Info Card - sama seperti riwayat */
        .data-info {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #0c5460;
        }

        .data-info i {
            margin-right: 8px;
        }

        /* Status Select Styling */
        .status-select {
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            min-width: 140px;
            transition: all 0.2s ease;
            width: 100%;
        }

        .status-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .status-hadir { 
            border-color: #27ae60; 
            background-color: #f0fdf4; 
            color: #065f46; 
        }
        .status-alpha { 
            border-color: #e74c3c; 
            background-color: #fef2f2; 
            color: #991b1b; 
        }
        .status-sakit { 
            border-color: #f39c12; 
            background-color: #fffbeb; 
            color: #92400e; 
        }
        .status-izin { 
            border-color: #3498db; 
            background-color: #eff6ff; 
            color: #1e40af; 
        }

        /* Summary Cards - dengan styling konsisten */
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .summary-card {
            padding: 24px;
            border-radius: 10px;
            text-align: center;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .summary-card h4 {
            font-size: 0.95rem;
            margin-bottom: 12px;
            opacity: 0.95;
            font-weight: 500;
        }

        .summary-card .count-number {
            font-size: 2.25rem;
            font-weight: 700;
        }

        .summary-hadir { 
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        .summary-alpha { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        .summary-sakit { 
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        .summary-izin { 
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        /* Empty State - konsisten dengan riwayat */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: #374151;
        }

        .empty-state p {
            font-size: 1rem;
            color: #6b7280;
        }

        /* Loading Animation */
        .loading-spinner {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alert untuk peringatan */
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
            border-left: 4px solid #f39c12;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                margin-right: 10px;
                margin-top: 10px;
                margin-bottom: 10px;
                padding: 20px;
            }
            
            .summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin: 0;
                padding: 15px;
                border-radius: 0;
            }
            
            .filter-form, .table-container {
                padding: 15px;
            }
            
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .summary {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
<?php include 'sidebar_guru.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-clipboard-check"></i> Absensi Siswa</h1>
            <p>Kelola kehadiran siswa secara real time tanpa ribet</p>
            <div class="guru-info">
            <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($nama); ?> 
                | <i class=""></i> <?php echo htmlspecialchars($mata_pelajaran); ?>
                | <i class=""></i><?php echo htmlspecialchars($nip); ?>
              </div>
        </div>

        <!-- Form Filter dengan styling konsisten -->
        <div class="filter-form">
            <h5><i class="fas fa-clipboard-list"></i> Input Data Absensi</h5>
            <p class="text-muted mb-3">Pilih jurusan, kelas, dan tanggal untuk memulai absensi</p>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="jurusan" class="form-label">
                        <i class="fas fa-graduation-cap"></i> Pilih Jurusan <span class="required-indicator">*</span>
                    </label>
                    <select id="jurusan" class="form-select" onchange="updateKelasOptions()">
                        <option value="">Pilih Jurusan</option>
                        <option value="IPA">IPA (Ilmu Pengetahuan Alam)</option>
                        <option value="IPS">IPS (Ilmu Pengetahuan Sosial)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="kelas" class="form-label">
                        <i class="fas fa-users"></i> Pilih Kelas <span class="required-indicator">*</span>
                    </label>
                    <select id="kelas" class="form-select" disabled>
                        <option value="">Pilih Kelas</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="tanggal" class="form-label">
                        <i class="fas fa-calendar-alt"></i> Tanggal Absensi <span class="required-indicator">*</span>
                    </label>
                    <input type="date" id="tanggal" class="form-control" value="">
                </div>
            </div>

            <div class="d-flex gap-3">
                <button class="btn btn-primary" onclick="loadStudents()">
                    <i class="fas fa-search"></i> Cari Data Siswa
                </button>

                <button class="btn btn-success" onclick="saveAttendance()" id="saveBtn" disabled>
                    <i class="fas fa-save"></i> Simpan Absensi
                </button>
            </div>
        </div>

        <div class="alert alert-warning text-center" id="initialWarning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Lengkapi Filter</strong><br>
            Silakan pilih jurusan dan kelas terlebih dahulu untuk mengambil absensi siswa.
        </div>

        <!-- Area untuk menampilkan data siswa -->
        <div id="studentsList"></div>
    </div>

    <!-- Load JavaScript Handler -->
    <script src="../js/absensi_handler.js"></script>
</body>
</html>