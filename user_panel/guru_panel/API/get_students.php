<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../../../db.php'; // Menggunakan koneksi mysqli dari db.php

// Ambil parameter
$jurusan = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

if (empty($jurusan) || empty($kelas)) {
    http_response_code(400);
    echo json_encode(array("message" => "Jurusan dan kelas harus diisi"));
    exit;
}

try {
    // Query untuk mengambil data siswa berdasarkan jurusan dan kelas
    $query = "SELECT id, nis, nama, kelas, jurusan, email, created_at 
              FROM siswa 
              WHERE jurusan = ? AND kelas = ? 
              ORDER BY nama ASC";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $jurusan, $kelas);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $students = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }

    mysqli_stmt_close($stmt);

    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "message" => "Data siswa berhasil diambil",
        "data" => $students,
        "total" => count($students)
    ));
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error: " . $exception->getMessage()
    ));
}

mysqli_close($conn);
?>