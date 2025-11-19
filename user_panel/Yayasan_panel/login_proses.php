<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Cek apakah sudah login
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'ketua_yayasan') {
    header("Location: yayasan_dashboard.php");
    exit();
}

// Koneksi ke database
include '../../db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    // Validasi input tidak boleh kosong
    if (empty($email) || empty($password)) {
        header("Location: login_yayasan.php?error=empty");
        exit();
    }

    // Query untuk mencari ketua yayasan berdasarkan email
    $query = "SELECT * FROM ketua_yayasan WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        // Error database
        error_log("Database error: " . mysqli_error($conn));
        header("Location: login_yayasan.php?error=system");
        exit();
    }

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID untuk keamanan
            session_regenerate_id(true);
            
            // Set session untuk ketua yayasan
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = 'ketua_yayasan';
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT']; // Untuk deteksi session hijacking
            
            // Redirect ke dashboard ketua yayasan
            header("Location: yayasan_dashboard.php");
            exit();
        } else {
            // Password salah
            header("Location: login_yayasan.php?error=invalid");
            exit();
        }
    } else {
        // Email tidak ditemukan
        header("Location: login_yayasan.php?error=invalid");
        exit();
    }
} else {
    // Jika bukan POST request, redirect ke login
    header("Location: login_yayasan.php");
    exit();
}
?>