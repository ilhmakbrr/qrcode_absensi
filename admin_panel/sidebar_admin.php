<?php
// Cek apakah session sudah dimulai, jika belum baru start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Validasi admin login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login_admin.php");
    exit();
}

// Ambil data admin dari session
$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];
$admin_role = 'Super Admin';

// Ambil nama lengkap dari database
include '../db.php';
$query = "SELECT nama_lengkap, username FROM users WHERE id = '$admin_id' AND role = 'admin' LIMIT 1";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $admin_data = mysqli_fetch_assoc($result);
    $admin_name = $admin_data['nama_lengkap'] ?? $admin_username;
} else {
    $admin_name = $admin_username;
}

// Tentukan halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - Sistem Absensi</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image" href="../gambar/SMA-PGRI-2-Padang-logo-ok.webp">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f5f7fa;
}

/* ===== Main Content Responsive ===== */
.main-content {
    margin-left: 260px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

.sidebar.collapsed ~ .main-content {
    margin-left: 70px !important;
}

/* ===== Sidebar Clean Design ===== */
.sidebar {
    width: 260px;
    background: #ffffff;
    min-height: 100vh;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 900;
    border-right: 1px solid #e8ecf1;
}

.sidebar.collapsed {
    width: 70px !important;
}

/* ===== Header Clean ===== */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 18px;
    border-bottom: 1px solid #e8ecf1;
    background: #ffffff;
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
    width: 38px;
    height: 38px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 10px;
    font-size: 18px;
    flex-shrink: 0;
}

.logo-text {
    font-weight: 700;
    font-size: 16px;
    color: #1a202c;
    white-space: nowrap;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    letter-spacing: -0.3px;
}

.sidebar.collapsed .logo-text {
    opacity: 0;
    visibility: hidden;
}

/* ===== Toggle Button - SAMA SEPERTI GURU ===== */
.toggle-btn {
    background: transparent;
    border: none;
    color: #718096;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 20px;
}

.toggle-btn:hover {
    background: #f7fafc;
    color: #667eea;
    transform: rotate(90deg);
}

/* ===== Open Button - SAMA SEPERTI GURU ===== */
.open-btn {
    position: fixed;
    top: 24px;
    left: 80px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    width: 38px;
    height: 38px;
    display: none;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.open-btn:hover {
    background: #5568d3;
    transform: scale(1.05) rotate(0deg) !important;
}

.sidebar.collapsed ~ .open-btn {
    display: flex;
    left: 85px;
    transform: rotate(90deg);
}

/* ===== Admin Info Clean ===== */
.admin-info {
    padding: 18px;
    background: #fafbfc;
    border-bottom: 1px solid #e8ecf1;
}

.admin-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.25rem;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.sidebar.collapsed .admin-avatar {
    width: 36px;
    height: 36px;
    font-size: 1rem;
    margin-bottom: 0;
}

.admin-details {
    text-align: center;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.admin-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 3px;
    color: #1a202c;
}

.admin-username {
    font-size: 11px;
    color: #718096;
    margin-bottom: 6px;
}

.admin-role {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f0f4ff;
    color: #667eea;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
}

.sidebar.collapsed .admin-details {
    opacity: 0;
    visibility: hidden;
    height: 0;
    overflow: hidden;
}

/* ===== Menu Clean ===== */
.nav-menu {
    padding: 8px 0;
    padding-bottom: 80px;
}

.menu-section-title {
    color: #a0aec0;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 16px 18px 8px;
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
    color: #4a5568;
    padding: 10px 18px;
    margin: 2px 10px;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-radius: 8px;
}

.nav-item:hover {
    background: #f7fafc;
    color: #667eea;
}

.nav-item.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.nav-item.active .nav-icon {
    color: white;
}

.nav-icon {
    font-size: 18px;
    width: 22px;
    text-align: center;
    transition: color 0.2s ease;
    flex-shrink: 0;
}

.nav-text {
    margin-left: 12px;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 500;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.sidebar.collapsed .nav-text {
    opacity: 0;
    visibility: hidden;
}

/* ===== Footer Clean ===== */
.sidebar-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 260px;
    border-top: 1px solid #e8ecf1;
    padding: 16px 18px;
    background: white;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 901;
}

.sidebar.collapsed .sidebar-footer {
    width: 70px;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #e53e3e;
    text-decoration: none;
    background: #fff5f5;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 13px;
    font-weight: 500;
}

.logout-btn:hover {
    background: #e53e3e;
    color: white;
}

.sidebar.collapsed .logout-btn span {
    display: none;
}

/* ===== Tooltip - SAMA SEPERTI GURU ===== */
.sidebar.collapsed .nav-item[title]:hover::after {
    content: attr(title);
    position: absolute;
    left: 75px;
    background: #1a202c;
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: nowrap;
    font-size: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    font-weight: 500;
}

/* ===== Scrollbar Clean ===== */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #e8ecf1;
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e0;
}

/* ===== Responsive Mobile ===== */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
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
                <img src="../gambar/SMA-PGRI-2-Padang-logo-ok.webp" alt="logo SMA2PGRI" style="width:25px; height:28px;">
            </div>
            <div class="logo-text">Admin Panel</div>
        </div>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="admin-info">
        <div class="admin-avatar">
            <?= strtoupper(substr($admin_name, 0, 1)); ?>
        </div>
        <div class="admin-details">
            <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
            <div class="admin-username">@<?php echo htmlspecialchars($admin_username); ?></div>
            <div class="admin-role">
                <i class="fas fa-shield-alt"></i>
                <?php echo htmlspecialchars($admin_role); ?>
            </div>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="menu-section-title">Menu Utama</div>

        <a href="dashboard_admin.php" 
           class="nav-item <?php echo ($current_page == 'dashboard_admin.php') ? 'active' : ''; ?>" 
           title="Dashboard">
            <div class="nav-icon"><i class="fas fa-home"></i></div>
            <div class="nav-text">Dashboard</div>
        </a>

        <a href="siswa.php" 
           class="nav-item <?php echo ($current_page == 'siswa.php') ? 'active' : ''; ?>" 
           title="Data Siswa">
            <div class="nav-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="nav-text">Data Siswa</div>
        </a>

        <a href="guru.php" 
           class="nav-item <?php echo ($current_page == 'guru.php') ? 'active' : ''; ?>" 
           title="Data Guru">
            <div class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="nav-text">Data Guru</div>
        </a>

        <a href="walikelas.php" 
           class="nav-item <?php echo ($current_page == 'walikelas.php') ? 'active' : ''; ?>" 
           title="Data Wali Kelas">
            <div class="nav-icon"><i class="fas fa-user-tie"></i></div>
            <div class="nav-text">Data Wali Kelas</div>
        </a>

        <a href="ketua_yayasan.php" 
           class="nav-item <?php echo ($current_page == 'ketua_yayasan.php') ? 'active' : ''; ?>" 
           title="Data Ketua Yayasan">
            <div class="nav-icon"><i class="fas fa-user-shield"></i></div>
            <div class="nav-text">Data Ketua Yayasan</div>
        </a>

        <a href="content_control.php" 
           class="nav-item <?php echo ($current_page == 'content_control.php') ? 'active' : ''; ?>" 
           title="Content Control">
            <div class="nav-icon"><i class="fas fa-cog"></i></div>
            <div class="nav-text">Content Control</div>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../admin_panel/auth/logout.php" class="logout-btn" onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
    </div>
</div>


<button class="open-btn" id="openBtn" onclick="openSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- Overlay untuk mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<script>
// Toggle Sidebar - SAMA SEPERTI GURU
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    sidebar.classList.toggle('collapsed');
    
    // Update button rotation
    if (!sidebar.classList.contains('collapsed')) {
        openBtn.style.transform = 'rotate(0deg)';
    }
    
    // Save state
    localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
}

// Open Sidebar - SAMA SEPERTI GURU
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.add('mobile-open');
        document.getElementById('sidebarOverlay').classList.add('active');
    } else {
        sidebar.classList.remove('collapsed');
        openBtn.style.transform = 'rotate(90deg)';
    }
    
    // Save state
    localStorage.setItem('sidebarState', 'expanded');
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

// Load saved state - SAMA SEPERTI GURU
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');
    
    // Apply saved state
    if (savedState === 'collapsed') {
        sidebar.classList.add('collapsed');
    }
    
    // Set active menu
    const currentPage = window.location.pathname.split('/').pop().split('?')[0];
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    // Close mobile menu when clicking nav item
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
    
    // Close mobile menu when clicking outside
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
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
    });
    
    console.log('Admin Sidebar - User: <?php echo $admin_username; ?>');
});
</script>
</body>
</html>