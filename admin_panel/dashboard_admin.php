<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}

include '../db.php';

// Ambil data admin yang sedang login
$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];

$query_admin = "SELECT nama_lengkap FROM users WHERE id = '$admin_id' LIMIT 1";
$result_admin = mysqli_query($conn, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['nama_lengkap'] ?? $admin_username;

// Statistik
$total_guru = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM guru"))['total'];
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa"))['total'];
$total_wali_kelas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM wali_kelas"))['total'];
$total_users = $total_guru + $total_siswa;

// Absensi hari ini
$today = date('Y-m-d');

// Cek struktur tabel absensi
$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM absensi");
$columns = [];
while ($col = mysqli_fetch_assoc($check_columns)) {
    $columns[] = $col['Field'];
}

$tanggal_col = in_array('tanggal', $columns) ? 'tanggal' : 'tanggal_absen';
$status_col = 'status';

$total_hadir_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi WHERE $tanggal_col = '$today' AND $status_col = 'hadir'"))['total'];

// Persentase kehadiran hari ini
$persentase_kehadiran_hari_ini = $total_siswa > 0 ? round(($total_hadir_hari_ini / $total_siswa) * 100, 1) : 0;

// Activity Login Terbaru (simulasi dari data yang ada)
$recent_logins = [];

// Ambil guru yang baru ditambahkan
$query_guru_recent = "SELECT id, nama, nip, 'Guru' as role, created_at FROM guru ORDER BY created_at DESC LIMIT 3";
$result_guru = mysqli_query($conn, $query_guru_recent);
if ($result_guru) {
    while ($row = mysqli_fetch_assoc($result_guru)) {
        $recent_logins[] = $row;
    }
}

// Ambil siswa yang baru ditambahkan
$query_siswa_recent = "SELECT id, nama, nis as nip, 'Siswa' as role, created_at FROM siswa ORDER BY created_at DESC LIMIT 2";
$result_siswa = mysqli_query($conn, $query_siswa_recent);
if ($result_siswa) {
    while ($row = mysqli_fetch_assoc($result_siswa)) {
        $recent_logins[] = $row;
    }
}

// Sort by created_at
usort($recent_logins, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to 5
$recent_logins = array_slice($recent_logins, 0, 5);

// Format waktu Indonesia
function timeAgo($datetime) {
    if (empty($datetime)) {
        return "Baru saja";
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return "Baru saja";
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 0) {
        return "Baru saja";
    }
    
    if ($diff < 60) {
        return $diff . " detik yang lalu";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " jam yang lalu";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " hari yang lalu";
    } elseif ($diff < 2592000) { 
        $weeks = floor($diff / 604800);
        return $weeks . " minggu yang lalu";
    } elseif ($diff < 31536000) { 
        $months = floor($diff / 2592000);
        return $months . " bulan yang lalu";
    } else {
        return date('d M Y', $timestamp);
    }
}

// Greeting berdasarkan waktu
$hour = date('H');
if ($hour < 12) {
    $greeting = "Selamat Pagi";
    $greeting_icon = "☀️";
} elseif ($hour < 15) {
    $greeting = "Selamat Siang";
    $greeting_icon = "🌤️";
} elseif ($hour < 18) {
    $greeting = "Selamat Sore";
    $greeting_icon = "🌅";
} else {
    $greeting = "Selamat Malam";
    $greeting_icon = "🌙";
}

$current_date = date('l, d F Y');
$date_indo = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu',
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

foreach ($date_indo as $eng => $indo) {
    $current_date = str_replace($eng, $indo, $current_date);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Absensi</title>
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
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 20px;
            padding: 2.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -5%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .greeting-text {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .admin-name {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #fff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .date-info {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .welcome-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.5rem;
        }

        .welcome-stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .welcome-stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .welcome-stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, transparent 100%);
            border-radius: 0 0 0 100%;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
            border-color: #d1d5db;
        }

        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .stat-icon-wrapper {
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .stat-icon.blue { 
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        .stat-icon.green { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .stat-icon.purple { 
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        .stat-icon.orange { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-body {
            position: relative;
            z-index: 1;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8125rem;
            font-weight: 600;
            margin-top: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
        }

        .stat-change.positive {
            background: #d1fae5;
            color: #065f46;
        }

        /* Two Column Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        /* Activity Section */
        .activity-section {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            border: 1px solid #e5e7eb;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .section-title-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }

        .view-all-link {
            font-size: 0.8125rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .view-all-link:hover {
            color: #2563eb;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .activity-item:hover {
            background: #f9fafb;
            border-color: #e5e7eb;
        }

        .activity-avatar {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(30, 41, 59, 0.25);
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-name {
            font-weight: 600;
            font-size: 0.9375rem;
            color: #111827;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-detail {
            font-size: 0.8125rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-role {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .activity-role.guru {
            background: #dbeafe;
            color: #1e40af;
        }

        .activity-role.siswa {
            background: #d1fae5;
            color: #065f46;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 500;
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions-section {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            border: 1px solid #e5e7eb;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
            margin-bottom: 0.75rem;
        }

        .action-button:last-child {
            margin-bottom: 0;
        }

        .action-button:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateX(4px);
        }

        .action-icon {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .action-icon.blue { 
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #2563eb;
        }

        .action-icon.green { 
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #059669;
        }

        .action-icon.purple { 
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            color: #7c3aed;
        }

        .action-content h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.125rem;
        }

        .action-content p {
            font-size: 0.75rem;
            color: #6b7280;
            margin: 0;
        }

        /* System Info */
        .system-info {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }

        .system-info-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .system-status-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .system-info-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #065f46;
        }

        .system-info-detail {
            font-size: 0.8125rem;
            color: #047857;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .greeting-text {
                font-size: 1.5rem;
            }

            .admin-name {
                font-size: 1.25rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>

    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="greeting-text">
                    <?php echo $greeting_icon . ' ' . $greeting; ?>
                </div>
                <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="date-info"><?php echo $current_date; ?></div>
                
                <div class="welcome-stats">
                    <div class="welcome-stat-item">
                        <div class="welcome-stat-number"><?php echo $persentase_kehadiran_hari_ini; ?>%</div>
                        <div class="welcome-stat-label">Kehadiran Hari Ini</div>
                    </div>
                    <div class="welcome-stat-item">
                        <div class="welcome-stat-number"><?php echo number_format($total_hadir_hari_ini); ?></div>
                        <div class="welcome-stat-label">Siswa Hadir</div>
                    </div>
                    <div class="welcome-stat-item">
                        <div class="welcome-stat-number"><?php echo number_format($total_users); ?></div>
                        <div class="welcome-stat-label">Total User</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon blue">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-number"><?php echo number_format($total_siswa); ?></div>
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Aktif
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon green">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-number"><?php echo number_format($total_guru); ?></div>
                    <div class="stat-label">Total Guru</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Aktif
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon purple">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-number"><?php echo number_format($total_wali_kelas); ?></div>
                    <div class="stat-label">Wali Kelas</div>
                    <div class="stat-change positive">
                        <i class="fas fa-briefcase"></i> Aktif
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon orange">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-body">
                    <div class="stat-number"><?php echo $persentase_kehadiran_hari_ini; ?>%</div>
                    <div class="stat-label">Tingkat Kehadiran</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Hari Ini
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Activity Section -->
            <div class="activity-section">
                <div class="section-header">
                    <div class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        User Terbaru Terdaftar
                    </div>
                    <a href="user_management.php" class="view-all-link">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="activity-list">
                    <?php if (count($recent_logins) > 0): ?>
                        <?php foreach ($recent_logins as $login): ?>
                        <div class="activity-item">
                            <div class="activity-avatar">
                                <?= strtoupper(substr($login['nama'], 0, 1)); ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-name"><?php echo htmlspecialchars($login['nama']); ?></div>
                                <div class="activity-detail">
                                    <span class="activity-role <?php echo strtolower($login['role']); ?>">
                                        <?php echo $login['role']; ?>
                                    </span>
                                    <span><?php echo htmlspecialchars($login['nip']); ?></span>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php echo timeAgo($login['created_at']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Belum ada user yang terdaftar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions & System Info -->
            <div>
                <div class="quick-actions-section">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="section-title-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            Quick Actions
                        </div>
                    </div>

                    <a href="user_management.php" class="action-button">
                        <div class="action-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-content">
                            <h4>Kelola User</h4>
                            <p>Tambah & edit data user</p>
                        </div>
                    </a>

                    <a href="sistem_settings.php" class="action-button">
                        <div class="action-icon purple">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="action-content">
                            <h4>Pengaturan Sistem</h4>
                            <p>Konfigurasi aplikasi</p>
                        </div>
                    </a>
                </div>

                <div class="system-info">
                    <div class="system-info-header">
                        <div class="system-status-icon">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div class="system-info-title">Sistem Beroperasi Normal</div>
                    </div>
                    <div class="system-info-detail">
                        Semua layanan berjalan dengan baik. Terakhir diperbarui: <?php echo date('d M Y H:i'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>