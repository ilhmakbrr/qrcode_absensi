<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

// Include database connection jika belum
if (!isset($conn)) {
    include '../../db.php';
}

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Jika tidak ada user_id di URL, coba ambil dari session (jika hanya 1 guru login)
if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
        // Redirect dengan user_id di URL untuk konsistensi
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?user_id=" . $current_user_id);
        exit();
    } else {
        // Tidak ada user_id dan ada multiple guru atau tidak ada guru login
        header("Location: login_user.php?role=guru&error=no_session");
        exit();
    }
}

// Validasi 1: Cek apakah user_id ada di session
if (!isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: login_user.php?role=guru&error=unauthorized");
    exit();
}

// Ambil data dari session
$session_data = $_SESSION['logged_users']['guru'][$current_user_id];

// Validasi 2: Verifikasi dengan database untuk keamanan tambahan
$verify_query = "SELECT id, nip, nama, email, mata_pelajaran FROM guru WHERE id = '" . mysqli_real_escape_string($conn, $current_user_id) . "'";
$verify_result = mysqli_query($conn, $verify_query);

if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
    // ID tidak ditemukan di database
    unset($_SESSION['logged_users']['guru'][$current_user_id]);
    header("Location: login_user.php?role=guru&error=invalid_user");
    exit();
}

$db_data = mysqli_fetch_assoc($verify_result);

// Validasi 3: Pastikan NIP di session cocok dengan database
if ($session_data['nip'] !== $db_data['nip']) {
    // Data tidak cocok - possible tampering
    unset($_SESSION['logged_users']['guru'][$current_user_id]);
    header("Location: login_user.php?role=guru&error=session_mismatch");
    exit();
}

// Validasi 4: Opsional - Cek session timeout (8 jam)
$session_lifetime = 8 * 60 * 60; // 8 jam dalam detik
if (isset($session_data['login_time']) && (time() - $session_data['login_time']) > $session_lifetime) {
    // Session expired
    unset($_SESSION['logged_users']['guru'][$current_user_id]);
    header("Location: login_user.php?role=guru&error=session_expired");
    exit();
}

// Semua validasi berhasil - Set variabel global untuk digunakan di halaman
$guru_id = $db_data['id'];
$nip = $db_data['nip'];
$nama = $db_data['nama'];
$email = $db_data['email'];
$mata_pelajaran = $db_data['mata_pelajaran'];

// Set juga variable $user_data untuk kompatibilitas
$user_data = [
    'user_id' => $guru_id,
    'nip' => $nip,
    'nama' => $nama,
    'email' => $email,
    'mata_pelajaran' => $mata_pelajaran,
    'role' => 'guru'
];


function logout_guru($user_id) {
    if (isset($_SESSION['logged_users']['guru'][$user_id])) {
        unset($_SESSION['logged_users']['guru'][$user_id]);
    }
    
    // Jika tidak ada user yang login, destroy session
    if (empty($_SESSION['logged_users']['siswa']) && empty($_SESSION['logged_users']['guru'])) {
        session_destroy();
    }
    
    header("Location: login_user.php?role=guru");
    exit();
}
?>