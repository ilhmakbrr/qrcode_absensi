<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Cek apakah user adalah ketua yayasan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ketua_yayasan') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../../db.php';

try {

    
    $online_users = [
        'guru' => []
    ];
    
    // Ambil data guru yang sedang online
    if (isset($_SESSION['logged_users']['guru']) && !empty($_SESSION['logged_users']['guru'])) {
        foreach ($_SESSION['logged_users']['guru'] as $user_id => $session_data) {
            // Hitung durasi login
            $login_time = $session_data['login_time'];
            $duration = time() - $login_time;
            
            // Format durasi (jam:menit)
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            $duration_text = sprintf("%02d:%02d", $hours, $minutes);
            
            // Ambil data lengkap dari database
            $query = "SELECT id, nip, nama, email, mata_pelajaran FROM guru WHERE id = " . intval($user_id);
            $result = mysqli_query($conn, $query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $guru_data = mysqli_fetch_assoc($result);
                
                $online_users['guru'][] = [
                    'id' => $guru_data['id'],
                    'nip' => $guru_data['nip'],
                    'nama' => $guru_data['nama'],
                    'email' => $guru_data['email'],
                    'mata_pelajaran' => $guru_data['mata_pelajaran'],
                    'login_time' => date('H:i:s', $login_time),
                    'login_date' => date('d/m/Y', $login_time),
                    'duration' => $duration_text,
                    'duration_seconds' => $duration
                ];
            }
        }
    }
    
    
    // Hitung total
    $total_guru_online = count($online_users['guru']);
    
    echo json_encode([
        'success' => true,
        'data' => $online_users,
        'summary' => [
            'total_guru_online' => $total_guru_online
            
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>