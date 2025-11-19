<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

include '../../db.php'; 
// Validasi session ketua yayasan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ketua_yayasan') {
    header("Location: login_yayasan.php?error=unauthorized");
    exit();
}

// Ambil data ketua yayasan dari session
$ketua_nama = $_SESSION['user_name'] ?? 'Ketua Yayasan';
$ketua_email = $_SESSION['user_email'] ?? '';
$ketua_id = $_SESSION['user_id'];

// Ambil foto ketua yayasan dari database
$ketua_foto = null;
$query = "SELECT foto FROM ketua_yayasan WHERE id = " . (int)$ketua_id;
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $ketua_foto = $row['foto'];
}

// Tentukan halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sidebar Ketua Yayasan</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: #f5f5f5;
    }

    /* ===== Main Content Responsive ===== */
    .main-content {
        margin-left: 260px;
        transition: margin-left 0.3s ease;
        padding: 20px;
        min-height: 100vh;
    }

    .sidebar.collapsed ~ .main-content {
        margin-left: 70px !important;
    }

    /* ===== SIDEBAR ===== */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid #e0e0e0;
        transition: width 0.3s ease;
        z-index: 900;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar.collapsed {
        width: 70px !important;
    }

    /* ===== HEADER ===== */
    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 70px;
        flex-shrink: 0;
        background: #f8f9fa;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
    }

    .brand-text {
        font-weight: 700;
        font-size: 18px;
        color: #0f172a;
        white-space: nowrap;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        letter-spacing: -0.5px;
    }

    .sidebar.collapsed .brand-text {
        opacity: 0;
        visibility: hidden;
    }

    /* ===== Tombol toggle di header ===== */
    .toggle-btn {
        background: none;
        border: none;
        color: #666;
        font-size: 20px;
        cursor: pointer;
        transition: transform 0.3s;
        padding: 5px;
    }

    .toggle-btn:hover {
        transform: rotate(90deg);
    }

    /* ===== Open Button - BUTTON UNTUK MEMBUKA SIDEBAR ===== */
    .open-btn {
        position: fixed;
        top: 25px;
        left: 80px;
        background: #2c3e50;
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
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }

    .open-btn:hover {
        background: #1a252f;
        transform: scale(1.05) rotate(0deg) !important;
    }

    /* Button muncul saat sidebar collapsed */
    .sidebar.collapsed ~ .open-btn {
        display: flex;
        left: 85px;
        transform: rotate(90deg);
    }

    /* ===== USER INFO ===== */
    .user-info {
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        transition: all 0.3s ease;
        flex-shrink: 0;
        background: #fafafa;
    }

    .user-avatar {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 20px;
        font-weight: 600;
        margin: 0 auto 12px;
        flex-shrink: 0;
        overflow: hidden;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-avatar-text {
        font-size: 20px;
        font-weight: 600;
    }

    .user-name {
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
        text-align: center;
        margin-bottom: 4px;
    }

    .user-role {
        font-size: 12px;
        color: #666;
        text-align: center;
    }

    .sidebar.collapsed .user-info {
        display: none;
    }

    /* ===== NAVIGATION ===== */
    .nav-menu {
        flex: 1;
        padding: 15px 0;
        padding-bottom: 80px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .nav-menu::-webkit-scrollbar {
        width: 6px;
    }

    .nav-menu::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.05);
    }

    .nav-menu::-webkit-scrollbar-thumb {
        background: #e0e0e0;
        border-radius: 3px;
    }

    .nav-menu::-webkit-scrollbar-thumb:hover {
        background: #d0d0d0;
    }

    .nav-section-title {
        padding: 15px 20px 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #999;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .sidebar.collapsed .nav-section-title {
        opacity: 0;
        visibility: hidden;
        height: 0;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: #555;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-item:hover {
        background: #f8f8f8;
        color: #2c3e50;
    }

    .nav-item.active {
        background: #f0f0f0;
        color: #2c3e50;
        font-weight: 500;
        border-left: 4px solid #2c3e50;
    }

    .nav-icon {
        font-size: 18px;
        width: 28px;
        text-align: center;
    }

    .nav-text {
        white-space: nowrap;
        font-size: 14px;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        visibility: hidden;
    }

    /* ===== DIVIDER ===== */
    .nav-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 15px 20px;
    }

    .sidebar.collapsed .nav-divider {
        margin: 15px 10px;
    }

    /* ===== Tooltip saat collapsed ===== */
    .nav-item[title] {
        position: relative;
    }

    .sidebar.collapsed .nav-item:hover::after {
        content: attr(title);
        position: absolute;
        left: 75px;
        background: #2c3e50;
        color: white;
        padding: 6px 10px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        z-index: 1000;
    }

    /* ===== FOOTER ===== */
    .sidebar-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 260px;
        border-top: 1px solid #e0e0e0;
        padding: 15px 20px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        transition: width 0.3s ease;
        z-index: 901;
    }

    .sidebar.collapsed .sidebar-footer {
        width: 70px;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        color: #666;
        text-decoration: none;
        background: #fff;
        border: 1px solid #e0e0e0;
        padding: 10px;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .logout-btn:hover {
        background: #fee;
        border-color: #fcc;
        color: #c33;
    }

    .sidebar.collapsed .logout-btn {
        justify-content: center;
    }

    .sidebar.collapsed .logout-btn span {
        display: none;
    }

    /* ===== MOBILE TOGGLE ===== */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        width: 40px;
        height: 40px;
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 999;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    /* ===== RESPONSIVE ===== */
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
        
        .mobile-toggle {
            display: flex;
        }
        
        .open-btn {
            display: none !important;
        }
        
        .sidebar.collapsed ~ .open-btn {
            display: none !important;
        }
    }

    /* ===== OVERLAY ===== */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
        z-index: 899;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }

    @media (min-width: 769px) {
        .sidebar-overlay {
            display: none !important;
        }
    }
    </style>
</head>
<body>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" onclick="toggleMobile()">
    <i class="bi bi-list"></i>
</button>

<button class="open-btn" id="openBtn" onclick="openSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobile()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
        <div class="logo-icon">
                <img src="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp" alt="logo SMA2PGRI" style="width:30px; height:35px;">
            </div>
            <span class="brand-text">SMA 2 PGRI</span>
        </div>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- User Info -->
    <div class="user-info">
        <div class="user-avatar">
            <?php if ($ketua_foto && file_exists('../../uploads/ketua_yayasan/' . $ketua_foto)): ?>
                <img src="../../uploads/ketua_yayasan/<?php echo htmlspecialchars($ketua_foto); ?>" alt="Foto Profil">
            <?php else: ?>
                <span class="user-avatar-text"><?php echo strtoupper(substr($ketua_nama, 0, 1)); ?></span>
            <?php endif; ?>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($ketua_nama); ?></div>
        <div class="user-role">Ketua Yayasan</div>
    </div>

    <!-- Navigation Menu -->
    <nav class="nav-menu">
        <div class="nav-section-title">Menu Utama</div>
        
        <a href="yayasan_dashboard.php" 
           class="nav-item <?php echo ($current_page == 'yayasan_dashboard.php') ? 'active' : ''; ?>"
           title="Dashboard">
            <div class="nav-icon"><i class="fas fa-home"></i></div>
            <div class="nav-text">Dashboard</div>
        </a>

        <div class="nav-divider"></div>

        <div class="nav-section-title">Monitoring Guru</div>

        <a href="kinerja_guru.php" 
           class="nav-item <?php echo ($current_page == 'kinerja_guru.php' || $current_page == 'detail_kinerja_guru.php') ? 'active' : ''; ?>"
           title="Kinerja Guru">
            <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
            <div class="nav-text">Kinerja Guru</div>
        </a>

        <a href="daftar_feedback_guru.php" 
           class="nav-item <?php echo ($current_page == 'daftar_feedback_guru.php' || $current_page == 'tambah_feedback_guru.php') ? 'active' : ''; ?>"
           title="Feedback Guru">
            <div class="nav-icon"><i class="fas fa-comments"></i></div>
            <div class="nav-text">Feedback Guru</div>
        </a>

        <a href="laporan_kinerja_guru.php" 
           class="nav-item <?php echo ($current_page == 'laporan_kinerja_guru.php') ? 'active' : ''; ?>"
           title="Laporan Kinerja">
            <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
            <div class="nav-text">Laporan Kinerja</div>
        </a>

        <div class="nav-divider"></div>

        <div class="nav-section-title">Pengaturan</div>

        <a href="profil_yayasan.php" 
           class="nav-item <?php echo ($current_page == 'profil_yayasan.php') ? 'active' : ''; ?>"
           title="Profil">
            <div class="nav-icon"><i class="fas fa-user"></i></div>
            <div class="nav-text">Profil</div>
        </a>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin keluar?')">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </div>
</div>

<!-- Tombol minimalis di samping sidebar -->
<button class="open-btn" id="openBtn" onclick="openSidebar()">
    <i class="bi bi-list"></i>
</button>


<script>
// Toggle Sidebar - Tutup sidebar
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

// Open Sidebar - Buka sidebar dari collapsed state
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    sidebar.classList.remove('collapsed');
    openBtn.style.transform = 'rotate(90deg)';
    
    // Save state
    localStorage.setItem('sidebarState', 'expanded');
}

// Toggle Mobile Menu
function toggleMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
    
    if (sidebar.classList.contains('mobile-open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// Close Mobile Menu
function closeMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Load saved state
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
        // Gunakan pencocokan eksak, bukan includes
        if (href && href.split('?')[0] === currentPage) {
            item.classList.add('active');
        }
    });
    
    // Close mobile menu when clicking nav item
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobile();
            }
        });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openBtn');
        const mobileToggle = document.querySelector('.mobile-toggle');
        
        if (window.innerWidth <= 768 && 
            sidebar.classList.contains('mobile-open') &&
            !sidebar.contains(event.target) && 
            !openBtn.contains(event.target) &&
            !mobileToggle.contains(event.target)) {
            closeMobile();
        }
    });
});

// Close menu when pressing ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        closeMobile();
    }
});
</script>

</body>
</html>