<?php
require_once '../auth_user/auth_check_yayasan.php';

// Koneksi database
include '../../db.php';

$ketua_id = $_SESSION['user_id'];
$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);

// Statistik Dashboard
$query_guru = "SELECT COUNT(*) AS total FROM guru";
$total_guru = mysqli_fetch_assoc(mysqli_query($conn, $query_guru))['total'];

$query_siswa = "SELECT COUNT(*) AS total FROM siswa";
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa))['total'];

$today = date('Y-m-d');

// PERBAIKAN: Bersihkan session guru yang sudah dihapus dari database
if (isset($_SESSION['logged_users']['guru']) && !empty($_SESSION['logged_users']['guru'])) {
    $valid_guru_sessions = [];
    
    foreach ($_SESSION['logged_users']['guru'] as $user_id => $session_data) {
        $check_query = "SELECT id FROM guru WHERE id = " . intval($user_id);
        $check_result = mysqli_query($conn, $check_query);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $valid_guru_sessions[$user_id] = $session_data;
        }
    }
    
    if (empty($valid_guru_sessions)) {
        unset($_SESSION['logged_users']['guru']);
    } else {
        $_SESSION['logged_users']['guru'] = $valid_guru_sessions;
    }
}

// Ambil data guru online dari session
$guru_online_data = isset($_SESSION['logged_users']['guru']) ? $_SESSION['logged_users']['guru'] : [];
$total_guru_online = count($guru_online_data);

// Query absensi hari ini
$query_absensi_today = "SELECT COUNT(*) AS total FROM absensi WHERE DATE(tanggal) = '$today'";
$result_absensi = mysqli_query($conn, $query_absensi_today);
if (!$result_absensi) {
    $query_absensi_today = "SELECT COUNT(*) AS total FROM absensi WHERE DATE(created_at) = '$today'";
    $result_absensi = mysqli_query($conn, $query_absensi_today);
}
$total_absensi_today = $result_absensi ? mysqli_fetch_assoc($result_absensi)['total'] : 0;

// Statistik kehadiran hari ini
$query_hadir = "SELECT COUNT(*) AS total FROM absensi WHERE DATE(tanggal) = '$today' AND status = 'hadir'";
$result_hadir = mysqli_query($conn, $query_hadir);
if (!$result_hadir) {
    $query_hadir = "SELECT COUNT(*) AS total FROM absensi WHERE DATE(created_at) = '$today' AND status = 'hadir'";
    $result_hadir = mysqli_query($conn, $query_hadir);
}
$total_hadir = $result_hadir ? mysqli_fetch_assoc($result_hadir)['total'] : 0;

$persentase_kehadiran = $total_siswa > 0 ? round(($total_hadir / $total_siswa) * 100, 1) : 0;

// Ambil data aktivitas guru
$query_guru_activity = "SELECT id, nama, nip FROM guru ORDER BY nama ASC";
$result_guru_activity = mysqli_query($conn, $query_guru_activity);
$guru_activities = [];

$guru_online_ids = array_keys($guru_online_data);

while ($row = mysqli_fetch_assoc($result_guru_activity)) {
    $is_online = in_array($row['id'], $guru_online_ids);
    
    $guru_activities[] = [
        'id' => $row['id'],
        'nama' => $row['nama'],
        'nip' => $row['nip'],
        'status' => $is_online ? 'online' : 'offline'
    ];
}

// Urutkan: Online dulu, baru offline
usort($guru_activities, function($a, $b) {
    if ($a['status'] === $b['status']) {
        return strcmp($a['nama'], $b['nama']);
    }
    return ($a['status'] === 'online') ? -1 : 1;
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ketua Yayasan - EduAttend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">

   <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
        }

        /* Main Container */
        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Section */
        .header-section {
            margin-bottom: 2.5rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 0.25rem;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            font-size: 0.9375rem;
            color: #86868b;
            font-weight: 400;
        }

        /* Stats Grid */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #d2d2d7;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #86868b;
            font-weight: 500;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #1d1d1f;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-description {
            font-size: 0.875rem;
            color: #86868b;
        }

        /* Attendance Progress */
        .attendance-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 2rem;
        }

        .attendance-label {
            font-size: 0.875rem;
            color: #86868b;
            font-weight: 500;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attendance-percent {
            font-size: 4rem;
            font-weight: 600;
            color: #1d1d1f;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .progress-bar-custom {
            height: 8px;
            background: #f5f5f7;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #1d1d1f 0%, #424245 100%);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        /* Activity Monitor */
        .activity-section {
            width: 100%;
        }

        .activity-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e5e5e7;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f5f5f7;
        }

        .activity-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1d1d1f;
        }

        .activity-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #86868b;
        }

        .online-indicator {
            width: 8px;
            height: 8px;
            background: #30d158;
            border-radius: 50%;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .activity-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-list::-webkit-scrollbar {
            width: 6px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: #f5f5f7;
            border-radius: 10px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: #d2d2d7;
            border-radius: 10px;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f5f5f7;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info {
            flex: 1;
        }

        .activity-name {
            font-size: 0.9375rem;
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 0.25rem;
        }

        .activity-nip {
            font-size: 0.8125rem;
            color: #86868b;
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .status-badge.online {
            background: #d7f5e3;
            color: #0e6b31;
        }

        .status-badge.offline {
            background: #f5f5f7;
            color: #86868b;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-dot.online {
            background: #30d158;
            animation: pulse 2s ease-in-out infinite;
        }

        .status-dot.offline {
            background: #86868b;
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.6; 
                transform: scale(1.2);
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #86868b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .header-title {
                font-size: 1.5rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .attendance-percent {
                font-size: 3rem;
            }

            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .status-badge {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="header-section">
            <h1 class="header-title">Selamat Datang, <?php echo htmlspecialchars($profile['nama']); ?></h1>
            <p class="header-subtitle"><?php echo strftime('%A, %d %B %Y', strtotime($today)); ?></p>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Guru</div>
                <div class="stat-value"><?php echo number_format($total_guru); ?></div>
                <div class="stat-description">Terdaftar dalam sistem</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Guru Online</div>
                <div class="stat-value"><?php echo number_format($total_guru_online); ?></div>
                <div class="stat-description">Sedang aktif di sistem</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Siswa</div>
                <div class="stat-value"><?php echo number_format($total_siswa); ?></div>
                <div class="stat-description">Terdaftar dalam sistem</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Kehadiran Hari Ini</div>
                <div class="stat-value"><?php echo number_format($total_hadir); ?></div>
                <div class="stat-description">Dari <?php echo number_format($total_siswa); ?> siswa</div>
            </div>
        </div>

        <!-- Attendance Progress -->
        <div class="attendance-card">
            <div class="attendance-label">Persentase Kehadiran Siswa Hari Ini</div>
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="attendance-percent"><?php echo $persentase_kehadiran; ?>%</div>
                </div>
                <div class="col-md-9">
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $persentase_kehadiran; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Monitor -->
        <div class="activity-section">
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Status Aktivitas Guru</h3>
                    <div class="activity-count">
                        <span class="online-indicator"></span>
                        <span><?php echo $total_guru_online; ?> dari <?php echo $total_guru; ?> online</span>
                    </div>
                </div>
                <div class="activity-list">
                    <?php if (empty($guru_activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <p>Belum ada data guru</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($guru_activities as $guru): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-name"><?php echo htmlspecialchars($guru['nama']); ?></div>
                                    <div class="activity-nip">NIP: <?php echo htmlspecialchars($guru['nip']); ?></div>
                                </div>
                                <div class="status-badge <?php echo $guru['status']; ?>">
                                    <span class="status-dot <?php echo $guru['status']; ?>"></span>
                                    <?php echo $guru['status'] === 'online' ? 'Online' : 'Offline'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh setiap 30 detik untuk update status online
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>