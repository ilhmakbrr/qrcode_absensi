<?php
// Perbaikan path untuk file db.php
include '../../db.php';

// Validasi input POST
if (!isset($_POST['nama_lengkap']) || !isset($_POST['username']) || !isset($_POST['password'])) {
    die("Data tidak lengkap!");
}

// Sanitasi input
$nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
$username = mysqli_real_escape_string($conn, $_POST['username']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Cek apakah username sudah ada
$check_sql = "SELECT username FROM users WHERE username = '$username'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    // Username sudah ada
    header("Location: register_admin.php?error=username_exists");
    exit();
}

// Insert data baru
$sql = "INSERT INTO users (nama_lengkap, username, password, role) 
        VALUES ('$nama', '$username', '$password', 'admin')";

if (mysqli_query($conn, $sql)) {
    header("Location: register_admin.php?success=1");
} else {
    header("Location: register_admin.php?error=database");
}

mysqli_close($conn);
?>