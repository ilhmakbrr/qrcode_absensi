<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../../../db.php';

// Ambil data POST
$data = json_decode(file_get_contents("php://input"));

// Debug log
error_log("Data diterima: " . print_r($data, true));

if (empty($data->attendance_data)) {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "Data absensi tidak boleh kosong"
    ));
    exit;
}

try {
    // Mulai transaksi
    mysqli_begin_transaction($conn);

    $success_count = 0;
    $errors = array();

    foreach ($data->attendance_data as $attendance) {
        // Validasi data wajib
        if (empty($attendance->mata_pelajaran)) {
            $errors[] = "Mata pelajaran kosong untuk siswa: " . $attendance->nama;
            continue;
        }

        // Query untuk menyimpan data absensi dengan mata_pelajaran
        $query = "INSERT INTO absensi 
                  (student_id, nis, nama, kelas, jurusan, mata_pelajaran, status, tanggal) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                  status = VALUES(status),
                  mata_pelajaran = VALUES(mata_pelajaran),
                  updated_at = CURRENT_TIMESTAMP";

        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            $errors[] = "Prepare failed: " . mysqli_error($conn);
            continue;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssss",
            $attendance->student_id,
            $attendance->nis,
            $attendance->nama,
            $attendance->kelas,
            $attendance->jurusan,
            $attendance->mata_pelajaran,
            $attendance->status,
            $attendance->tanggal
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
            error_log("Berhasil simpan: " . $attendance->nama . " - " . $attendance->mata_pelajaran);
        } else {
            $errors[] = "Execute failed untuk " . $attendance->nama . ": " . mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    }

    if ($success_count > 0) {
        // Commit transaksi jika ada yang berhasil
        mysqli_commit($conn);

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "message" => "Absensi berhasil disimpan",
            "total_saved" => $success_count,
            "errors" => $errors
        ));
    } else {
        // Rollback jika semua gagal
        mysqli_rollback($conn);
        
        http_response_code(500);
        echo json_encode(array(
            "success" => false,
            "message" => "Tidak ada data yang berhasil disimpan",
            "errors" => $errors
        ));
    }

} catch (Exception $exception) {
    // Rollback transaksi jika terjadi kesalahan
    mysqli_rollback($conn);

    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error: " . $exception->getMessage()
    ));
}

mysqli_close($conn);
?>