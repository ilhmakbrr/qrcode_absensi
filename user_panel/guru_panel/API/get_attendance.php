<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../../../db.php';

// Ambil parameter
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';

if (empty($kelas) || empty($tanggal)) {
    http_response_code(400);
    echo json_encode(array("message" => "Kelas dan tanggal harus diisi"));
    exit;
}

try {
    // Query untuk mengambil data absensi
    $query = "SELECT * FROM absensi 
              WHERE kelas = ? AND tanggal = ? 
              ORDER BY nama ASC";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $kelas, $tanggal);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $attendance = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance[] = $row;
    }

    mysqli_stmt_close($stmt);

    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "data" => $attendance,
        "total" => count($attendance)
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