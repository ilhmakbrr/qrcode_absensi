<?php
// Matikan error display untuk production
error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set('Asia/Jakarta');
include '../auth_user/auth_check_siswa.php';


function isQRValid($qr_timestamp) {
    $current_timestamp = time();
    $slot_duration = 30; // 30 DETIK
    $current_slot = floor($current_timestamp / $slot_duration) * $slot_duration;
    return ($qr_timestamp == $current_slot);
}

// Handle submit absensi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_absensi') {
    header('Content-Type: application/json');
    
    $qr_data = trim($_POST['qr_data']);
    
    // Parse QR: ID|NIP|MAPEL|KELAS|JURUSAN|TANGGAL|TIMESTAMP|TOKEN
    $parts = explode('|', $qr_data);
    
    if (count($parts) !== 8) {
        echo json_encode(['success' => false, 'message' => 'Format QR Code tidak valid']);
        exit();
    }
    
    list($jadwal_id, $nip, $mata_pelajaran, $qr_kelas, $qr_jurusan, $tanggal, $qr_timestamp, $token) = $parts;
    
    // VALIDASI 1: Cek timestamp QR (harus dalam slot 30 DETIK yang sama)
    if (!isQRValid((int)$qr_timestamp)) {
        echo json_encode([
            'success' => false, 
            'message' => 'QR Code sudah kadaluarsa! QR berubah setiap 30 detik.',
            'error_type' => 'expired'
        ]);
        exit();
    }
    
    // VALIDASI 2: Cek tanggal
    if ($tanggal !== date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'QR Code kadaluarsa (tanggal tidak valid)']);
        exit();
    }
    
    // VALIDASI 3: Cek kelas dan jurusan siswa
    if ($qr_kelas !== $kelas) {
        echo json_encode(['success' => false, 'message' => 'QR Code tidak sesuai dengan kelas Anda']);
        exit();
    }
    
    if (!empty($jurusan) && $qr_jurusan !== $jurusan) {
        echo json_encode(['success' => false, 'message' => 'QR Code tidak sesuai dengan jurusan Anda']);
        exit();
    }
    
    // VALIDASI 4: Cek jadwal
    $query = "SELECT * FROM jadwal_guru WHERE id = ? AND nip = ? AND status = 'aktif'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $jadwal_id, $nip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak valid']);
        exit();
    }
    
    $jadwal_data = mysqli_fetch_assoc($result);
    
    // VALIDASI 5: Cek waktu (dalam jam pelajaran + toleransi)
    $current_time = date('H:i:s');
    $jam_mulai_toleransi = date('H:i:s', strtotime($jadwal_data['jam_mulai'] . ' -5 minutes'));
    
    if ($current_time < $jam_mulai_toleransi || $current_time > $jadwal_data['jam_selesai']) {
        echo json_encode(['success' => false, 'message' => 'Absensi hanya dapat dilakukan saat jam pelajaran']);
        exit();
    }
    
    // VALIDASI 6: Verifikasi token dengan timestamp (30 DETIK)
    $slot_duration = 30; // HARUS SAMA DENGAN GURU!
    $current_slot = floor(time() / $slot_duration) * $slot_duration;
    $expected_token = substr(hash('sha256', $tanggal . $jadwal_id . $nip . $current_slot . 'salt_absensi_2025_v2'), 0, 16);
    
    if ($token !== $expected_token) {
        echo json_encode([
            'success' => false, 
            'message' => 'Token tidak valid! QR Code mungkin screenshot/foto lama.',
            'error_type' => 'invalid_token'
        ]);
        exit();
    }
    
    // Cek sudah absen untuk mata pelajaran ini hari ini
    $check_query = "SELECT * FROM absensi WHERE student_id = ? AND tanggal = ? AND mata_pelajaran = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "iss", $siswa_id, $tanggal, $mata_pelajaran);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        $existing = mysqli_fetch_assoc($check_result);
        echo json_encode([
            'success' => false, 
            'message' => 'Anda sudah melakukan absensi untuk mata pelajaran ' . $mata_pelajaran . ' hari ini',
            'existing_status' => $existing['status']
        ]);
        exit();
    }
    
    // Tentukan status berdasarkan waktu
    $status = 'hadir';
    if ($current_time > $jadwal_data['jam_mulai']) {
        $status = 'hadir';
    }
    
    // INSERT ABSENSI - DENGAN MATA PELAJARAN
    $insert_query = "INSERT INTO absensi (student_id, nis, nama, kelas, jurusan, mata_pelajaran, status, tanggal) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "isssssss", 
        $siswa_id, $nis, $nama, $kelas, $jurusan, $mata_pelajaran, $status, $tanggal
    );
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $current_timestamp = time();
        $next_slot = ceil($current_timestamp / $slot_duration) * $slot_duration;
        $remaining = $next_slot - $current_timestamp;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Absensi berhasil! Status: ' . strtoupper($status),
            'data' => [
                'nama' => $nama,
                'nis' => $nis,
                'kelas' => $kelas,
                'jurusan' => $jurusan,
                'mata_pelajaran' => $mata_pelajaran,  
                'waktu' => date('H:i:s'),
                'tanggal' => date('d/m/Y', strtotime($tanggal)),
                'status' => strtoupper($status),
                'qr_slot' => date('H:i:s', $qr_timestamp),
                'remaining_valid' => $remaining
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan absensi: ' . mysqli_error($conn)]);
    }
    
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Absensi - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
   
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .main-content {
                margin: 10px;
                padding: 1rem;
            }
        }

        /* Header Section */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Card Style */
        .card-modern {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card-modern:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* User Info Card */
        .user-card {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border: none;
        }

        .user-card:hover {
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.25);
        }

        .user-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-item label {
            font-size: 0.75rem;
            opacity: 0.9;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item .value {
            font-size: 1rem;
            font-weight: 600;
        }

        /* Scan Section */
        .scan-card {
            text-align: center;
        }

        .scan-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 1.5rem;
        }

        /* Button Styles */
        .btn-scan {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 400px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }

        .btn-scan:active {
            transform: translateY(0);
        }

        .btn-stop {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            max-width: 400px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            margin: 0 auto;
        }

        .btn-stop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
        }

        /* Video Container */
        #video-container {
            display: none;
            margin: 1.5rem auto;
            max-width: 500px;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid #3b82f6;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
            position: relative;
        }

        #video-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            border: 3px solid rgba(59, 130, 246, 0.5);
            border-radius: 16px;
            z-index: 1;
            pointer-events: none;
        }

        #video {
            width: 100%;
            display: block;
            background: #000;
        }

        /* Manual Input */
        .manual-input {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .manual-input h6 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 1rem;
            text-align: center;
        }

        .manual-input textarea {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            font-size: 0.85rem;
            font-family: 'Courier New', monospace;
            transition: all 0.3s ease;
            resize: none;
        }

        .manual-input textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn-submit {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        /* Modal */
        .result-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
            padding: 1rem;
        }

        .result-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .result-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .result-icon.success {
            background: #dcfce7;
            color: #10b981;
        }

        .result-icon.error {
            background: #fee2e2;
            color: #ef4444;
        }

        .result-icon.loading {
            background: #dbeafe;
            color: #3b82f6;
        }

        .result-title {
            text-align: center;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .result-message {
            text-align: center;
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .result-details {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .result-details > div {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-details > div:last-child {
            border-bottom: none;
        }

        .result-details strong {
            color: #475569;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            background: #dcfce7;
            color: #166534;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .error-badge {
            padding: 0.75rem 1rem;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-top: 1rem;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .warning-badge {
            padding: 0.75rem 1rem;
            background: #fef3c7;
            color: #92400e;
            border-radius: 8px;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .btn-close-result {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-close-result:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Responsive */
        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .btn-scan, .btn-stop {
                padding: 0.875rem 1.5rem;
                font-size: 0.95rem;
            }

            .result-content {
                padding: 1.5rem;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_siswa.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-qrcode"></i> Scan QR Absensi</h1>
            <p>Arahkan kamera ke QR Code yang ditampilkan guru untuk melakukan absensi</p>
        </div>

        <!-- User Info Card -->
        <div class="card-modern user-card">
            <div class="card-title">
                <i class="fas fa-user-circle"></i>
                <span>Informasi Siswa</span>
            </div>
            <div class="user-info-grid">
                <div class="info-item">
                    <label>Nama Lengkap</label>
                    <div class="value"><?php echo htmlspecialchars($nama); ?></div>
                </div>
                <div class="info-item">
                    <label>NIS</label>
                    <div class="value"><?php echo htmlspecialchars($nis); ?></div>
                </div>
                <div class="info-item">
                    <label>Kelas</label>
                    <div class="value"><?php echo htmlspecialchars($kelas); ?></div>
                </div>
                <div class="info-item">
                    <label>Jurusan</label>
                    <div class="value"><?php echo htmlspecialchars($jurusan); ?></div>
                </div>
            </div>
        </div>

        <!-- Scan Card -->
        <div class="card-modern scan-card">
            <h5 class="card-title">Scan QR Code</h5>
            
            <button id="startScanBtn" class="btn-scan" onclick="startScan()">
                <i class="fas fa-camera"></i>
                <span>Buka Kamera</span>
            </button>
            
            <button id="stopScanBtn" class="btn-stop" onclick="stopScan()">
                <i class="fas fa-stop-circle"></i>
                <span>Tutup Kamera</span>
            </button>

            <div id="video-container">
                <video id="video" autoplay playsinline></video>
            </div>

            <!-- Manual Input -->
            <div class="manual-input">
                <h6><i class="fas fa-keyboard"></i> Input Manual (Opsional)</h6>
                <textarea id="manualInput" class="form-control" rows="3" placeholder="Tempel kode QR di sini jika kamera tidak berfungsi..."></textarea>
                <button class="btn-submit" onclick="submitManual()">
                    <i class="fas fa-paper-plane"></i> Kirim
                </button>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div id="resultModal" class="result-modal">
        <div class="result-content">
            <div id="resultIcon" class="result-icon"></div>
            <h3 id="resultTitle" class="result-title"></h3>
            <p id="resultMessage" class="result-message"></p>
            <div id="resultDetails" class="result-details"></div>
            <button class="btn-close-result" onclick="closeResult()">
                Tutup
            </button>
        </div>
    </div>

    <script>
        let scanning = false;
        let videoStream = null;
        let scanTimeout = null;
        
        async function startScan() {
            try {
                scanning = true;
                
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                videoStream = await navigator.mediaDevices.getUserMedia(constraints);
                
                const video = document.getElementById('video');
                video.srcObject = videoStream;
                
                document.getElementById('video-container').style.display = 'block';
                document.getElementById('startScanBtn').style.display = 'none';
                document.getElementById('stopScanBtn').style.display = 'inline-flex';
                
                scanQRCode();
            } catch (error) {
                console.error('Error accessing camera:', error);
                showResult(false, 'Error Kamera', 'Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.');
                scanning = false;
            }
        }
        
        function stopScan() {
            scanning = false;
            
            if (scanTimeout) {
                cancelAnimationFrame(scanTimeout);
                scanTimeout = null;
            }
            
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            
            const video = document.getElementById('video');
            video.srcObject = null;
            
            document.getElementById('video-container').style.display = 'none';
            document.getElementById('startScanBtn').style.display = 'inline-flex';
            document.getElementById('stopScanBtn').style.display = 'none';
        }
        
        function scanQRCode() {
            if (!scanning) return;
            
            const video = document.getElementById('video');
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                
                if (code) {
                    console.log('QR Code detected:', code.data);
                    scanning = false;
                    stopScan();
                    submitAbsensi(code.data);
                    return;
                }
            }
            
            scanTimeout = requestAnimationFrame(scanQRCode);
        }
        
        function submitManual() {
            const data = document.getElementById('manualInput').value.trim();
            if (!data) {
                showResult(false, 'Input Kosong', 'Silakan masukkan data QR Code terlebih dahulu');
                return;
            }
            submitAbsensi(data);
        }
        
        function submitAbsensi(qrData) {
            console.log('Submitting absensi with QR:', qrData);
            
            const formData = new FormData();
            formData.append('action', 'submit_absensi');
            formData.append('qr_data', qrData);
            
            showLoading();
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server mengembalikan format yang salah.');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    const details = `
                        <div><strong>Nama</strong><span>${data.data.nama}</span></div>
                        <div><strong>NIS</strong><span>${data.data.nis}</span></div>
                        <div><strong>Kelas</strong><span>${data.data.kelas} - ${data.data.jurusan}</span></div>
                        <div><strong>Mata Pelajaran</strong><span>${data.data.mata_pelajaran}</span></div>
                        <div><strong>Waktu</strong><span>${data.data.waktu}</span></div>
                        <div><strong>Tanggal</strong><span>${data.data.tanggal}</span></div>
                        <div class="status-badge">
                            <i class="fas fa-check-circle"></i>
                            <span>STATUS: ${data.data.status}</span>
                        </div>
                    `;
                    showResult(true, 'Absensi Berhasil!', data.message, details);
                } else {
                    let errorDetails = '';
                    
                    if (data.error_type === 'expired') {
                        errorDetails = `<div class="error-badge">
                            <i class="fas fa-exclamation-triangle"></i> <strong>QR Code Kadaluarsa!</strong><br>
                            QR berubah setiap 30 detik. Silakan minta guru untuk refresh QR atau scan ulang.
                        </div>`;
                    } else if (data.error_type === 'invalid_token') {
                        errorDetails = `<div class="error-badge">
                            <i class="fas fa-ban"></i> <strong>Token Tidak Valid!</strong><br>
                            Kemungkinan QR ini screenshot atau foto. Gunakan QR yang sedang ditampilkan guru.
                        </div>`;
                    } else if (data.existing_status) {
                        errorDetails = `<div class="warning-badge">
                            <strong>Status sebelumnya:</strong> ${data.existing_status.toUpperCase()}
                        </div>`;
                    }
                    
                    showResult(false, 'Absensi Gagal', data.message, errorDetails);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showResult(false, 'Error Sistem', 'Terjadi kesalahan: ' + error.message);
            });
        }

        function showLoading() {
            const modal = document.getElementById('resultModal');
            const icon = document.getElementById('resultIcon');
            const titleEl = document.getElementById('resultTitle');
            const messageEl = document.getElementById('resultMessage');
            const detailsEl = document.getElementById('resultDetails');
            
            icon.className = 'result-icon loading';
            icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            titleEl.textContent = 'Memproses';
            messageEl.textContent = 'Mohon tunggu sebentar...';
            detailsEl.innerHTML = '';
            
            modal.classList.add('show');
        }
        
        function showResult(success, title, message, details = '') {
            const modal = document.getElementById('resultModal');
            const icon = document.getElementById('resultIcon');
            const titleEl = document.getElementById('resultTitle');
            const messageEl = document.getElementById('resultMessage');
            const detailsEl = document.getElementById('resultDetails');
            
            icon.className = 'result-icon ' + (success ? 'success' : 'error');
            icon.innerHTML = success ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';
            titleEl.textContent = title;
            messageEl.textContent = message;
            detailsEl.innerHTML = details;
            
            modal.classList.add('show');
        }
        
        function closeResult() {
            document.getElementById('resultModal').classList.remove('show');
            document.getElementById('manualInput').value = '';
        }
        
        window.addEventListener('unload', () => {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (scanning) {
                    stopScan();
                } else {
                    closeResult();
                }
            }
        });
    </script>
</body>
</html>