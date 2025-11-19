<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: ../../auth/login_user.php?role=guru");
    exit();
}

$user_data = $_SESSION['logged_users']['guru'][$current_user_id];
$user_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];

include '../../db.php';
include '../auth_user/auth_check_guru.php'; 


// Cek apakah guru adalah wali kelas
$tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
$wali_query = "SELECT wk.*, g.nama as nama_guru FROM wali_kelas wk 
               JOIN guru g ON wk.guru_id = g.id 
               WHERE wk.guru_id = ? AND wk.tahun_ajaran = ?";
$stmt = mysqli_prepare($conn, $wali_query);
mysqli_stmt_bind_param($stmt, "is", $user_id, $tahun_ajaran);
mysqli_stmt_execute($stmt);
$wali_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($wali_result) == 0) {
    die("<div style='display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial;'>
        <div style='text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
            <i class='fas fa-exclamation-triangle' style='font-size: 4rem; color: #e74c3c; margin-bottom: 20px;'></i>
            <h2 style='color: #2c3e50; margin-bottom: 10px;'>Akses Ditolak</h2>
            <p style='color: #7f8c8d;'>Anda bukan wali kelas. Halaman ini hanya dapat diakses oleh wali kelas.</p>
            <a href='dashboard_guru.php?user_id=$current_user_id' style='display: inline-block; margin-top: 20px; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>
                Kembali ke Dashboard
            </a>
        </div>
    </div>");
}

$wali_data = mysqli_fetch_assoc($wali_result);
$kelas_wali = $wali_data['kelas'];

$kelas_parts = explode(' ', $kelas_wali);
$jurusan_wali = isset($kelas_parts[1]) ? $kelas_parts[1] : '-';

// Ambil filter dari form
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$mata_pelajaran = isset($_GET['mata_pelajaran']) ? $_GET['mata_pelajaran'] : '';

// Ambil daftar mata pelajaran untuk filter
$mapel_query = "SELECT DISTINCT mata_pelajaran FROM absensi WHERE kelas = ? ORDER BY mata_pelajaran";
$stmt_mapel = mysqli_prepare($conn, $mapel_query);
mysqli_stmt_bind_param($stmt_mapel, "s", $kelas_wali);
mysqli_stmt_execute($stmt_mapel);
$mapel_result = mysqli_stmt_get_result($stmt_mapel);

// Ambil daftar siswa di kelas
$siswa_query = "SELECT id, nis, nama FROM siswa WHERE kelas = ? ORDER BY nama";
$stmt_siswa = mysqli_prepare($conn, $siswa_query);
mysqli_stmt_bind_param($stmt_siswa, "s", $kelas_wali);
mysqli_stmt_execute($stmt_siswa);
$siswa_result = mysqli_stmt_get_result($stmt_siswa);
$siswa_list = [];
while ($row = mysqli_fetch_assoc($siswa_result)) {
    $siswa_list[] = $row;
}

// Generate hari dalam bulan sesuai jumlah hari aktual
$first_day = $bulan . '-01';
$last_day = date('Y-m-t', strtotime($first_day));
$jumlah_hari = date('t', strtotime($first_day)); // Ambil jumlah hari sebenarnya (28-31)

// Ambil data absensi
$absensi_data = [];
if ($mata_pelajaran) {
    $absensi_query = "SELECT student_id, tanggal, status 
                      FROM absensi 
                      WHERE kelas = ? 
                      AND mata_pelajaran = ?
                      AND tanggal BETWEEN ? AND ?
                      ORDER BY tanggal";
    $stmt_absensi = mysqli_prepare($conn, $absensi_query);
    mysqli_stmt_bind_param($stmt_absensi, "ssss", $kelas_wali, $mata_pelajaran, $first_day, $last_day);
} else {
    $absensi_query = "SELECT student_id, tanggal, status 
                      FROM absensi 
                      WHERE kelas = ? 
                      AND tanggal BETWEEN ? AND ?
                      ORDER BY tanggal";
    $stmt_absensi = mysqli_prepare($conn, $absensi_query);
    mysqli_stmt_bind_param($stmt_absensi, "sss", $kelas_wali, $first_day, $last_day);
}

mysqli_stmt_execute($stmt_absensi);
$absensi_result = mysqli_stmt_get_result($stmt_absensi);

while ($row = mysqli_fetch_assoc($absensi_result)) {
    $student_id = $row['student_id'];
    $tanggal = date('j', strtotime($row['tanggal'])); // Hanya ambil tanggal (1-31)
    $status = $row['status'];
    
    if (!isset($absensi_data[$student_id])) {
        $absensi_data[$student_id] = [];
    }
    $absensi_data[$student_id][$tanggal] = $status;
}

// Fungsi untuk cek hari libur (Sabtu, Minggu, dan hari libur nasional Indonesia)
function isHoliday($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 (Monday) to 7 (Sunday)
    
    // Sabtu (6) atau Minggu (7)
    if ($dayOfWeek == 6 || $dayOfWeek == 7) {
        return true;
    }
    
    // Hari libur nasional Indonesia 2024-2025
    $holidays = [
        '2024-01-01', // Tahun Baru
        '2024-02-08', // Isra Miraj
        '2024-02-10', // Tahun Baru Imlek
        '2024-02-14', // Pemilu
        '2024-03-11', // Hari Suci Nyepi
        '2024-03-29', // Wafat Isa Al-Masih
        '2024-03-31', // Hari Raya Idul Fitri
        '2024-04-01', // Hari Raya Idul Fitri
        '2024-04-11', // Cuti Bersama Idul Fitri
        '2024-05-01', // Hari Buruh
        '2024-05-09', // Kenaikan Isa Al-Masih
        '2024-05-10', // Cuti Bersama
        '2024-05-23', // Hari Raya Waisak
        '2024-06-01', // Hari Lahir Pancasila
        '2024-06-17', // Hari Raya Idul Adha
        '2024-06-18', // Cuti Bersama Idul Adha
        '2024-07-07', // Tahun Baru Islam
        '2024-08-17', // Hari Kemerdekaan RI
        '2024-09-16', // Maulid Nabi Muhammad
        '2024-12-25', // Hari Raya Natal
        '2024-12-26', // Cuti Bersama Natal
        '2025-01-01', // Tahun Baru
        '2025-01-29', // Tahun Baru Imlek
        '2025-02-27', // Isra Miraj
        '2025-03-14', // Hari Suci Nyepi
        '2025-03-29', // Hari Raya Idul Fitri
        '2025-03-30', // Hari Raya Idul Fitri
        '2025-03-31', // Cuti Bersama Idul Fitri
        '2025-04-01', // Cuti Bersama Idul Fitri
        '2025-04-18', // Wafat Isa Al-Masih
        '2025-05-01', // Hari Buruh
        '2025-05-12', // Hari Raya Waisak
        '2025-05-29', // Kenaikan Isa Al-Masih
        '2025-06-01', // Hari Lahir Pancasila
        '2025-06-06', // Hari Raya Idul Adha
        '2025-06-27', // Tahun Baru Islam
        '2025-08-17', // Hari Kemerdekaan RI
        '2025-09-05', // Maulid Nabi Muhammad
        '2025-12-25', // Hari Raya Natal
    ];
    
    $dateString = date('Y-m-d', $timestamp);
    return in_array($dateString, $holidays);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi Per Siswa - <?php echo htmlspecialchars($kelas_wali); ?></title>
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
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .header .info {
            font-size: 1rem;
            opacity: 0.95;
        }

        /* Print Elements - Hidden by default */
        .print-logo,
        .print-school-info,
        .print-report-title,
        .print-info-table,
        .print-signature,
        .print-footer {
            display: none;
        }

        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 0.85rem;
        }

        .attendance-table thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .attendance-table th {
            padding: 12px 6px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }

        /* Override untuk header tanggal - pastikan ukuran sama */
        .attendance-table thead tr:nth-child(2) th {
            padding: 8px 2px !important;
        }

        .attendance-table td {
            padding: 10px 6px;
            text-align: center;
            border: 1px solid #dee2e6;
        }

        .attendance-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .attendance-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        /* Kolom siswa fixed */
        .attendance-table th:nth-child(1),
        .attendance-table th:nth-child(2),
        .attendance-table th:nth-child(3),
        .attendance-table td:nth-child(1),
        .attendance-table td:nth-child(2),
        .attendance-table td:nth-child(3) {
            position: sticky;
            background: white;
            z-index: 5;
        }

        .attendance-table thead th:nth-child(1),
        .attendance-table thead th:nth-child(2),
        .attendance-table thead th:nth-child(3) {
            background: #2c3e50;
            z-index: 11;
        }

        .attendance-table th:nth-child(1),
        .attendance-table td:nth-child(1) {
            left: 0;
            min-width: 50px;
            width: 50px;
        }

        .attendance-table th:nth-child(2),
        .attendance-table td:nth-child(2) {
            left: 50px;
            min-width: 100px;
            width: 100px;
        }

        .attendance-table th:nth-child(3),
        .attendance-table td:nth-child(3) {
            left: 150px;
            min-width: 200px;
            width: 200px;
            text-align: left;
            padding-left: 15px;
        }

        /* Kolom tanggal - ukuran seragam dan lebih kecil */
        .attendance-table .date-col {
            min-width: 32px !important;
            width: 32px !important;
            max-width: 32px !important;
            padding: 8px 2px !important;
            text-align: center !important;
        }

        /* Kolom summary - ukuran seragam */
        .attendance-table .summary-col {
            min-width: 42px !important;
            width: 42px !important;
            max-width: 42px !important;
            font-weight: 600;
            text-align: center !important;
        }

        /* PENTING: Semua kolom dari kolom ke-4 dan seterusnya (tanggal + summary) ukuran sama */
        .attendance-table th:nth-child(n+4),
        .attendance-table td:nth-child(n+4) {
            width: 32px !important;
            min-width: 32px !important;
            max-width: 32px !important;
            padding: 8px 2px !important;
        }

        /* Override khusus untuk kolom % agar sedikit lebih lebar */
        .attendance-table th:last-child,
        .attendance-table td:last-child {
            width: 48px !important;
            min-width: 48px !important;
            max-width: 48px !important;
        }

        /* Highlight hari libur */
        .holiday-col {
            background-color: #ffe6e6 !important;
            color: #c92a2a !important;
        }

        .status-H { background-color: #d4edda; color: #155724; font-weight: bold; }
        .status-A { background-color: #f8d7da; color: #721c24; font-weight: bold; }
        .status-S { background-color: #fff3cd; color: #856404; font-weight: bold; }
        .status-I { background-color: #d1ecf1; color: #0c5460; font-weight: bold; }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-custom {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-print {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
        }

        .btn-print:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
        }

        .btn-excel {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            border: none;
        }

        .btn-excel:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
            transform: translateY(-2px);
        }

        /* PRINT STYLES */
        @media print {
            .filter-section,
            .action-buttons,
            .sidebar,
            .header {
                display: none !important;
            }
            
            @page {
                size: A4 landscape;
                margin: 6mm 4mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 4px !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                width: 100%;
            }

            /* Show print elements */
            .print-school-info {
                display: block !important;
                text-align: center;
                margin-bottom: 6px;
                padding-bottom: 5px;
                border-bottom: 2px solid #000;
            }

            .print-school-name {
                font-size: 1.1rem;
                font-weight: bold;
                color: #000;
                margin-bottom: 3px;
                text-transform: uppercase;
            }

            .print-school-address {
                font-size: 0.65rem;
                color: #000;
                line-height: 1.3;
            }

            .print-logo-circle {
                width: 50px;
                height: 50px;
                margin: 0 auto 5px;
                background: #2c3e50;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.5rem;
            }

            .print-report-title {
                display: block !important;
                font-size: 0.85rem;
                font-weight: bold;
                text-align: center;
                margin: 6px 0 5px 0;
                color: #000;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .print-info-table {
                display: table !important;
                width: 100%;
                margin-bottom: 6px;
                font-size: 0.65rem;
            }

            .print-info-row {
                display: table-row;
            }

            .print-info-cell {
                display: table-cell;
                padding: 1px 0;
            }

            .print-info-label {
                width: 110px;
                font-weight: bold;
            }

            .table-wrapper {
                overflow: visible !important;
                box-shadow: none !important;
                margin-bottom: 0 !important;
            }

            .attendance-table {
                font-size: 0.5rem !important;
                page-break-inside: auto;
                width: 100% !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
            }

            .attendance-table thead {
                background: #2c3e50 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .attendance-table thead th {
                background: #2c3e50 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .attendance-table th {
                padding: 3px 2px !important;
                border: 0.5px solid #000 !important;
                font-size: 0.5rem !important;
                white-space: nowrap;
                text-align: center;
                position: static !important;
                left: auto !important;
                background: #2c3e50 !important;
                color: white !important;
            }

            .attendance-table td {
                padding: 3px 2px !important;
                border: 0.5px solid #000 !important;
                font-size: 0.5rem !important;
                text-align: center;
                position: static !important;
                left: auto !important;
            }

            /* Kolom No - lebih kecil */
            .attendance-table th:nth-child(1),
            .attendance-table td:nth-child(1) {
                width: 5px !important;
                min-width: 5px !important;
                max-width: 5px !important;
            }

            /* Kolom NIS - lebih kecil */
            .attendance-table th:nth-child(2),
            .attendance-table td:nth-child(2) {
                width: 15px !important;
                min-width: 15px !important;
                max-width: 15px !important;
            }

            /* Kolom Nama - lebih kecil */
            .attendance-table th:nth-child(3),
            .attendance-table td:nth-child(3) {
                width: 15px !important;
                min-width: 15px !important;
                max-width: 15px !important;
                text-align: left !important;
                padding-left: 4px !important;
                font-size: 0.48rem !important;
            }

            /* PENTING: Semua kolom dari kolom ke-4 hingga terakhir (tanggal + summary) ukuran sama dan lebih lebar */
            .attendance-table th:nth-child(n+4),
            .attendance-table td:nth-child(n+4) {
                width: 35px !important;
                min-width: 35px !important;
                max-width: 35px !important;
                padding: 4px 2px !important;
            }

            /* Override khusus untuk kolom % agar sedikit lebih lebar di print */
            .attendance-table th:last-child,
            .attendance-table td:last-child {
                width: 5px !important;
                min-width: 5px !important;
                max-width: 5px !important;
            }

            /* Highlight hari libur di print */
            .holiday-col {
                background-color: #ffe6e6 !important;
                color: #c92a2a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .attendance-table tbody tr:nth-child(even) {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Color coding for status */
            .status-H { 
                background-color: #d4edda !important; 
                color: #155724 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-weight: bold !important;
            }
            .status-A { 
                background-color: #f8d7da !important; 
                color: #721c24 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-weight: bold !important;
            }
            .status-S { 
                background-color: #fff3cd !important; 
                color: #856404 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-weight: bold !important;
            }
            .status-I { 
                background-color: #d1ecf1 !important; 
                color: #0c5460 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-weight: bold !important;
            }

            .print-signature {
                display: table !important;
                width: 100%;
                margin-top: 15px;
                page-break-inside: avoid;
                font-size: 0.65rem;
            }

            .signature-row {
                display: table-row;
            }

            .signature-cell {
                display: table-cell;
                text-align: center;
                padding: 8px;
                vertical-align: top;
            }

            .signature-left { width: 50%; }
            .signature-right { width: 50%; }

            .signature-title {
                font-weight: bold;
                margin-bottom: 35px;
            }

            .signature-line {
                border-bottom: 1px solid #000;
                width: 120px;
                margin: 0 auto 3px;
            }

            .signature-name {
                font-weight: 600;
            }

            .signature-nip {
                font-size: 0.6rem;
            }

            .print-footer {
                display: block !important;
                text-align: center;
                margin-top: 10px;
                padding-top: 6px;
                border-top: 1px solid #000;
                font-size: 0.58rem;
                color: #666;
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
        }

        @media (max-width: 768px) {
            .main-content {
                margin: 0;
                padding: 15px;
                border-radius: 0;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_guru.php'; ?>

    <div class="main-content">
        <!-- Screen Header -->
        <div class="header">
            <h1><i class="fas fa-user-graduate"></i> Laporan Absensi Per Siswa</h1>
            <div class="info">
                <i class="fas fa-chalkboard-teacher"></i> Wali Kelas: <strong><?php echo htmlspecialchars($nama); ?></strong> | 
                Kelas: <strong><?php echo htmlspecialchars($kelas_wali . ' - ' . $jurusan_wali); ?></strong> |
                Tahun Ajaran: <strong><?php echo $tahun_ajaran; ?></strong>
            </div>
        </div>

        <!-- Print Header (hidden on screen) -->
        <div class="print-school-info">
            <div class="print-logo-circle">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="print-school-name">SMA PGRI 2 Padang</div>
            <div class="print-school-address">
                Jl. Linggar Jati no.1 Lubuk Begalung, Sumatera Barat 25173<br>
                Telepon: (0751) 72293 | Email: smapgri2padang@kemdikbud.go.id
            </div>
        </div>

        <div class="print-report-title">
            Laporan Kehadiran Siswa Per Hari - Kelas <?php echo htmlspecialchars($kelas_wali); ?><br>
            Periode: <?php echo date('F Y', strtotime($bulan)); ?> (<?php echo $jumlah_hari; ?> Hari)
        </div>

        <div class="print-info-table">
            <div class="print-info-row">
                <div class="print-info-cell print-info-label">Wali Kelas</div>
                <div class="print-info-cell">: <?php echo htmlspecialchars($nama); ?></div>
            </div>
            <div class="print-info-row">
                <div class="print-info-cell print-info-label">NIP</div>
                <div class="print-info-cell">: <?php echo htmlspecialchars($nip); ?></div>
            </div>
            <div class="print-info-row">
                <div class="print-info-cell print-info-label">Kelas/Jurusan</div>
                <div class="print-info-cell">: <?php echo htmlspecialchars($kelas_wali . ' - ' . $jurusan_wali); ?></div>
            </div>
            <div class="print-info-row">
                <div class="print-info-cell print-info-label">Tahun Ajaran</div>
                <div class="print-info-cell">: <?php echo htmlspecialchars($tahun_ajaran); ?></div>
            </div>
            <div class="print-info-row">
                <div class="print-info-cell print-info-label">Mata Pelajaran</div>
                <div class="print-info-cell">: <?php echo $mata_pelajaran ? htmlspecialchars($mata_pelajaran) : 'Semua Mata Pelajaran'; ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h5 class="mb-3"><i class="fas fa-filter"></i> Filter Laporan</h5>
            <form method="GET" class="row g-3">
                <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">
                
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Bulan</label>
                    <input type="month" name="bulan" class="form-control" value="<?php echo $bulan; ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-book"></i> Mata Pelajaran</label>
                    <select name="mata_pelajaran" class="form-select">
                        <option value="">Semua Mata Pelajaran</option>
                        <?php 
                        mysqli_data_seek($mapel_result, 0);
                        while ($mapel = mysqli_fetch_assoc($mapel_result)): 
                        ?>
                            <option value="<?php echo htmlspecialchars($mapel['mata_pelajaran']); ?>" 
                                    <?php echo ($mata_pelajaran == $mapel['mata_pelajaran']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mapel['mata_pelajaran']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-custom btn-print w-100">
                        <i class="fas fa-search"></i> Tampilkan Laporan
                    </button>
                </div>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-custom btn-print">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
            <button onclick="exportToExcel()" class="btn btn-custom btn-excel">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>

        <!-- Info Hari -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Informasi:</strong> Bulan ini memiliki <strong><?php echo $jumlah_hari; ?> hari</strong>. 
            Kolom berwarna merah adalah hari libur (Sabtu/Minggu/Hari Libur Nasional).
        </div>

        <!-- Tabel Absensi -->
        <div class="table-wrapper">
            <table class="attendance-table" id="attendanceTable">
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">NIS</th>
                        <th rowspan="2">Nama Siswa</th>
                        <th colspan="<?php echo $jumlah_hari; ?>">Tanggal</th>
                        <th colspan="4">Total</th>
                        <th rowspan="2">%</th>
                    </tr>
                    <tr>
                        <?php for ($i = 1; $i <= $jumlah_hari; $i++): 
                            $currentDate = $bulan . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                            $isHolidayDay = isHoliday($currentDate);
                            $holidayClass = $isHolidayDay ? 'date-col holiday-col' : 'date-col';
                        ?>
                            <th class="<?php echo $holidayClass; ?>"><?php echo $i; ?></th>
                        <?php endfor; ?>
                        <th class="summary-col">H</th>
                        <th class="summary-col">A</th>
                        <th class="summary-col">S</th>
                        <th class="summary-col">I</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($siswa_list as $siswa): 
                        $student_id = $siswa['id'];
                        
                        // Hitung total per status
                        $total_hadir = 0;
                        $total_alpha = 0;
                        $total_sakit = 0;
                        $total_izin = 0;
                        
                        for ($i = 1; $i <= $jumlah_hari; $i++) {
                            if (isset($absensi_data[$student_id][$i])) {
                                switch ($absensi_data[$student_id][$i]) {
                                    case 'hadir': $total_hadir++; break;
                                    case 'alpha': $total_alpha++; break;
                                    case 'sakit': $total_sakit++; break;
                                    case 'izin': $total_izin++; break;
                                }
                            }
                        }
                        
                        $total_absensi = $total_hadir + $total_alpha + $total_sakit + $total_izin;
                        $persentase_hadir = ($total_absensi > 0) ? round(($total_hadir / $total_absensi) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                        <td style="text-align: left; padding-left: 15px;"><?php echo htmlspecialchars($siswa['nama']); ?></td>
                        
                        <?php for ($i = 1; $i <= $jumlah_hari; $i++): 
                            $currentDate = $bulan . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                            $isHolidayDay = isHoliday($currentDate);
                            
                            $status = isset($absensi_data[$student_id][$i]) ? $absensi_data[$student_id][$i] : '';
                            $symbol = '';
                            $class = 'date-col';
                            
                            if ($isHolidayDay) {
                                $class .= ' holiday-col';
                            }
                            
                            switch ($status) {
                                case 'hadir':
                                    $symbol = 'H';
                                    $class .= ' status-H';
                                    break;
                                case 'alpha':
                                    $symbol = 'A';
                                    $class .= ' status-A';
                                    break;
                                case 'sakit':
                                    $symbol = 'S';
                                    $class .= ' status-S';
                                    break;
                                case 'izin':
                                    $symbol = 'I';
                                    $class .= ' status-I';
                                    break;
                                default:
                                    $symbol = '-';
                            }
                        ?>
                            <td class="<?php echo $class; ?>">
                                <?php echo $symbol; ?>
                            </td>
                        <?php endfor; ?>
                        
                        <td class="summary-col"><?php echo $total_hadir; ?></td>
                        <td class="summary-col"><?php echo $total_alpha; ?></td>
                        <td class="summary-col"><?php echo $total_sakit; ?></td>
                        <td class="summary-col"><?php echo $total_izin; ?></td>
                        <td class="summary-col"><?php echo $persentase_hadir; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Print Signature -->
        <div class="print-signature">
            <div class="signature-row">
                <div class="signature-cell signature-left">
                    <div class="signature-title">Mengetahui,<br>Kepala Sekolah</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">Ilham Akbar, S.Kom</div>
                    <div class="signature-nip">NIP. 196701011990031004</div>
                </div>
                <div class="signature-cell signature-right">
                    <div class="signature-title">Padang, <?php echo date('d F Y'); ?><br>Wali Kelas</div>
                    <div class="signature-line"></div>
                    <div class="signature-name"><?php echo htmlspecialchars($nama); ?></div>
                    <div class="signature-nip">NIP. <?php echo htmlspecialchars($nip); ?></div>
                </div>
            </div>
        </div>

        <div class="print-footer">
            Dicetak pada: <?php echo date('d F Y, H:i'); ?> WIB | Sistem Absensi - SMA PGRI 2 Padang
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <script>
        // Deteksi jumlah hari untuk optimization print
        const jumlahHari = <?php echo $jumlah_hari; ?>;
        const totalSiswa = <?php echo count($siswa_list); ?>;
        
        console.log('📊 Informasi Tabel:');
        console.log('  - Jumlah Hari:', jumlahHari);
        console.log('  - Total Siswa:', totalSiswa);
        console.log('  - Total Kolom:', 3 + jumlahHari + 5); // No, NIS, Nama + Days + H,A,S,I,%
        
        // Warning jika terlalu banyak kolom
        const totalKolom = 3 + jumlahHari + 5;
        if (totalKolom > 40) {
            console.warn('⚠️ PERHATIAN: Tabel memiliki', totalKolom, 'kolom');
            console.warn('💡 TIPS: Gunakan A3 Landscape atau pertimbangkan filter mata pelajaran');
        }
        
        function exportToExcel() {
            // Buat workbook baru
            const wb = XLSX.utils.book_new();
            
            // Data header sekolah
            const schoolData = [
                ['SMA PGRI 2 PADANG'],
                ['Jl. Linggar Jati no.1 Lubuk Begalung, Sumatera Barat 25173'],
                ['Telepon: (0751) 72293 | Email: smapgri2padang@kemdikbud.go.id'],
                [''],
                ['LAPORAN KEHADIRAN SISWA PER HARI'],
                ['Periode: <?php echo date('F Y', strtotime($bulan)); ?> (<?php echo $jumlah_hari; ?> Hari)'],
                [''],
                ['Wali Kelas', ': <?php echo htmlspecialchars($nama); ?>'],
                ['NIP', ': <?php echo htmlspecialchars($nip); ?>'],
                ['Kelas/Jurusan', ': <?php echo htmlspecialchars($kelas_wali . ' - ' . $jurusan_wali); ?>'],
                ['Tahun Ajaran', ': <?php echo htmlspecialchars($tahun_ajaran); ?>'],
                ['Mata Pelajaran', ': <?php echo $mata_pelajaran ? htmlspecialchars($mata_pelajaran) : "Semua Mata Pelajaran"; ?>'],
                ['']
            ];

            // Buat worksheet dari tabel
            const table = document.getElementById('attendanceTable');
            const ws = XLSX.utils.table_to_sheet(table);
            
            // Insert school data di awal
            XLSX.utils.sheet_add_aoa(ws, schoolData, {origin: 'A1'});
            
            // Styling untuk Excel
            const range = XLSX.utils.decode_range(ws['!ref']);
            
            // Merge cells untuk header
            if (!ws['!merges']) ws['!merges'] = [];
            ws['!merges'].push(
                {s: {r: 0, c: 0}, e: {r: 0, c: 10}}, // Title
                {s: {r: 1, c: 0}, e: {r: 1, c: 10}}, // Address
                {s: {r: 2, c: 0}, e: {r: 2, c: 10}}, // Contact
                {s: {r: 4, c: 0}, e: {r: 4, c: 10}}  // Report title
            );
            
            // Set column widths
            ws['!cols'] = [
                {wch: 5},   // No
                {wch: 12},  // NIS
                {wch: 25},  // Nama
            ];
            
            // Tambah worksheet ke workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Laporan Absensi');
            
            // Generate filename
            const filename = `Laporan_Absensi_<?php echo str_replace(' ', '_', $kelas_wali); ?>_<?php echo $bulan; ?>.xlsx`;
            
            // Download file
            XLSX.writeFile(wb, filename);
            
            console.log('✅ Excel exported successfully');
        }

        console.log('✅ Laporan Absensi Per Siswa loaded');
        console.log('📊 Total Siswa:', totalSiswa);
        console.log('📅 Periode:', '<?php echo date('F Y', strtotime($bulan)); ?>');
        console.log('📆 Jumlah Hari:', jumlahHari);
        console.log('🏫 Kelas:', '<?php echo $kelas_wali; ?>');
    </script>
</body>
</html>