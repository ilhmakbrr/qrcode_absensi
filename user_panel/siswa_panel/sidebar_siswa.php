<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

include '../../db.php';
// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['siswa']) && count($_SESSION['logged_users']['siswa']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['siswa']);
    }
}

if (!$current_user_id || !isset($_SESSION['logged_users']['siswa'][$current_user_id])) {
    header("Location: ../auth/login_user.php?role=siswa");
    exit();
}

// Ambil data siswa dari session
$user_data = $_SESSION['logged_users']['siswa'][$current_user_id];
$nama = $user_data['nama'];
$kelas = $user_data['kelas'];
$nis = $user_data['nis'];
$jurusan = $user_data['jurusan'] ?? '';

// Ambil foto siswa dari database
$siswa_foto = null;
$query = "SELECT foto FROM siswa WHERE id = " . (int)$current_user_id;
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $siswa_foto = $row['foto'];
}

// Tentukan halaman aktif berdasarkan URL
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sidebar Siswa - Sistem Absensi</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
}

/* ===== Main Content Responsive ===== */
.main-content {
    margin-left: 280px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

.sidebar.collapsed ~ .main-content {
    margin-left: 80px !important;
}

/* ===== Sidebar ===== */
.sidebar {
    width: 280px;
    background: #ffffff;
    color: #1e293b;
    min-height: 100vh;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 900;
    border-right: 1px solid #e2e8f0;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
}

.sidebar.collapsed {
    width: 80px !important;
}

/* ===== Header ===== */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
}

.logo-icon {
    background: white;
    color: white;
    width: 42px;
    height: 42px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 12px;
    font-size: 20px;
    flex-shrink: 0;
}

.logo-text {
    font-weight: 700;
    font-size: 18px;
    color: #0f172a;
    white-space: nowrap;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    letter-spacing: -0.5px;
}

.sidebar.collapsed .logo-text {
    opacity: 0;
    visibility: hidden;
}

/* ===== Toggle Button ===== */
.toggle-btn {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 18px;
}

.toggle-btn:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    transform: rotate(90deg);
}

/* ===== Open Button ===== */
.open-btn {
    position: fixed;
    top: 28px;
    left: 90px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 10px;
    width: 40px;
    height: 40px;
    display: none;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.open-btn:hover {
    background: #2563eb;
    transform: scale(1.05) rotate(0deg) !important;
}

.sidebar.collapsed ~ .open-btn {
    display: flex;
    left: 95px;
    transform: rotate(90deg);
}

/* ===== User Info ===== */
.user-info {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
}

.user-avatar {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 1.5rem;
    color: white;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    transition: all 0.3s ease;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar.collapsed .user-avatar {
    width: 40px;
    height: 40px;
    font-size: 1.25rem;
    margin-bottom: 0;
}

.user-details {
    text-align: center;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.user-name {
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 4px;
    color: #0f172a;
}

.user-meta {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 8px;
}

.user-class {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #eff6ff;
    color: #2563eb;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
}

.sidebar.collapsed .user-details {
    opacity: 0;
    visibility: hidden;
    height: 0;
    overflow: hidden;
}

/* ===== Menu ===== */
.nav-menu {
    padding: 12px 0;
    padding-bottom: 80px;
}

.menu-section-title {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 20px 8px;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.sidebar.collapsed .menu-section-title {
    opacity: 0;
    visibility: hidden;
    height: 0;
    padding: 0;
    margin: 0;
}

.nav-item {
    display: flex;
    align-items: center;
    color: #64748b;
    padding: 12px 20px;
    margin: 2px 12px;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-radius: 10px;
}

.nav-item:hover {
    background: #f8fafc;
    color: #3b82f6;
}

.nav-item.active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: 600;
}

.nav-item.active .nav-icon {
    color: #2563eb;
}

.nav-icon {
    font-size: 20px;
    width: 24px;
    text-align: center;
    transition: color 0.2s ease;
    flex-shrink: 0;
}

.nav-text {
    margin-left: 12px;
    white-space: nowrap;
    font-size: 14px;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.sidebar.collapsed .nav-text {
    opacity: 0;
    visibility: hidden;
}

.nav-badge {
    margin-left: auto;
    background: #3b82f6;
    color: white;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.sidebar.collapsed .nav-badge {
    opacity: 0;
    visibility: hidden;
}

/* ===== Footer ===== */
.sidebar-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 280px;
    border-top: 1px solid #f1f5f9;
    padding: 20px;
    background: white;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 901;
}

.sidebar.collapsed .sidebar-footer {
    width: 80px;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #dc2626;
    text-decoration: none;
    background: #fef2f2;
    padding: 12px;
    border-radius: 10px;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 600;
}

.logout-btn:hover {
    background: #dc2626;
    color: white;
}

.sidebar.collapsed .logout-btn span {
    display: none;
}

/* ===== Tooltip ===== */
.sidebar.collapsed .nav-item[title]:hover::after {
    content: attr(title);
    position: absolute;
    left: 85px;
    background: #1e293b;
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    white-space: nowrap;
    font-size: 13px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    font-weight: 500;
}

/* ===== Scrollbar ===== */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

/* ===== Responsive Mobile ===== */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0 !important;
    }
    
    .open-btn {
        display: flex !important;
        left: 20px;
        transform: rotate(0deg) !important;
    }
    
    .sidebar.collapsed ~ .open-btn {
        left: 20px;
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 899;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .sidebar.mobile-open ~ .sidebar-overlay,
    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }
}
</style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <img src="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp" alt="logo SMA2PGRI" style="width:25px; height:28px;">
            </div>
            <div class="logo-text">SMA 2 PGRI</div>
        </div>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="user-info">
        <div class="user-avatar">
            <?php if ($siswa_foto && file_exists('../../uploads/siswa/' . $siswa_foto)): ?>
                <img src="../../uploads/siswa/<?php echo htmlspecialchars($siswa_foto); ?>" alt="Foto Profil">
            <?php else: ?>
                <?= strtoupper(substr($nama, 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($nama); ?></div>
            <div class="user-meta">NIS: <?php echo htmlspecialchars($nis); ?></div>
            <div class="user-class">
                <i class="bi bi-bookmark-fill"></i>
                <?php echo htmlspecialchars($kelas); ?> - <?php echo htmlspecialchars($jurusan); ?>
            </div>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="menu-section-title">Menu Utama</div>

        <a href="dashboard_siswa.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" 
           class="nav-item <?php echo ($current_page == 'dashboard_siswa.php') ? 'active' : ''; ?>" 
           title="Dashboard">
            <div class="nav-icon"><i class="bi bi-house-door"></i></div>
            <div class="nav-text">Dashboard</div>
        </a>

        <a href="scan_qr.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" 
           class="nav-item <?php echo ($current_page == 'scan_qr.php') ? 'active' : ''; ?>" 
           title="Scan QR Code">
            <div class="nav-icon"><i class="bi bi-qr-code-scan"></i></div>
            <div class="nav-text">Scan QR Absen</div>
            <span class="nav-badge">Live</span>
        </a>

        <a href="riwayat_absen.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" 
           class="nav-item <?php echo ($current_page == 'riwayat_absen.php') ? 'active' : ''; ?>" 
           title="Riwayat Absensi">
            <div class="nav-icon"><i class="bi bi-clock-history"></i></div>
            <div class="nav-text">Riwayat Absensi</div>
        </a>

        <a href="jadwal_pelajaran.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" 
           class="nav-item <?php echo ($current_page == 'jadwal_pelajaran.php') ? 'active' : ''; ?>" 
           title="Jadwal Pelajaran">
            <div class="nav-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="nav-text">Jadwal Pelajaran</div>
        </a>

        <div class="menu-section-title">Bantuan</div>

        <a href="bantuan.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>" 
           class="nav-item <?php echo ($current_page == 'bantuan.php') ? 'active' : ''; ?>" 
           title="Bantuan">
            <div class="nav-icon"><i class="bi bi-question-circle"></i></div>
            <div class="nav-text">Pusat Bantuan</div>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../auth/login_user.php?logout&role=siswa&id=<?php echo $current_user_id; ?>" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
    </div>
</div>

<button class="open-btn" id="openBtn" onclick="openSidebar()">
    <i class="bi bi-list"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    sidebar.classList.toggle('collapsed');
    
    if (!sidebar.classList.contains('collapsed')) {
        openBtn.style.transform = 'rotate(0deg)';
    }
    
    localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
    } else {
        sidebar.classList.remove('collapsed');
        openBtn.style.transform = 'rotate(90deg)';
    }
    
    localStorage.setItem('sidebarState', 'expanded');
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');
    
    if (savedState === 'collapsed') {
        sidebar.classList.add('collapsed');
    }
    
    const currentPage = window.location.pathname.split('/').pop().split('?')[0];
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
    
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openBtn');
        
        if (window.innerWidth <= 768 && 
            sidebar.classList.contains('mobile-open') &&
            !sidebar.contains(event.target) && 
            !openBtn.contains(event.target)) {
            closeMobileSidebar();
        }
    });
    
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        }
    });
});
</script>
</body>
</html>