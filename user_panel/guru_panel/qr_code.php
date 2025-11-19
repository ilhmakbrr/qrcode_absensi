<?php
date_default_timezone_set('Asia/Jakarta');
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

include '../../db.php';
include '../auth_user/auth_check_guru.php'; 

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: ../auth/login_user.php?role=guru");
    exit();
}

$user_data = $_SESSION['logged_users']['guru'][$current_user_id];
$guru_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];
$email = $user_data['email'];
$mata_pelajaran = $user_data['mata_pelajaran'];

// Function untuk cek waktu dalam rentang jadwal (TOLERANSI 5 MENIT)
function isWithinScheduleTime($jam_mulai, $jam_selesai) {
    $current_time = date('H:i:s');
    $jam_mulai_toleransi = date('H:i:s', strtotime($jam_mulai . ' -5 minutes'));
    return ($current_time >= $jam_mulai_toleransi && $current_time <= $jam_selesai);
}

// Function untuk generate time slot (30 detik interval)
function getCurrentTimeSlot() {
    $current_timestamp = time();
    $slot_duration = 30;
    $time_slot = floor($current_timestamp / $slot_duration) * $slot_duration;
    return $time_slot;
}

// Function untuk cek apakah QR masih valid (dalam 30 detik)
function isQRValid($qr_timestamp) {
    $current_slot = getCurrentTimeSlot();
    return ($qr_timestamp == $current_slot);
}

// ============================================
// AJAX HANDLERS
// ============================================

// Handle AJAX untuk Generate QR Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_qr') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isset($_POST['jadwal_id']) || empty($_POST['jadwal_id'])) {
        echo json_encode(['success' => false, 'message' => 'Jadwal ID tidak valid']);
        exit();
    }
    
    $jadwal_id = (int)$_POST['jadwal_id'];
    
    $query = "SELECT * FROM jadwal_guru WHERE id = ? AND nip = ? AND status = 'aktif'";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "is", $jadwal_id, $nip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $hari_ini = date('N');
        $hari_jadwal = array('Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7);
        
        $is_today = ($hari_jadwal[$row['hari']] == $hari_ini);
        $is_active_time = isWithinScheduleTime($row['jam_mulai'], $row['jam_selesai']);
        $is_qr_active = ($is_today && $is_active_time);
        
        $today = date('Y-m-d');
        $time_slot = getCurrentTimeSlot();
        $slot_time = date('H:i:s', $time_slot);
        
        $unique_token = hash('sha256', $today . $row['id'] . $row['nip'] . $time_slot . 'salt_absensi_2025_v2');
        
        $qr_simple = sprintf(
            "%d|%s|%s|%s|%s|%s|%d|%s",
            $row['id'],
            $row['nip'],
            $row['mata_pelajaran'],
            $row['kelas'],
            $row['jurusan'],
            $today,
            $time_slot,
            substr($unique_token, 0, 16)
        );
        
        $expire_time = $time_slot + 30;
        $remaining_seconds = max(0, $expire_time - time());
        
        $qr_data_full = array(
            'jadwal_id' => $row['id'],
            'nip' => $row['nip'],
            'nama_guru' => $nama,
            'mata_pelajaran' => $row['mata_pelajaran'],
            'kelas' => $row['kelas'],
            'jurusan' => $row['jurusan'],
            'hari' => $row['hari'],
            'jam' => substr($row['jam_mulai'], 0, 5) . '-' . substr($row['jam_selesai'], 0, 5),
            'tanggal' => date('d-m-Y'),
            'time_slot' => $is_qr_active ? $slot_time : 'Preview Mode'
        );
        
        $response = array(
            'success' => true,
            'data' => $row,
            'qr_string' => $qr_simple,
            'qr_display' => $qr_data_full,
            'is_today' => $is_today,
            'is_active_time' => $is_active_time,
            'is_qr_active' => $is_qr_active,
            'tanggal' => $today,
            'current_time' => date('H:i:s'),
            'time_slot' => $time_slot,
            'slot_time' => $slot_time,
            'expire_at' => date('H:i:s', $expire_time),
            'remaining_seconds' => $remaining_seconds,
            'valid_from' => date('H:i', strtotime($row['jam_mulai'] . ' -5 minutes')),
            'valid_until' => substr($row['jam_selesai'], 0, 5)
        );
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan atau tidak aktif']);
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// Handle AJAX untuk cek status real-time
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_status') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isset($_POST['jadwal_id']) || empty($_POST['jadwal_id'])) {
        echo json_encode(['success' => false, 'message' => 'Jadwal ID tidak valid']);
        exit();
    }
    
    $jadwal_id = (int)$_POST['jadwal_id'];
    $query = "SELECT * FROM jadwal_guru WHERE id = ? AND nip = ? AND status = 'aktif'";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "is", $jadwal_id, $nip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $hari_ini = date('N');
        $hari_jadwal = array('Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7);
        
        $is_today = ($hari_jadwal[$row['hari']] == $hari_ini);
        $is_active_time = isWithinScheduleTime($row['jam_mulai'], $row['jam_selesai']);
        $is_qr_active = ($is_today && $is_active_time);
        
        echo json_encode([
            'success' => true,
            'is_today' => $is_today,
            'is_active_time' => $is_active_time,
            'is_qr_active' => $is_qr_active,
            'current_time' => date('H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan']);
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// Query untuk mengambil jadwal
$jadwal_query = "SELECT * FROM jadwal_guru WHERE nip = ? AND status = 'aktif' ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jam_mulai ASC";
$jadwal_stmt = mysqli_prepare($conn, $jadwal_query);
mysqli_stmt_bind_param($jadwal_stmt, "s", $nip);
mysqli_stmt_execute($jadwal_stmt);
$jadwal_result = mysqli_stmt_get_result($jadwal_stmt);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Absensi - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        :root {
            --primary: #4A90E2;
            --primary-dark: #357ABD;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --dark: #1E293B;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #E0E7FF 0%, #F3E8FF 50%, #FCE7F3 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        } 
        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }       
        .page-container {
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
        }
        
        .header-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 20px;
            padding: 2.5rem 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.3);
            color: white;
        }
        
        .header-card h1 {
            font-size: 1.875rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-card h1 i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem;
            border-radius: 12px;
        }
        
        .header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            color: white;
            font-size: 0.9375rem;
            font-weight: 500;
        }
        
        .info-badge i {
            font-size: 1.125rem;
        }
        
        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            background: white;
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.125rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .total-badge {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 0.625rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
        }
        
        /* Cards Grid Layout */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Schedule Card */
        .schedule-card {
            background: white;
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .schedule-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gray-300);
        }

        .schedule-card.active::before {
            background: linear-gradient(90deg, var(--success) 0%, #059669 100%);
            animation: shimmer 2s infinite;
        }

        .schedule-card.today::before {
            background: linear-gradient(90deg, var(--warning) 0%, #D97706 100%);
        }

        @keyframes shimmer {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .schedule-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .schedule-card.active {
            border-color: var(--success);
            background: linear-gradient(135deg, #ECFDF5 0%, #FFFFFF 100%);
        }

        .schedule-card.today {
            border-color: var(--warning);
            background: linear-gradient(135deg, #FFFBEB 0%, #FFFFFF 100%);
        }

        /* Card Header */
        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .day-badge {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .schedule-card.active .day-badge {
            background: var(--success);
        }

        .schedule-card.today .day-badge {
            background: var(--warning);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gray-400);
        }

        .schedule-card.active .status-dot {
            background: var(--success);
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        .schedule-card.today .status-dot {
            background: var(--warning);
        }

        @keyframes pulse-dot {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            50% { 
                opacity: 0.7; 
                transform: scale(1.1);
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
        }

        .status-text {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .schedule-card.active .status-text {
            color: var(--success);
        }

        .schedule-card.today .status-text {
            color: var(--warning);
        }

        /* Card Body */
        .card-body-custom {
            margin-bottom: 1.25rem;
        }

        .time-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
        }

        .time-display i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .time-text {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--gray-800);
            font-family: 'Courier New', monospace;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 0.625rem 0;
            gap: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 0.875rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-value {
            font-size: 0.9375rem;
            color: var(--gray-800);
            font-weight: 600;
            margin-top: 0.125rem;
        }

        /* Card Footer */
        .card-footer-custom {
            display: flex;
            gap: 0.75rem;
        }

        .btn-qr {
            flex: 1;
            padding: 0.875rem 1.25rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-qr-primary {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-qr-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-qr-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #D97706 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-qr-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-qr-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-qr-secondary:hover {
            background: var(--gray-300);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }
        
        .empty-state h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
        }
        
        .empty-state p {
            font-size: 0.9375rem;
            color: var(--gray-500);
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 2.5rem;
            border: none;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-title i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.625rem;
            border-radius: 10px;
            font-size: 1.25rem;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 2.5rem;
            background: white;
        }
        
        /* Countdown Timer */
        .countdown-timer {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 2px solid var(--warning);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 600;
            color: #92400e;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .countdown-timer i {
            font-size: 1.25rem;
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        #countdown {
            font-size: 1.5rem;
            font-weight: 800;
            color: #B45309;
            background: white;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* QR Container */
        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            text-align: center;
        }

        .qr-container {
            background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);
            border: 4px solid var(--primary);
            border-radius: 20px;
            padding: 2rem;
            display: inline-block;
            box-shadow: 0 8px 24px rgba(74, 144, 226, 0.2);
            position: relative;
            overflow: hidden;
        }

        .qr-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary), var(--info), var(--primary));
            border-radius: 20px;
            z-index: -1;
            animation: borderGlow 3s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        #qrcode {
            background: white;
            padding: 1rem;
            border-radius: 12px;
        }
        
        /* Info Card */
        .info-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-card h6 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card h6 i {
            color: var(--primary);
            font-size: 1.125rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .info-item-value {
            color: var(--gray-800);
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        /* Manual Input Card */
        .manual-input-card {
            background: white;
            border: 2px dashed var(--gray-300);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .manual-input-card h6 {
            color: var(--gray-700);
            font-weight: 700;
            font-size: 0.9375rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .manual-input-card h6 i {
            color: var(--info);
        }

        .manual-input-card textarea {
            font-family: 'Courier New', monospace;
            font-size: 0.8125rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 1rem;
            resize: none;
        }

        .manual-input-card textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        /* Download Button */
        .btn-download {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.4);
        }

        .btn-copy {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s;
        }

        .btn-copy:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .body-card {
                padding: 1.5rem;
                border-radius: 16px;
            }
            
            .header-card {
                padding: 1.5rem 1.25rem;
                border-radius: 16px;
            }

            .header-card h1 {
                font-size: 1.5rem;
            }

            .header-info {
                flex-direction: column;
                align-items: stretch;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .qr-container {
                padding: 1.5rem;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
<?php include 'sidebar_guru.php'; ?>
    <div class="main-content">
        <div class="body-card">
            <div class="page-container">
                <!-- Header Card -->
                <div class="header-card">
                    <h1>
                        <i class="fas fa-qrcode"></i>
                        QR Code Absensi Guru
                    </h1>
                    <div class="header-info">
                        <div class="info-badge">
                            <i class="fas fa-user-circle"></i>
                            <strong><?php echo htmlspecialchars($nama); ?></strong>
                            <span style="opacity: 0.8; margin-left: 0.25rem;">(NIP: <?php echo htmlspecialchars($nip); ?>)</span>
                        </div>
                        <div class="info-badge">
                            <i class="fas fa-book-open"></i>
                            <span><?php echo htmlspecialchars($mata_pelajaran); ?></span>
                        </div>
                        <div class="time-badge">
                            <i class="fas fa-clock"></i>
                            <span id="current-time"><?php echo date('H:i:s'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Section Header -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-check"></i>
                        Jadwal Mengajar Hari Ini
                    </h2>
                    <?php if (mysqli_num_rows($jadwal_result) > 0): ?>
                        <div class="total-badge">
                            <i class="fas fa-list"></i> 
                            <?php echo mysqli_num_rows($jadwal_result); ?> Jadwal
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cards Grid -->
                <?php if (mysqli_num_rows($jadwal_result) > 0): ?>
                    <div class="cards-grid">
                        <?php 
                        $hari_ini = date('N');
                        $hari_mapping = array('Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7);
                        
                        while ($row = mysqli_fetch_assoc($jadwal_result)): 
                            $is_today = ($hari_mapping[$row['hari']] == $hari_ini);
                            $is_active_time = isWithinScheduleTime($row['jam_mulai'], $row['jam_selesai']);
                            $is_qr_active = ($is_today && $is_active_time);
                            
                            $card_class = '';
                            if ($is_qr_active) {
                                $card_class = 'active';
                            } elseif ($is_today) {
                                $card_class = 'today';
                            }
                        ?>
                            <div class="schedule-card <?php echo $card_class; ?>" data-jadwal-id="<?php echo $row['id']; ?>">
                                <!-- Card Header -->
                                <div class="card-header-custom">
                                    <div class="day-badge">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo $row['hari']; ?>
                                    </div>
                                    <div class="status-indicator">
                                        <span class="status-dot"></span>
                                        <span class="status-text">
                                            <?php 
                                            if ($is_qr_active) {
                                                echo 'Aktif';
                                            } elseif ($is_today) {
                                                echo 'Hari Ini';
                                            } else {
                                                echo 'Preview';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="card-body-custom">
                                    <!-- Time -->
                                    <div class="time-display">
                                        <i class="fas fa-clock"></i>
                                        <span class="time-text">
                                            <?php echo substr($row['jam_mulai'], 0, 5); ?> - <?php echo substr($row['jam_selesai'], 0, 5); ?>
                                        </span>
                                    </div>

                                    <!-- Info Rows -->
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Mata Pelajaran</div>
                                            <div class="info-value"><?php echo htmlspecialchars($row['mata_pelajaran']); ?></div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-door-open"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Kelas</div>
                                            <div class="info-value"><?php echo htmlspecialchars($row['kelas']); ?></div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Jurusan</div>
                                            <div class="info-value"><?php echo htmlspecialchars($row['jurusan']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="card-footer-custom">
                                    <button class="btn-qr <?php echo $is_qr_active ? 'btn-qr-primary' : ($is_today ? 'btn-qr-warning' : 'btn-qr-secondary'); ?>" 
                                            onclick="generateQR(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-qrcode"></i>
                                        <?php 
                                        if ($is_qr_active) {
                                            echo 'Lihat QR Code';
                                        } elseif ($is_today) {
                                            echo 'Generate QR';
                                        } else {
                                            echo 'Preview QR';
                                        }
                                        ?>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>Belum Ada Jadwal</h4>
                        <p>Belum ada jadwal mengajar yang aktif saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QR Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-qrcode"></i>
                        QR Code Absensi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="countdown-timer" id="countdownTimer">
                        <i class="fas fa-hourglass-half"></i> 
                        <span>QR akan berubah dalam:</span>
                        <span id="countdown">0:30</span>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="qr-card">
                                <div class="qr-container">
                                    <div id="qrcode"></div>
                                </div>
                                <button class="btn-download" id="downloadQR">
                                    <i class="fas fa-download"></i>
                                    Download QR Code
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="info-card" id="jadwalInfo">
                            </div>
                            
                            <div class="manual-input-card">
                                <h6>
                                    <i class="fas fa-keyboard"></i>
                                    Data QR (Manual Input)
                                </h6>
                                <textarea class="form-control" id="copyText" rows="4" readonly></textarea>
                                <button class="btn-copy" onclick="copyData(event)">
                                    <i class="fas fa-copy"></i>
                                    Salin Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script src="../js/qrcode_handler.js"></script>
    
    <script>
        // Update waktu setiap detik
        setInterval(function() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('current-time').textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    </script>
</body>
</html>
<?php
mysqli_stmt_close($jadwal_stmt);
mysqli_close($conn);
?>