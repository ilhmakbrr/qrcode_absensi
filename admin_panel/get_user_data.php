<?php
session_start();

// Cek apakah user sudah login dan memiliki role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Koneksi ke database
include '../db.php';

// Cek apakah koneksi berhasil
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Cek apakah parameter yang diperlukan ada
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$type = $_GET['type'];
$id = (int)$_GET['id'];

// Validasi type
if (!in_array($type, ['guru', 'siswa', 'wali_kelas'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit();
}

try {
    // Query berdasarkan type
    if ($type === 'guru') {
        $query = "SELECT id, nip, nama, email, mata_pelajaran FROM guru WHERE id = ?";
    } elseif ($type === 'siswa') {
        $query = "SELECT id, nis, nama, email, kelas, jurusan FROM siswa WHERE id = ?";
    } elseif ($type === 'wali_kelas') {
        $query = "SELECT wk.id, wk.guru_id, g.nip, g.nama, wk.kelas, wk.tahun_ajaran 
                  FROM wali_kelas wk 
                  JOIN guru g ON wk.guru_id = g.id 
                  WHERE wk.id = ?";
    } else { // ketua yaaysan
        $query = "SELECT id, nama, tanggal_lahir FROM ketua_yayasan WHERE id = ?"; 
    }
    
    
    // Prepare statement untuk keamanan
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . mysqli_error($conn));
    }
   
    
    // Bind parameter
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    // Execute query
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }
    
    // Get result
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Get result failed: " . mysqli_stmt_error($stmt));
    }
    
    $data = mysqli_fetch_assoc($result);
    
    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data not found']);
        exit();
    }
    
    // Return data
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
    // Close statement
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    // Log error
    error_log("Error in get_user_data.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}

// Close connection
mysqli_close($conn);
?>