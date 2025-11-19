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

// Set variable lokal untuk halaman ini
$user_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];
$email = $user_data['email'];
$mata_pelajaran = $user_data['mata_pelajaran'];

include '../../db.php';
include '../auth_user/auth_check_guru.php'; 

$today = date('Y-m-d');
$current_time = date('H:i:s');

// Initialize variables
$recent_activities = [];
$schedules = [];
$weekly_stats = [];

// Day mapping untuk konversi hari
$day_mapping = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin', 
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_ini = $day_mapping[date('l')];

// Statistik Dasar
$stats = [
    'total_siswa' => 0,
    'qr_aktif' => 0,
    'kehadiran_hari_ini' => 0,
    'total_mata_pelajaran' => 0
];

// 1. Total Siswa
$query = "SELECT COUNT(DISTINCT s.id) as total 
          FROM siswa s
          INNER JOIN jadwal_guru jg ON s.kelas = jg.kelas 
          WHERE jg.nip = ? 
          AND jg.status = 'aktif'";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $nip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_siswa'] = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// 2. QR Code Aktif (berdasarkan jadwal guru yang sedang berlangsung)
$query = "SELECT COUNT(*) as total FROM jadwal_guru 
          WHERE nip = ? 
          AND hari = ?  
          AND status = 'aktif'
          AND jam_mulai <= ? 
          AND jam_selesai >= ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssss", $nip, $hari_ini, $current_time, $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stats['qr_aktif'] = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// 3. Persentase Kehadiran Hari Ini (berdasarkan mata pelajaran guru)
$query = "SELECT 
            (COUNT(CASE WHEN status = 'hadir' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as percentage
          FROM absensi 
          WHERE tanggal = ?
          AND mata_pelajaran = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $today, $mata_pelajaran);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stats['kehadiran_hari_ini'] = round($row['percentage'] ?? 0, 1);
    }
    mysqli_stmt_close($stmt);
}

// 4. Total Mata Pelajaran yang diampu guru
$query = "SELECT COUNT(DISTINCT mata_pelajaran) as total 
          FROM jadwal_guru 
          WHERE nip = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $nip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_mata_pelajaran'] = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// 5. Aktivitas Terbaru - PERBAIKAN: Gunakan created_at untuk jam absensi
$query = "SELECT 
            a.id,
            a.nis,
            a.nama,
            a.kelas,
            a.jurusan,
            a.mata_pelajaran,
            a.status,
            a.tanggal,
            a.created_at as waktu_absen,
            TIME(a.created_at) as jam_absen,
            s.nama as nama_siswa, 
            s.kelas as kelas_siswa,
            jg.jam_mulai as jam_mulai_pelajaran,
            jg.jam_selesai as jam_selesai_pelajaran
          FROM absensi a
          LEFT JOIN siswa s ON a.student_id = s.id
          LEFT JOIN jadwal_guru jg ON jg.nip = ? 
            AND jg.kelas = a.kelas
            AND jg.mata_pelajaran = a.mata_pelajaran
            AND jg.hari = ?
            AND jg.status = 'aktif'
          WHERE a.tanggal = ?
          AND a.mata_pelajaran = ?
          ORDER BY a.created_at DESC
          LIMIT 15";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssss", $nip, $hari_ini, $today, $mata_pelajaran);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_activities[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// 6. Jadwal Hari Ini untuk guru
$query = "SELECT *, 
          CASE 
            WHEN jam_mulai <= ? AND jam_selesai >= ? THEN 'berlangsung'
            WHEN jam_mulai > ? THEN 'upcoming'
            ELSE 'selesai'
          END as status_jadwal
          FROM jadwal_guru 
          WHERE nip = ? 
          AND hari = ?
          AND status = 'aktif'
          ORDER BY jam_mulai ASC";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sssss", $current_time, $current_time, $current_time, $nip, $hari_ini);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $schedules[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// 7. Statistik Mingguan (kehadiran per hari untuk mata pelajaran guru)
$query = "SELECT 
            DAYNAME(tanggal) as hari,
            COUNT(CASE WHEN status = 'hadir' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as percentage
          FROM absensi
          WHERE tanggal >= DATE_SUB(?, INTERVAL 7 DAY)
          AND mata_pelajaran = ?
          GROUP BY DAYNAME(tanggal), DATE(tanggal)
          ORDER BY tanggal";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $today, $mata_pelajaran);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $weekly_stats[$row['hari']] = round($row['percentage'], 1);
        }
    }
    mysqli_stmt_close($stmt);
}

// 8. Ringkasan Kehadiran Minggu Ini
$weekly_summary = [
    'rata_rata' => 0,
    'total_hadir' => 0,
    'total_tidak_hadir' => 0
];

$query = "SELECT 
            COUNT(CASE WHEN status = 'hadir' THEN 1 END) as total_hadir,
            COUNT(CASE WHEN status != 'hadir' THEN 1 END) as total_tidak_hadir,
            COUNT(CASE WHEN status = 'hadir' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as rata_rata
          FROM absensi
          WHERE tanggal >= DATE_SUB(?, INTERVAL 7 DAY)
          AND mata_pelajaran = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $today, $mata_pelajaran);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $weekly_summary = mysqli_fetch_assoc($result);
        $weekly_summary['rata_rata'] = round($weekly_summary['rata_rata'] ?? 0, 1);
        $weekly_summary['total_hadir'] = $weekly_summary['total_hadir'] ?? 0;
        $weekly_summary['total_tidak_hadir'] = $weekly_summary['total_tidak_hadir'] ?? 0;
    }
    mysqli_stmt_close($stmt);
}

// Function untuk menghitung keterlambatan
function calculateLateness($jam_absen, $jam_mulai_pelajaran) {
    if (!$jam_absen || !$jam_mulai_pelajaran) return null;
    
    $absen_time = strtotime($jam_absen);
    $mulai_time = strtotime($jam_mulai_pelajaran);
    
    if ($absen_time > $mulai_time) {
        $diff = $absen_time - $mulai_time;
        $minutes = round($diff / 60);
        return $minutes;
    }
    return 0;
}

// Function untuk format waktu relatif
function timeAgo($datetime) {
    $now = time();
    $ago = strtotime($datetime);
    $diff = $now - $ago;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $minutes = round($diff / 60);
        return $minutes . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' jam yang lalu';
    } else {
        return date('d M Y H:i', $ago);
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
   
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-content {
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .dashboard-content {
            margin-top: 25px;
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .activity-card, .schedule-card, .quick-actions-card, .attendance-summary-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .time-badge, .date-badge, .period-badge {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .activity-list, .schedule-list {
            padding: 15px 20px;
            min-height: 200px;
            max-height: 350px;
            overflow-y: auto;
        }

        .activity-item, .schedule-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child, .schedule-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .activity-icon.green {
            background: #d4edda;
            color: #155724;
        }

        .activity-icon.blue {
            background: #cce5ff;
            color: #0056b3;
        }

        .activity-icon.orange {
            background: #fff3cd;
            color: #856404;
        }

        .activity-icon.red {
            background: #f8d7da;
            color: #721c24;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 12px;
            color: #7f8c8d;
        }

        .activity-details {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }

        .lateness-badge {
            background: #ffeaa7;
            color: #d63031;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }

        .early-badge {
            background: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }

        .schedule-time {
            font-size: 12px;
            font-weight: 600;
            color: #7f8c8d;
            min-width: 80px;
        }

        .schedule-content {
            flex: 1;
            margin: 0 15px;
        }

        .schedule-subject {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .schedule-class {
            font-size: 12px;
            color: #7f8c8d;
        }

        .schedule-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 8px;
            font-weight: 600;
        }

        .schedule-status.berlangsung {
            background: #d4edda;
            color: #155724;
        }

        .schedule-status.upcoming {
            background: #fff3cd;
            color: #856404;
        }

        .schedule-status.selesai {
            background: #f8d7da;
            color: #721c24;
        }

        .schedule-item.current {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(155, 89, 182, 0.1) 100%);
            border-radius: 8px;
            padding: 12px;
            margin: 5px 0;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }

        .quick-action-btn.primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .quick-action-btn.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .quick-action-btn.success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .quick-action-btn.info {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
        }

        .attendance-chart {
            display: flex;
            align-items: end;
            justify-content: space-between;
            padding: 20px;
            height: 120px;
        }

        .chart-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .chart-bar {
            width: 30px;
            height: 80px;
            background: #f1f3f4;
            border-radius: 4px;
            position: relative;
            margin-bottom: 8px;
        }

        .bar-fill {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: linear-gradient(135deg, #27ae60, #229954);
            border-radius: 4px;
            transition: height 0.3s ease;
        }

        .chart-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .attendance-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px 20px;
            background: #f8f9fa;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .stat-desc {
            font-size: 12px;
            color: #7f8c8d;
        }

        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .dashboard-row {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_guru.php'; ?>
    
    <div class="main-content">
        <div class="welcome-card">
            <div class="welcome-title">Dashboard Guru</div>
            <div class="welcome-subtitle">
                Selamat datang, <?php echo htmlspecialchars($nama); ?>. 
                Kelola absensi mata pelajaran <strong><?php echo htmlspecialchars($mata_pelajaran); ?></strong>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_siswa']) ?></div>
                <div class="stat-label">Total Siswa</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="stat-number"><?= $stats['qr_aktif'] ?></div>
                <div class="stat-label">QR Code Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?= $stats['kehadiran_hari_ini'] ?>%</div>
                <div class="stat-label">Kehadiran Hari Ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number"><?= $stats['total_mata_pelajaran'] ?></div>
                <div class="stat-label">Mata Pelajaran</div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-row">
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Aktivitas Terbaru</h3>
                        <span class="time-badge"><?= date('H:i') ?> WIB</span>
                    </div>
                    <div class="activity-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <?php 
                                $jam_absen = $activity['jam_absen'];
                                $jam_mulai = $activity['jam_mulai_pelajaran'];
                                $lateness = calculateLateness($jam_absen, $jam_mulai);
                                
                                // Status icon color
                                $icon_color = 'green';
                                $icon = 'check';
                                if ($activity['status'] == 'alpha') {
                                    $icon_color = 'red';
                                    $icon = 'times';
                                } elseif ($activity['status'] == 'sakit') {
                                    $icon_color = 'orange';
                                    $icon = 'heartbeat';
                                } elseif ($activity['status'] == 'izin') {
                                    $icon_color = 'blue';
                                    $icon = 'hand-paper';
                                } elseif ($lateness !== null && $lateness > 0) {
                                    $icon_color = 'orange';
                                    $icon = 'clock';
                                }
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $icon_color ?>">
                                        <i class="fas fa-<?= $icon ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?= htmlspecialchars($activity['nama']) ?>
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($activity['kelas']) ?></span>
                                            
                                            <?php if ($activity['status'] == 'hadir' && $lateness !== null): ?>
                                                <?php if ($lateness > 0): ?>
                                                    <span class="lateness-badge">Terlambat <?= $lateness ?> menit</span>
                                                <?php else: ?>
                                                    <span class="early-badge">Tepat Waktu</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="fas fa-clock"></i> <strong><?= $jam_absen ? date('H:i', strtotime($jam_absen)) : '-' ?></strong> WIB
                                            | <?= ucfirst($activity['status']) ?>
                                            | <?= timeAgo($activity['waktu_absen']) ?>
                                        </div>
                                        <?php if ($jam_mulai): ?>
                                            <div class="activity-details">
                                                <i class="fas fa-calendar-check"></i> Jadwal: <?= date('H:i', strtotime($jam_mulai)) ?> - <?= date('H:i', strtotime($activity['jam_selesai_pelajaran'])) ?> WIB
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 10px;"></i>
                                <p>Belum ada aktivitas absensi hari ini untuk mata pelajaran <strong><?= htmlspecialchars($mata_pelajaran) ?></strong></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="schedule-card">
                    <div class="card-header">
                        <h3>Jadwal Hari Ini</h3>
                        <span class="date-badge"><?= date('d M Y') ?></span>
                    </div>
                    <div class="schedule-list">
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <div class="schedule-item <?= $schedule['status_jadwal'] == 'berlangsung' ? 'current' : '' ?>">
                                    <div class="schedule-time">
                                        <?= date('H:i', strtotime($schedule['jam_mulai'])) ?> - 
                                        <?= date('H:i', strtotime($schedule['jam_selesai'])) ?>
                                    </div>
                                    <div class="schedule-content">
                                        <div class="schedule-subject"><?= htmlspecialchars($schedule['mata_pelajaran']) ?></div>
                                        <div class="schedule-class">
                                            <?= htmlspecialchars($schedule['kelas']) ?> 
                                            <span class="badge bg-secondary"><?= htmlspecialchars($schedule['jurusan']) ?></span>
                                        </div>
                                    </div>
                                    <div class="schedule-status <?= $schedule['status_jadwal'] ?>">
                                        <?= ucfirst($schedule['status_jadwal']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">Tidak ada jadwal hari ini</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-row">
                <div class="quick-actions-card">
                    <div class="card-header">
                        <h3>Aksi Cepat</h3>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="qr_code.php?user_id=<?php echo $current_user_id; ?>" class="quick-action-btn primary">
                            <i class="fas fa-qrcode"></i>
                            Buat QR Code
                        </a>
                        <a href="absensi_siswa.php?user_id=<?php echo $current_user_id; ?>" class="quick-action-btn secondary">
                            <i class="fas fa-check-circle"></i>
                            Input Absensi
                        </a>
                        <a href="laporan_absensi.php?user_id=<?php echo $current_user_id; ?>" class="quick-action-btn success">
                            <i class="fas fa-file-alt"></i>
                            Buat Laporan
                        </a>
                        <a href="statistik_absensi.php?user_id=<?php echo $current_user_id; ?>" class="quick-action-btn info">
                            <i class="fas fa-chart-bar"></i>
                            Lihat Statistik
                        </a>
                    </div>
                </div>

                <div class="attendance-summary-card">
                    <div class="card-header">
                        <h3>Ringkasan Kehadiran</h3>
                        <span class="period-badge">Minggu Ini</span>
                    </div>

                    <div class="attendance-chart">
                        <?php
                        $days = ['Monday' => 'Sen', 'Tuesday' => 'Sel', 'Wednesday' => 'Rab', 'Thursday' => 'Kam', 'Friday' => 'Jum'];
                        foreach ($days as $day_en => $day_id):
                            $percentage = isset($weekly_stats[$day_en]) ? $weekly_stats[$day_en] : 0;
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar">
                                    <div class="bar-fill" style="height: <?= $percentage ?>%"></div>
                                </div>
                                <div class="chart-label"><?= $day_id ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="attendance-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= $weekly_summary['rata_rata'] ?>%</span>
                            <span class="stat-desc">Rata-rata</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($weekly_summary['total_hadir']) ?></span>
                            <span class="stat-desc">Hadir</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($weekly_summary['total_tidak_hadir']) ?></span>
                            <span class="stat-desc">Tidak Hadir</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('=== Dashboard Guru Loaded ===');
        console.log('Total aktivitas:', <?= count($recent_activities) ?>);
        console.log('Total jadwal:', <?= count($schedules) ?>);
    </script>
</body>
</html>