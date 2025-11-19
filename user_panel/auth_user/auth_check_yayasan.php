<?php

// Pastikan session sudah dimulai dengan nama yang sama
if (session_status() === PHP_SESSION_NONE) {
    session_name('SCHOOL_ATTENDANCE_SYSTEM');
    session_start();
}

// PROTEKSI 1: Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // User belum login, redirect ke halaman login
    header("Location: ../yayasan_panel/login_yayasan.php?error=unauthorized");
    exit();
}

// PROTEKSI 2: Cek apakah role adalah ketua_yayasan
if ($_SESSION['role'] !== 'ketua_yayasan') {
    // Bukan ketua yayasan, redirect ke login
    header("Location: ../yayasan_panel/login_yayasan.php?error=unauthorized");
    exit();
}

// PROTEKSI 3: Session Timeout (30 menit tidak aktif)
$timeout_duration = 1800; // 30 menit dalam detik

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    if ($elapsed_time > $timeout_duration) {
        // Session timeout, hapus session dan redirect
        session_unset();
        session_destroy();
        header("Location: ../yayasan_panel/login_yayasan.php?error=session");
        exit();
    }
}

// Update waktu aktivitas terakhir
$_SESSION['last_activity'] = time();

// PROTEKSI 4: Cek apakah user masih ada di database
if (isset($_SESSION['user_id'])) {
    // Path ke db.php dari folder auth_user
    require_once __DIR__ . '/../../db.php';
    
    $user_id = intval($_SESSION['user_id']);
    $check_query = "SELECT id FROM ketua_yayasan WHERE id = $user_id LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);
    
    if (!$check_result || mysqli_num_rows($check_result) === 0) {
        // User tidak ditemukan di database, logout paksa
        session_unset();
        session_destroy();
        header("Location: ../yayasan_panel/login_yayasan.php?error=unauthorized");
        exit();
    }
}

// PROTEKSI 5: Cek session hijacking (opsional - keamanan tambahan)
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} else {
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        // Kemungkinan session hijacking
        session_unset();
        session_destroy();
        header("Location: ../yayasan_panel/login_yayasan.php?error=session");
        exit();
    }
}
?>