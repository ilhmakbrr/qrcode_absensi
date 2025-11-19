<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

// CRITICAL: Ambil user_id dari parameter URL - JANGAN dari session global
$sidebar_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Fallback: jika tidak ada user_id di URL dan hanya 1 guru login
if (!$sidebar_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $sidebar_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

// Validasi: pastikan user_id valid
if (!$sidebar_user_id || !isset($_SESSION['logged_users']['guru'][$sidebar_user_id])) {
    header("Location: ../auth/login_user.php?role=guru");
    exit();
}

// Ambil data guru dari session array (BUKAN dari session global)
$sidebar_user_data = $_SESSION['logged_users']['guru'][$sidebar_user_id];
$guru_nama = $sidebar_user_data['nama'];
$guru_nip = $sidebar_user_data['nip'];
$guru_email = $sidebar_user_data['email'] ?? '';
$guru_id = $sidebar_user_data['user_id'];
$guru_mata_pelajaran = $sidebar_user_data['mata_pelajaran'] ?? '';

// Pastikan koneksi database tersedia
if (!isset($conn)) {
    include_once __DIR__ . '/../../db.php';
}

// Function untuk cek apakah guru adalah wali kelas
if (!function_exists('isWaliKelas')) {
    function isWaliKelas($guru_id, $conn) {
        if (!$guru_id || $guru_id == 0) return false;
        
        $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
        $query = "SELECT wk.*, g.nama, g.nip FROM wali_kelas wk 
                  JOIN guru g ON wk.guru_id = g.id 
                  WHERE wk.guru_id = ? AND wk.tahun_ajaran = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $guru_id, $tahun_ajaran);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                return $data;
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }
}

// Cek status wali kelas
$wali_kelas_data = isWaliKelas($guru_id, $conn);
$is_wali_kelas = ($wali_kelas_data !== false);
$kelas_wali = $is_wali_kelas ? $wali_kelas_data['kelas'] : null;

// Tentukan halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sidebar Guru - Sistem Absensi</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">

<style>
    
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #f5f7fb;
}

/* ===== Main Content Responsive ===== */
.main-content {
    margin-left: 260px;
    transition: margin-left 0.3s ease;
    padding: 20px;
    min-height: 100vh;
}


.sidebar.collapsed ~ .main-content {
    margin-left: 80px !important;
}

.sidebar {
    width: 260px;
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    color: white;
    min-height: 100vh;
    transition: width 0.3s ease;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 900;
}

.sidebar.collapsed {
    width: 80px !important;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    background: linear-gradient(135deg, #2980b9, #3498db);
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.logo-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    font-size: 22px;
}

.logo-text {
    font-weight: 700;
    font-size: 18px;
    white-space: nowrap;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.sidebar.collapsed .logo-text {
    opacity: 0;
    visibility: hidden;
}

/* ===== Tombol toggle di header ===== */
.toggle-btn {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    transition: transform 0.3s;
}

.toggle-btn:hover {
    transform: rotate(90deg);
}

/* Update the open button styles */
.open-btn {
    position: fixed;
    top: 25px;
    left: 85px;
    background: #2980b9;
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
    background: #3498db;
    transform: scale(1.05) rotate(0deg) !important;
}

.sidebar.collapsed ~ .open-btn {
    display: flex;
    left: 90px;
    transform: rotate(90deg);
}

/* ===== User Info ===== */
.user-info {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    font-size: 14px;
    color: rgba(255,255,255,0.85);
}

.user-info strong {
    display: block;
    margin-bottom: 4px;
}

.wali-kelas-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 6px;
    box-shadow: 0 2px 6px rgba(139, 92, 246, 0.4);
}

.sidebar.collapsed .user-info {
    display: none;
}

/* ===== Menu ===== */
.nav-menu {
    padding: 15px 0;
    padding-bottom: 80px; /* Extra space for footer */
}

.menu-section-divider {
    border-top: 1px solid rgba(255,255,255,0.1);
    margin: 15px 20px;
}

.menu-section-title {
    color: rgba(255,255,255,0.5);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 20px 5px;
    margin-top: 10px;
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
    color: rgba(255,255,255,0.85);
    padding: 12px 20px;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    border-left: 4px solid #3498db;
    color: #fff;
}

/* Menu wali kelas styling */
.nav-item.wali-kelas-menu {
    border-left: 3px solid transparent;
}

.nav-item.wali-kelas-menu:hover {
    background: rgba(139, 92, 246, 0.15);
    border-left-color: #8b5cf6;
}

.nav-item.wali-kelas-menu.active {
    background: rgba(139, 92, 246, 0.2);
    border-left: 4px solid #8b5cf6;
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

/* ===== Footer ===== */
.sidebar-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 260px;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding: 15px 20px;
    background: rgba(44, 62, 80, 0.95);
    backdrop-filter: blur(10px);
    transition: width 0.3s ease;
    z-index: 901;
}

.sidebar.collapsed .sidebar-footer {
    width: 80px;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    color: white;
    text-decoration: none;
    background: rgba(255,255,255,0.1);
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s;
}

.logout-btn:hover {
    background: rgba(255,77,77,0.3);
    color: white;
}

.sidebar.collapsed .logout-btn {
    justify-content: center;
}

.sidebar.collapsed .logout-btn span {
    display: none;
}

/* ===== Tooltip saat collapsed ===== */
.nav-item[title] {
    position: relative;
}

.sidebar.collapsed .nav-item:hover::after {
    content: attr(title);
    position: absolute;
    left: 85px;
    background: #2c3e50;
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: nowrap;
    font-size: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    z-index: 1000;
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
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
    }
    
    .sidebar.collapsed ~ .open-btn {
        left: 20px;
    }
}
</style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
    <div class="logo">
            <div class="logo-icon">
                <img src="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp" alt="logo SMA2PGRI" style="width:30px; height:35px;">
            </div>
            <div class="logo-text">SMA 2 PGRI</div>
        </div>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="user-info">
        <strong><?php echo htmlspecialchars($guru_nama); ?></strong>
        <small>NIP: <?php echo htmlspecialchars($guru_nip); ?></small>
        <?php if ($is_wali_kelas): ?>
            <div class="wali-kelas-badge">
                <i class="fas fa-star"></i>
                <span>Wali Kelas <?php echo htmlspecialchars($kelas_wali); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <nav class="nav-menu">
        <!-- Menu Guru Mata Pelajaran -->
        <a href="dashboard_guru.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'dashboard_guru.php') ? 'active' : ''; ?>" 
           title="Dashboard">
            <div class="nav-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            <div class="nav-text">Dashboard</div>
        </a>

        <a href="qr_code.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'qr_code.php') ? 'active' : ''; ?>" 
           title="QR Code">
            <div class="nav-icon"><i class="bi bi-qr-code"></i></div>
            <div class="nav-text">Buat QR Code</div>
        </a>

        <a href="absensi_siswa.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'absensi_siswa.php') ? 'active' : ''; ?>" 
           title="Absensi">
            <div class="nav-icon"><i class="bi bi-check-circle"></i></div>
            <div class="nav-text">Absensi Siswa</div>
        </a>

        <a href="riwayat_absensi.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'riwayat_absensi.php') ? 'active' : ''; ?>" 
           title="Riwayat">
            <div class="nav-icon"><i class="bi bi-journal-text"></i></div>
            <div class="nav-text">Riwayat Absensi</div>
        </a>

        <a href="jadwal_mengajar.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'jadwal_mengajar.php') ? 'active' : ''; ?>" 
           title="Jadwal">
            <div class="nav-icon"><i class="bi bi-calendar3"></i></div>
            <div class="nav-text">Jadwal Mengajar</div>
        </a>

        <a href="laporan_absensi.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'laporan_absensi.php') ? 'active' : ''; ?>" 
           title="Laporan">
            <div class="nav-icon"><i class="bi bi-file-text"></i></div>
            <div class="nav-text">Laporan Absensi</div>
        </a>

        <a href="statistik_absensi.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item <?php echo ($current_page == 'statistik_absensi.php') ? 'active' : ''; ?>" 
           title="Statistik">
            <div class="nav-icon"><i class="bi bi-bar-chart"></i></div>
            <div class="nav-text">Statistik Absensi</div>
        </a>

        
        <?php if ($is_wali_kelas): ?>
        <!-- Divider untuk Menu Wali Kelas -->
        <div class="menu-section-divider"></div>
        <div class="menu-section-title">
            <i class="fas fa-user-tie"></i> Wali Kelas
        </div>

        <a href="profil_siswa.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item wali-kelas-menu <?php echo ($current_page == 'profil_siswa.php') ? 'active' : ''; ?>" 
           title="Profil Siswa">
            <div class="nav-icon"><i class="bi bi-people"></i></div>
            <div class="nav-text">Profil Siswa Kelas</div>
        </a>

        <a href="rekap_siswa.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item wali-kelas-menu <?php echo ($current_page == 'rekap_siswa.php') ? 'active' : ''; ?>" 
           title="Rekap Absensi Kelas">
            <div class="nav-icon"><i class="bi bi-clipboard-data"></i></div>
            <div class="nav-text">Rekap Absensi Kelas</div>
        </a>

        <a href="laporan_persiswa.php?user_id=<?php echo $sidebar_user_id; ?>" 
           class="nav-item wali-kelas-menu <?php echo ($current_page == 'laporan_persiswa.php') ? 'active' : ''; ?>" 
           title="Laporan Per Siswa">
            <div class="nav-icon"><i class="bi bi-person-lines-fill"></i></div>
            <div class="nav-text">Laporan Per Siswa</div>
        </a>

        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../auth_user/login_user.php?logout&role=guru&id=<?php echo $sidebar_user_id; ?>" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Tombol minimalis di samping sidebar -->
<button class="open-btn" id="openBtn" onclick="openSidebar()">
    <i class="bi bi-list"></i>
</button>


<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    sidebar.classList.toggle('collapsed');
    
    // Update button rotation
    if (!sidebar.classList.contains('collapsed')) {
        openBtn.style.transform = 'rotate(0deg)';
    }
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openBtn');
    
    sidebar.classList.remove('collapsed');
    openBtn.style.transform = 'rotate(90deg)';
}

// Mobile menu toggle
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');
    
    // Terapkan state yang tersimpan
    if (savedState === 'collapsed') {
        sidebar.classList.add('collapsed');
    }
});

// Set active menu based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop().split('?')[0];
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openBtn');
        
        if (window.innerWidth <= 768 && 
            sidebar.classList.contains('mobile-open') &&
            !sidebar.contains(event.target) && 
            !openBtn.contains(event.target)) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    console.log('Sidebar Guru - User ID: <?php echo $sidebar_user_id; ?>');
});
</script>
</body>
</html>