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

include '../../db.php'; 
include '../auth_user/auth_check_guru.php'; 

// Cek apakah guru adalah wali kelas
$tahun_ajaran_current = date('Y') . '/' . (date('Y') + 1);
$wali_query = "SELECT wk.*, g.nama as nama_guru FROM wali_kelas wk 
               JOIN guru g ON wk.guru_id = g.id 
               WHERE wk.guru_id = ? AND wk.tahun_ajaran = ?";
$stmt = mysqli_prepare($conn, $wali_query);
mysqli_stmt_bind_param($stmt, "is", $user_id, $tahun_ajaran_current);
mysqli_stmt_execute($stmt);
$wali_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($wali_result) == 0) {
    die("
    <div style='display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif;'>
        <div style='text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
            <i class='fas fa-exclamation-triangle' style='font-size: 4rem; color: #e74c3c; margin-bottom: 20px;'></i>
            <h2 style='color: #2c3e50; margin-bottom: 10px;'>Akses Ditolak</h2>
            <p style='color: #7f8c8d; font-size: 1.1rem;'>Anda bukan wali kelas. Halaman ini hanya dapat diakses oleh wali kelas.</p>
            <a href='dashboard_guru.php?user_id=$current_user_id' style='display: inline-block; margin-top: 20px; padding: 12px 30px; background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>
                <i class='fas fa-arrow-left'></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    ");
}

$wali_data = mysqli_fetch_assoc($wali_result);
$kelas_wali = $wali_data['kelas'];

// Parse kelas untuk mendapatkan jurusan
$kelas_parts = explode(' ', $kelas_wali);
$jurusan_wali = isset($kelas_parts[1]) ? $kelas_parts[1] : '';

// Kelas dan jurusan wali kelas otomatis digunakan
$kelas_filter = $kelas_wali;
$jurusan_filter = $jurusan_wali;

// Filter parameters - hanya semester dan tahun ajaran
$semester_filter = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$tahun_ajaran_filter = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : '';

// Tentukan rentang tanggal berdasarkan semester dan tahun ajaran
$tanggal_dari = '';
$tanggal_sampai = '';
$semester_label = '';

if (!empty($semester_filter) && !empty($tahun_ajaran_filter)) {
    // Format tahun ajaran: 2024/2025
    $tahun_parts = explode('/', $tahun_ajaran_filter);
    
    if ($semester_filter == 'Ganjil') {
        // Semester Ganjil: Juli - Desember (tahun pertama)
        $tanggal_dari = $tahun_parts[0] . '-07-01';
        $tanggal_sampai = $tahun_parts[0] . '-12-31';
        $semester_label = 'Ganjil (Juli - Desember ' . $tahun_parts[0] . ')';
    } else {
        // Semester Genap: Januari - Juni (tahun kedua)
        $tanggal_dari = $tahun_parts[1] . '-01-01';
        $tanggal_sampai = $tahun_parts[1] . '-06-30';
        $semester_label = 'Genap (Januari - Juni ' . $tahun_parts[1] . ')';
    }
}

$students_data = [];
$total_hari_sekolah = 0;

// Cek apakah filter minimal sudah diisi
$filters_filled = !empty($semester_filter) && !empty($tahun_ajaran_filter);

// Fungsi untuk menentukan status prioritas dalam 1 hari
// Logika: Alpha > Sakit > Izin > Hadir
function getStatusPrioritas($status_array) {
    if (in_array('alpha', $status_array)) {
        return 'alpha';
    } elseif (in_array('sakit', $status_array)) {
        return 'sakit';
    } elseif (in_array('izin', $status_array)) {
        return 'izin';
    } else {
        return 'hadir';
    }
}

if ($filters_filled) {
    // Ambil semua tanggal unik dalam semester untuk kelas ini
    $query_days = "SELECT COUNT(DISTINCT tanggal) as total_days 
                   FROM absensi 
                   WHERE jurusan = ? 
                   AND kelas = ? 
                   AND tanggal BETWEEN ? AND ?";
    
    $stmt_days = mysqli_prepare($conn, $query_days);
    mysqli_stmt_bind_param($stmt_days, "ssss", $jurusan_filter, $kelas_filter, $tanggal_dari, $tanggal_sampai);
    mysqli_stmt_execute($stmt_days);
    $result_days = mysqli_stmt_get_result($stmt_days);
    
    if ($result_days) {
        $row_days = mysqli_fetch_assoc($result_days);
        $total_hari_sekolah = $row_days['total_days'];
    }
    mysqli_stmt_close($stmt_days);

    // Ambil data siswa dari kelas yang dipilih
    $query_students = "SELECT DISTINCT s.id, s.nis, s.nama 
                       FROM siswa s 
                       WHERE s.jurusan = ? 
                       AND s.kelas = ?
                       ORDER BY s.nama ASC";
    
    $stmt_students = mysqli_prepare($conn, $query_students);
    mysqli_stmt_bind_param($stmt_students, "ss", $jurusan_filter, $kelas_filter);
    mysqli_stmt_execute($stmt_students);
    $result_students = mysqli_stmt_get_result($stmt_students);
    
    if ($result_students) {
        while ($student = mysqli_fetch_assoc($result_students)) {
            // Ambil semua absensi per hari untuk siswa ini
            $query_attendance = "SELECT tanggal, status 
                                FROM absensi 
                                WHERE student_id = ?
                                AND jurusan = ?
                                AND kelas = ?
                                AND tanggal BETWEEN ? AND ?
                                ORDER BY tanggal";
            
            $stmt_attendance = mysqli_prepare($conn, $query_attendance);
            mysqli_stmt_bind_param($stmt_attendance, "issss", 
                $student['id'], 
                $jurusan_filter, 
                $kelas_filter,
                $tanggal_dari, 
                $tanggal_sampai
            );
            mysqli_stmt_execute($stmt_attendance);
            $result_attendance = mysqli_stmt_get_result($stmt_attendance);
            
            // Group by tanggal untuk menerapkan logika prioritas
            $absensi_per_hari = [];
            if ($result_attendance) {
                while ($row = mysqli_fetch_assoc($result_attendance)) {
                    $tanggal = $row['tanggal'];
                    $status = strtolower($row['status']);
                    
                    if (!isset($absensi_per_hari[$tanggal])) {
                        $absensi_per_hari[$tanggal] = [];
                    }
                    $absensi_per_hari[$tanggal][] = $status;
                }
            }
            mysqli_stmt_close($stmt_attendance);
            
            // Hitung total berdasarkan status prioritas per hari
            $attendance_data = [
                'hadir' => 0,
                'alpha' => 0,
                'sakit' => 0,
                'izin' => 0
            ];
            
            foreach ($absensi_per_hari as $tanggal => $status_array) {
                $status_final = getStatusPrioritas($status_array);
                $attendance_data[$status_final]++;
            }
            
            $total_kehadiran = array_sum($attendance_data);
            $persentase_kehadiran = $total_hari_sekolah > 0 ? 
                round(($attendance_data['hadir'] / $total_hari_sekolah) * 100, 1) : 0;
            
            $students_data[] = [
                'nis' => $student['nis'],
                'nama' => $student['nama'],
                'hadir' => $attendance_data['hadir'],
                'alpha' => $attendance_data['alpha'],
                'sakit' => $attendance_data['sakit'],
                'izin' => $attendance_data['izin'],
                'total_kehadiran' => $total_kehadiran,
                'persentase' => $persentase_kehadiran
            ];
        }
    }
    mysqli_stmt_close($stmt_students);
}

// Hitung statistik kelas
$total_siswa = count($students_data);
$rata_rata_kehadiran = 0;
if ($total_siswa > 0) {
    $total_persentase = array_sum(array_column($students_data, 'persentase'));
    $rata_rata_kehadiran = round($total_persentase / $total_siswa, 1);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kehadiran Semester - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Times New Roman', serif;
            background: #f8f9fa;
        }
        
        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        .filter-form {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .filter-form h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
        }
        
        .form-select, .form-control {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #8e44ad;
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            border: none;
            padding: 12px 25px;
            font-weight: 500;
        }

        /* Header Laporan */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        .report-header .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: #2c3e50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .report-header h1 {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .report-header h2 {
            font-size: 1.4rem;
            color: #34495e;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .report-header .school-info {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .report-header .report-title {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .report-info .info-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }

        .report-info .info-box h6 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1rem;
            text-transform: uppercase;
        }

        .report-info .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #dee2e6;
        }

        .report-info .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .report-info .info-label {
            font-weight: 500;
            color: #555;
        }

        .report-info .info-value {
            font-weight: bold;
            color: #2c3e50;
        }

        /* Tabel */
        .table-container {
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        thead th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            border-right: 1px solid #fff;
        }

        tbody td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }

        tbody td:first-child {
            font-weight: bold;
        }

        tbody td:nth-child(2) {
            text-align: left;
            font-weight: 500;
        }

        tbody td:nth-child(3) {
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:hover {
            background-color: #e8daef;
        }

        .percentage-cell {
            font-weight: bold;
        }

        .percentage-excellent { color: #27ae60; }
        .percentage-good { color: #2980b9; }
        .percentage-fair { color: #f39c12; }
        .percentage-poor { color: #e74c3c; }

        /* Ringkasan */
        .summary-section {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }

        .summary-section h5 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            border-bottom: 2px solid #8e44ad;
            padding-bottom: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .summary-item .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .summary-item .value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .summary-item .label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 500;
        }

        .summary-total { border-color: #8e44ad; color: #8e44ad; }
        .summary-average { border-color: #27ae60; color: #27ae60; }
        .summary-days { border-color: #f39c12; color: #f39c12; }

        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background: #ebf5fb;
            border: 2px solid #3498db;
            border-radius: 8px;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .signature-box .title {
            font-weight: bold;
            margin-bottom: 60px;
            color: #2c3e50;
        }

        .signature-box .line {
            border-bottom: 2px solid #2c3e50;
            margin-bottom: 5px;
        }

        .signature-box .name {
            font-weight: 500;
            color: #555;
        }

        /* Print Styles - COMPACT & RAPI */
        @media print {
            .sidebar, 
            .filter-form, 
            .btn,
            .no-print,
            .header {
                display: none !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 10mm 8mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 9pt !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            /* Header Laporan - Compact */
            .report-header {
                page-break-after: avoid !important;
                margin-bottom: 10px !important;
                border-bottom: 2px solid #2c3e50 !important;
                padding-bottom: 8px !important;
            }

            .report-header .school-logo {
                width: 45px !important;
                height: 45px !important;
                margin: 0 auto 6px !important;
                font-size: 1.2rem !important;
            }

            .report-header h1 {
                font-size: 1rem !important;
                margin-bottom: 2px !important;
            }

            .report-header h2 {
                font-size: 0.85rem !important;
                margin-bottom: 6px !important;
            }

            .report-header .school-info {
                font-size: 0.7rem !important;
                margin-bottom: 8px !important;
                line-height: 1.2 !important;
            }

            .report-header .report-title {
                padding: 6px 10px !important;
                margin: 8px 0 !important;
                font-size: 0.75rem !important;
                border-radius: 4px !important;
            }

            /* Info Laporan - Compact */
            .report-info {
                gap: 10px !important;
                margin-bottom: 10px !important;
                font-size: 0.7rem !important;
            }

            .report-info .info-box {
                padding: 8px !important;
                border: 1px solid #e9ecef !important;
            }

            .report-info .info-box h6 {
                margin-bottom: 6px !important;
                font-size: 0.72rem !important;
                padding-bottom: 3px !important;
            }

            .report-info .info-item {
                margin-bottom: 3px !important;
                padding: 2px 0 !important;
                font-size: 0.68rem !important;
            }

            .report-info .info-label,
            .report-info .info-value {
                font-size: 0.68rem !important;
            }

            /* Tabel - Compact */
            .table-container {
                margin-bottom: 10px !important;
                border: 1px solid #dee2e6 !important;
            }

            table {
                font-size: 0.68rem !important;
            }

            thead th {
                padding: 5px 3px !important;
                font-size: 0.65rem !important;
                letter-spacing: 0.2px !important;
            }

            tbody td {
                padding: 4px 3px !important;
                font-size: 0.65rem !important;
                border-bottom: 0.5px solid #dee2e6 !important;
            }

            tbody td:first-child {
                font-weight: 600 !important;
            }

            tbody td:nth-child(2),
            tbody td:nth-child(3) {
                font-size: 0.63rem !important;
            }

            /* Ringkasan - Compact */
            .summary-section {
                padding: 10px !important;
                margin-top: 10px !important;
                border: 1px solid #dee2e6 !important;
                page-break-inside: avoid !important;
            }

            .summary-section h5 {
                margin-bottom: 8px !important;
                font-size: 0.75rem !important;
                padding-bottom: 4px !important;
            }

            .summary-grid {
                gap: 8px !important;
                margin-bottom: 8px !important;
            }

            .summary-item {
                padding: 8px !important;
                border: 1px solid #e9ecef !important;
            }

            .summary-item .icon {
                font-size: 1.1rem !important;
                margin-bottom: 4px !important;
            }

            .summary-item .value {
                font-size: 1.1rem !important;
                margin-bottom: 2px !important;
            }

            .summary-item .label {
                font-size: 0.62rem !important;
            }

            /* Notes Section */
            .notes-section {
                margin-top: 8px !important;
                padding: 8px 10px !important;
                border: 1px solid #3498db !important;
            }

            .notes-section h6 {
                font-size: 0.7rem !important;
                margin-bottom: 6px !important;
            }

            .notes-section ul {
                font-size: 0.63rem !important;
                line-height: 1.3 !important;
                margin: 0 !important;
                padding-left: 15px !important;
            }

            .notes-section li {
                margin-bottom: 3px !important;
            }

            /* Signature - Compact */
            .signature-section {
                margin-top: 15px !important;
                padding: 8px 0 !important;
                page-break-inside: avoid !important;
            }

            .signature-box {
                width: 160px !important;
            }

            .signature-box .title {
                font-size: 0.7rem !important;
                margin-bottom: 40px !important;
                line-height: 1.2 !important;
            }

            .signature-box .line {
                border-bottom: 1px solid #2c3e50 !important;
                margin-bottom: 3px !important;
            }

            .signature-box .name {
                font-size: 0.7rem !important;
                font-weight: 600 !important;
            }

            .signature-box div:last-child {
                font-size: 0.63rem !important;
            }

            /* Color Preservation */
            thead th {
                background: #2c3e50 !important;
                color: white !important;
            }

            tbody tr:nth-child(even) {
                background-color: #f8f9fa !important;
            }

            .percentage-excellent { color: #27ae60 !important; }
            .percentage-good { color: #2980b9 !important; }
            .percentage-fair { color: #f39c12 !important; }
            .percentage-poor { color: #e74c3c !important; }

            .summary-total { border-color: #8e44ad !important; color: #8e44ad !important; }
            .summary-average { border-color: #27ae60 !important; color: #27ae60 !important; }
            .summary-days { border-color: #f39c12 !important; color: #f39c12 !important; }

            .report-header .report-title {
                background: linear-gradient(135deg, #2c3e50, #3498db) !important;
                color: white !important;
            }

            .report-header .school-logo {
                background: #2c3e50 !important;
                color: white !important;
            }

            /* Page Break Controls */
            .report-header {
                page-break-after: avoid !important;
            }
            
            thead {
                display: table-header-group !important;
            }
            
            tr {
                page-break-inside: avoid !important;
            }

            tbody {
                display: table-row-group !important;
            }

            h1, h2, h3, h4, h5, h6, p {
                margin-top: 0 !important;
                margin-bottom: 0.3rem !important;
            }
        }
    </style>
</head>
<body>
    
<?php include 'sidebar_guru.php'; ?>

<div class="main-content">
    <div class="header no-print">
        <h1><i class="fas fa-file-alt"></i> Laporan Kehadiran Semester</h1>
        <p>Laporan rekapitulasi kehadiran siswa per semester</p>
        <div class="guru-info">
            <i class="fas fa-user-tie"></i> Wali Kelas: <?php echo htmlspecialchars($nama); ?> 
            | <i class=""></i> Kelas: <?php echo htmlspecialchars($kelas_wali); ?>
            | <i class=""></i> <?php echo htmlspecialchars($nip); ?>
        </div>
    </div>
    
    <!-- Form Filter -->
    <div class="filter-form no-print">
        <h5><i class="fas fa-filter"></i> Filter Laporan - Kelas <?php echo htmlspecialchars($kelas_wali); ?></h5>
        <p class="text-muted mb-3">Pilih semester dan tahun ajaran untuk menampilkan laporan kehadiran kelas Anda</p>
        
        <form method="GET" class="row g-3">
            <!-- Hidden input user_id -->
            <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">
            
            <div class="col-md-6">
                <label class="form-label">Semester <span style="color: red;">*</span></label>
                <select name="semester" class="form-select" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="Ganjil" <?= ($semester_filter == 'Ganjil') ? 'selected' : '' ?>>Ganjil (Juli - Desember)</option>
                    <option value="Genap" <?= ($semester_filter == 'Genap') ? 'selected' : '' ?>>Genap (Januari - Juni)</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Tahun Ajaran <span style="color: red;">*</span></label>
                <select name="tahun_ajaran" class="form-select" required>
                    <option value="">-- Pilih Tahun --</option>
                    <option value="2023/2024" <?= ($tahun_ajaran_filter == '2023/2024') ? 'selected' : '' ?>>2023/2024</option>
                    <option value="2024/2025" <?= ($tahun_ajaran_filter == '2024/2025') ? 'selected' : '' ?>>2024/2025</option>
                    <option value="2025/2026" <?= ($tahun_ajaran_filter == '2025/2026') ? 'selected' : '' ?>>2025/2026</option>
                    <option value="2026/2027" <?= ($tahun_ajaran_filter == '2026/2027') ? 'selected' : '' ?>>2026/2027</option>
                </select>
            </div>

            <div class="col-12">
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search"></i> Tampilkan Laporan
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    
                    <?php if ($filters_filled && $total_siswa > 0): ?>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Cetak Laporan
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$filters_filled): ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Lengkapi Filter</strong><br>
            Silakan pilih semester dan tahun ajaran terlebih dahulu untuk menampilkan laporan kehadiran.
        </div>
    <?php else: ?>

        <!-- Header Laporan -->
        <div class="report-header">
            <div class="school-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>SMA PGRI 2 Padang</h1>
            <h2>Laporan Kehadiran Siswa Semester</h2>
            <div class="school-info">
                Jl. Linggar Jati no.1 Lubuk Begalung, Sumatera Barat 25173<br>
                Telepon: (0751) 72293 | Email: smapgri2padang@kemdikbud.go.id
            </div>
            
            <div class="report-title">
                Kelas <?= htmlspecialchars($kelas_filter) ?> - <?= htmlspecialchars($jurusan_filter) ?> | 
                Semester <?= htmlspecialchars($semester_label) ?> | 
                Tahun Ajaran <?= htmlspecialchars($tahun_ajaran_filter) ?>
            </div>
        </div>

        <!-- Info Laporan -->
        <div class="report-info">
            <div class="info-box">
                <h6><i class="fas fa-info-circle"></i> Informasi Kelas</h6>
                <div class="info-item">
                    <span class="info-label">Jurusan</span>
                    <span class="info-value"><?= htmlspecialchars($jurusan_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($kelas_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Wali Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($nama) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">NIP</span>
                    <span class="info-value"><?= htmlspecialchars($nip) ?></span>
                </div>
            </div>
            
            <div class="info-box">
                <h6><i class="fas fa-calendar-alt"></i> Periode Laporan</h6>
                <div class="info-item">
                    <span class="info-label">Semester</span>
                    <span class="info-value"><?= htmlspecialchars($semester_label) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tahun Ajaran</span>
                    <span class="info-value"><?= htmlspecialchars($tahun_ajaran_filter) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Rentang Tanggal</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($tanggal_dari)) ?> - <?= date('d/m/Y', strtotime($tanggal_sampai)) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Cetak</span>
                    <span class="info-value"><?= date('d F Y') ?></span>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No.</th>
                        <th style="width: 200px;">Nama Siswa</th>
                        <th style="width: 100px;">NIS</th>
                        <th style="width: 80px;">Total Hari</th>
                        <th style="width: 60px;">Hadir</th>
                        <th style="width: 60px;">Alpha</th>
                        <th style="width: 60px;">Izin</th>
                        <th style="width: 60px;">Sakit</th>
                        <th style="width: 100px;">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_siswa > 0): ?>
                        <?php $no = 1; foreach ($students_data as $student): ?>
                        <?php
                        $persentase_class = '';
                        if ($student['persentase'] >= 90) $persentase_class = 'percentage-excellent';
                        elseif ($student['persentase'] >= 75) $persentase_class = 'percentage-good';
                        elseif ($student['persentase'] >= 60) $persentase_class = 'percentage-fair';
                        else $persentase_class = 'percentage-poor';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($student['nama']) ?></td>
                            <td><?= htmlspecialchars($student['nis']) ?></td>
                            <td><?= $total_hari_sekolah ?></td>
                            <td><?= $student['hadir'] ?></td>
                            <td><?= $student['alpha'] ?></td>
                            <td><?= $student['izin'] ?></td>
                            <td><?= $student['sakit'] ?></td>
                            <td class="percentage-cell <?= $persentase_class ?>">
                                <?= $student['persentase'] ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                                <strong>Belum Ada Data</strong><br>
                                Tidak ada data kehadiran untuk Semester <strong><?= htmlspecialchars($semester_filter) ?></strong><br>
                                Tahun Ajaran: <strong><?= htmlspecialchars($tahun_ajaran_filter) ?></strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_siswa > 0): ?>
        <!-- Ringkasan -->
        <div class="summary-section">
            <h5><i class="fas fa-chart-bar"></i> Ringkasan Laporan</h5>
            
            <div class="summary-grid">
                <div class="summary-item summary-total">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="value"><?= $total_siswa ?></div>
                    <div class="label">Total Siswa</div>
                </div>
                
                <div class="summary-item summary-average">
                    <div class="icon"><i class="fas fa-percentage"></i></div>
                    <div class="value"><?= $rata_rata_kehadiran ?>%</div>
                    <div class="label">Rata-rata Kehadiran</div>
                </div>
                
                <div class="summary-item summary-days">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="value"><?= $total_hari_sekolah ?></div>
                    <div class="label">Total Hari Sekolah</div>
                </div>
            </div>

            <div class="notes-section">
                <h6><i class="fas fa-sticky-note"></i> Catatan Penting:</h6>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Logika Penghitungan Status Per Hari:</strong> Dalam 1 hari, jika siswa memiliki beberapa mata pelajaran, status akhir ditentukan dengan prioritas: <strong>Alpha > Sakit > Izin > Hadir</strong></li>
                    <li>Persentase kehadiran dihitung berdasarkan jumlah hari hadir dibagi total hari sekolah</li>
                    <li>Total hari sekolah semester ini: <strong><?= $total_hari_sekolah ?> hari</strong></li>
                    <li>Standar kehadiran minimal adalah 75% untuk dapat mengikuti evaluasi semester</li>
                    <li>Semester <strong><?= htmlspecialchars($semester_filter) ?></strong> Tahun Ajaran <strong><?= htmlspecialchars($tahun_ajaran_filter) ?></strong></li>
                    <?php if ($rata_rata_kehadiran < 75): ?>
                    <li style="color: #e74c3c; font-weight: bold;">⚠️ Rata-rata kehadiran kelas di bawah standar minimal (<?= $rata_rata_kehadiran ?>%)</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Tanda Tangan -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="title">Mengetahui,<br>Kepala Sekolah</div>
                <div class="line"></div>
                <div class="name">Ilham Akbar, S.Kom</div>
                <div style="font-size: 0.9rem; color: #666;">NIP. 196701011990031004</div>
            </div>
            
            <div class="signature-box">
                <div class="title">Padang, <?= date('d F Y') ?><br>Wali Kelas</div>
                <div class="line"></div>
                <div class="name"><?= htmlspecialchars($nama) ?></div>
                <div style="font-size: 0.9rem; color: #666;">NIP. <?= htmlspecialchars($nip) ?></div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    console.log('=== Laporan Semester Script Loaded ===');
    console.log('👨‍🏫 Wali Kelas:', '<?php echo $nama; ?>');
    console.log('🏫 Kelas:', '<?php echo $kelas_wali; ?>');
    console.log('📚 Jurusan:', '<?php echo $jurusan_wali; ?>');
    
    const currentUserId = <?php echo $current_user_id; ?>;

    function resetFilter() {
        console.log('Reset filter');
        window.location.href = window.location.pathname + '?user_id=' + currentUserId;
    }

    console.log('Initialization complete');
</script>

</body>
</html>