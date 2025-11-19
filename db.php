<?php
$host = "localhost";       
$user = "root";          
$pass = "";                
$dbname = "db_absensi_siswa";  

// Membuat koneksi ke database
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>