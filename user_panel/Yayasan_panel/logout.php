<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Bersihkan data session user dari logged_users jika ada
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Hapus dari logged_users array (untuk tracking guru online)
    if (isset($_SESSION['logged_users'][$role][$user_id])) {
        unset($_SESSION['logged_users'][$role][$user_id]);
    }
}

// Hapus semua session variables
$_SESSION = array();

// Hapus session cookie jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login dengan pesan sukses
header("Location: login_yayasan.php?success=logout");
exit();
?>