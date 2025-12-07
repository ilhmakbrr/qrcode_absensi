<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_panel/auth/logout.php?error=unauthorized");
    exit();
}

include '../db.php';

if (!$conn) {
    die("Error: Tidak dapat terhubung ke database.");
}

// Check if view exists, if not create it
$check_view = "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_db_absensi_siswa = 'v_active_sessions'";
$view_exists = mysqli_query($conn, $check_view);

if (mysqli_num_rows($view_exists) == 0) {
    // Create the view if it doesn't exist
    $create_view = "CREATE VIEW v_active_sessions AS
    SELECT 
        ls.id,
        ls.user_id,
        ls.role,
        ls.login_time,
        ls.logout_time,
        ls.ip_address,
        ls.user_agent,
        ls.status,
        CASE 
            WHEN ls.role = 'siswa' THEN s.nama
            WHEN ls.role = 'guru' THEN g.nama
            ELSE 'Unknown'
        END as nama_user,
        CASE 
            WHEN ls.role = 'siswa' THEN s.nis
            WHEN ls.role = 'guru' THEN g.nip
            ELSE NULL
        END as identifier,
        s.kelas as kelas_siswa,
        s.jurusan as jurusan_siswa,
        g.mata_pelajaran as mata_pelajaran_guru,
        NULL as wali_kelas,
        TIMESTAMPDIFF(MINUTE, ls.login_time, NOW()) as durasi_menit
    FROM login_sessions ls
    LEFT JOIN siswa s ON ls.user_id = s.id AND ls.role = 'siswa'
    LEFT JOIN guru g ON ls.user_id = g.id AND ls.role = 'guru'
    WHERE ls.status = 'active' AND ls.logout_time IS NULL";
    
    mysqli_query($conn, $create_view);
}

// Get active sessions
$query = "SELECT * FROM v_active_sessions ORDER BY login_time DESC";
$active_sessions = mysqli_query($conn, $query);

if (!$active_sessions) {
    die("Error query active sessions: " . mysqli_error($conn));
}

// Get session statistics
$stats_query = "SELECT 
                COUNT(*) as total_active,
                SUM(CASE WHEN role = 'siswa' THEN 1 ELSE 0 END) as total_siswa,
                SUM(CASE WHEN role = 'guru' THEN 1 ELSE 0 END) as total_guru
                FROM v_active_sessions";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle null values
$stats['total_active'] = $stats['total_active'] ?? 0;
$stats['total_siswa'] = $stats['total_siswa'] ?? 0;
$stats['total_guru'] = $stats['total_guru'] ?? 0;

// Get recent logout sessions (last 20)
$recent_query = "SELECT 
                ls.*,
                CASE 
                    WHEN ls.role = 'siswa' THEN s.nama
                    WHEN ls.role = 'guru' THEN g.nama
                    ELSE 'Unknown'
                END as nama_user,
                CASE 
                    WHEN ls.role = 'siswa' THEN s.nis
                    WHEN ls.role = 'guru' THEN g.nip
                    ELSE NULL
                END as identifier,
                s.kelas as kelas_siswa,
                s.jurusan as jurusan_siswa,
                g.mata_pelajaran as mata_pelajaran_guru
                FROM login_sessions ls
                LEFT JOIN siswa s ON ls.user_id = s.id AND ls.role = 'siswa'
                LEFT JOIN guru g ON ls.user_id = g.id AND ls.role = 'guru'
                WHERE ls.logout_time IS NOT NULL
                ORDER BY ls.logout_time DESC
                LIMIT 20";
$recent_sessions = mysqli_query($conn, $recent_query);

if (!$recent_sessions) {
    die("Error query recent sessions: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Login Sessions - EduAttend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }

        .main-content {
            margin: 20px;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            border-color: #c1cdd7;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .stat-icon.primary {
            background:  #7f8c8d;
            color: white;
        }

        .stat-icon.success {
            background:  #7f8c8d;
            color: white;
        }

        .stat-icon.info {
            background:  #7f8c8d;
            color: white;
        }

        .stat-label {
            font-size: 0.8rem;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: white;
            line-height: 1;
        }

        .section-card {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            font-size: 1.1rem;
            color: black;
        }

        .table {
            margin: 0;
            font-size: 0.875rem;
        }

        .table thead th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding: 12px;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            color: #495057;
            border-bottom: 1px solid #f1f3f5;
        }

        .table-hover tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .badge-siswa {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-guru {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-admin {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .badge-duration {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-logout {
            background: #f5f5f5;
            color: #616161;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-item {
            font-size: 0.8rem;
            color: #5a6c7d;
        }

        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            margin-right: 4px;
        }

        .text-code {
            font-family: 'Courier New', monospace;
            background: #f5f6fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8rem;
            color: #5a6c7d;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
        }

        .refresh-notice {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 20px;
            margin-top: 24px;
            text-align: center;
        }

        .refresh-notice i {
            color: #7f8c8d;
            margin-right: 8px;
        }

        .refresh-notice small {
            color: #5a6c7d;
            font-size: 0.8rem;
        }

        .time-text {
            color: #7f8c8d;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin: 10px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Monitoring Session Login</h1>
            <p class="page-subtitle">Pengawasan Login siswa dan guru</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon primary">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="stat-label">Total Sesi Aktif</div>
                </div>
                <div class="stat-number"><?= $stats['total_active'] ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon success">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-label">Siswa Online</div>
                </div>
                <div class="stat-number"><?= $stats['total_siswa'] ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon info">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-label">Guru Online</div>
                </div>
                <div class="stat-number"><?= $stats['total_guru'] ?></div>
            </div>
        </div>

        <!-- Active Sessions -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-users"></i>
                Sesi Aktif
            </h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama User</th>
                            <th>Role</th>
                            <th>ID</th>
                            <th>Detail Informasi</th>
                            <th>Waktu Login</th>
                            <th>Durasi</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($active_sessions && mysqli_num_rows($active_sessions) > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($session = mysqli_fetch_assoc($active_sessions)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($session['nama_user']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $session['role'] ?>">
                                            <?= strtoupper($session['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-code"><?= htmlspecialchars($session['identifier']) ?></span>
                                    </td>
                                    <td>
                                        <div class="info-group">
                                            <?php if ($session['role'] == 'siswa'): ?>
                                                <div class="info-item">
                                                    <span class="info-label">Kelas:</span>
                                                    <?= htmlspecialchars($session['kelas_siswa'] ?? '-') ?>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Jurusan:</span>
                                                    <?= htmlspecialchars($session['jurusan_siswa'] ?? '-') ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="info-item">
                                                    <span class="info-label">Mata Pelajaran:</span>
                                                    <?= htmlspecialchars($session['mata_pelajaran_guru'] ?? '-') ?>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Wali Kelas:</span>
                                                    <?= htmlspecialchars($session['wali_kelas'] ?? '-') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="time-text">
                                            <?= date('d M Y, H:i', strtotime($session['login_time'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-duration">
                                            <?= $session['durasi_menit'] ?> menit
                                        </span>
                                    </td>
                                    <td>
                                        <span class="time-text"><?= htmlspecialchars($session['ip_address']) ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Tidak ada sesi aktif saat ini</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Logout Sessions -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-history"></i>
                Riwayat Logout
            </h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama User</th>
                            <th>Role</th>
                            <th>ID</th>
                            <th>Informasi</th>
                            <th>Login</th>
                            <th>Logout</th>
                            <th>Durasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_sessions && mysqli_num_rows($recent_sessions) > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($session = mysqli_fetch_assoc($recent_sessions)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($session['nama_user']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $session['role'] ?>">
                                            <?= strtoupper($session['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-code"><?= htmlspecialchars($session['identifier']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($session['role'] == 'siswa'): ?>
                                            <span class="info-item">
                                                <?= htmlspecialchars($session['kelas_siswa'] ?? '-') ?>
                                                <?= !empty($session['jurusan_siswa']) ? '(' . htmlspecialchars($session['jurusan_siswa']) . ')' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="info-item">
                                                <?= htmlspecialchars($session['mata_pelajaran_guru'] ?? '-') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="time-text">
                                            <?= date('d M, H:i', strtotime($session['login_time'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="time-text">
                                            <?= date('d M, H:i', strtotime($session['logout_time'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $duration = $session['session_duration'] ?? 0;
                                        $minutes = floor($duration / 60);
                                        $seconds = $duration % 60;
                                        echo "<span class='badge badge-duration'>{$minutes}m {$seconds}s</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $session['status'] == 'logout' ? 'logout' : 'active' ?>">
                                            <?= ucfirst($session['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Belum ada riwayat logout</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Auto Refresh Notice -->
        <div class="refresh-notice">
            <i class="fas fa-sync-alt"></i>
            <small><strong>Auto Refresh:</strong> Halaman akan diperbarui otomatis setiap 30 detik</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
        
        // Show last refresh time
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date().toLocaleTimeString('id-ID');
            console.log('Page loaded at: ' + now);
        });
    </script>
</body>
</html>