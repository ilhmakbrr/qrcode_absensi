<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

include '../../db.php';

// AUTO-DETECT user_id
$user_id = null;

// Prioritas 1: Dari parameter GET
if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
}
// Prioritas 2: Dari session logged_users
elseif (isset($_SESSION['logged_users']['guru']) && !empty($_SESSION['logged_users']['guru'])) {
    // Ambil user_id pertama dari array
    $user_id = array_key_first($_SESSION['logged_users']['guru']);
}
// Prioritas 3: Dari session user_id (jika hanya 1 user)
elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'guru') {
    $user_id = intval($_SESSION['user_id']);
}

if ($user_id) {
    // Update logout time di database
    $update_session = "UPDATE login_sessions 
                      SET logout_time = NOW(),
                          session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW())
                      WHERE user_id = ? 
                      AND role = 'guru' 
                      AND logout_time IS NULL
                      ORDER BY login_time DESC 
                      LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $update_session);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $success = mysqli_stmt_execute($stmt);
    
    // Log untuk debugging (opsional)
    if ($success) {
        $affected = mysqli_stmt_affected_rows($stmt);
        // error_log("Logout guru ID: $user_id - Rows updated: $affected");
    }
    
    mysqli_stmt_close($stmt);
    
    // Hapus dari session
    if (isset($_SESSION['logged_users']['guru'][$user_id])) {
        unset($_SESSION['logged_users']['guru'][$user_id]);
    }
    
    // Jika tidak ada user yang login, destroy session
    if (empty($_SESSION['logged_users']['siswa']) && empty($_SESSION['logged_users']['guru'])) {
        session_destroy();
    }
} else {
    // Fallback: logout semua guru jika user_id tidak ditemukan
    if (isset($_SESSION['logged_users']['guru'])) {
        foreach ($_SESSION['logged_users']['guru'] as $id => $data) {
            $update_session = "UPDATE login_sessions 
                              SET logout_time = NOW(),
                                  session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW())
                              WHERE user_id = ? 
                              AND role = 'guru' 
                              AND logout_time IS NULL
                              ORDER BY login_time DESC 
                              LIMIT 1";
            
            $stmt = mysqli_prepare($conn, $update_session);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    $_SESSION['logged_users']['guru'] = [];
    
    if (empty($_SESSION['logged_users']['siswa'])) {
        session_destroy();
    }
}

mysqli_close($conn);
header("Location: ../auth_user/login_user.php?role=guru");
exit();
?>