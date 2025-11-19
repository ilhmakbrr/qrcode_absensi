<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Inisialisasi array untuk multiple users jika belum ada
if (!isset($_SESSION['logged_users'])) {
    $_SESSION['logged_users'] = [
        'siswa' => [],
        'guru' => []
    ];
}

$current_role = isset($_GET['role']) ? $_GET['role'] : (isset($_POST['role']) ? $_POST['role'] : 'siswa');

// Handle logout request
if (isset($_GET['logout'])) {
    $logout_role = $_GET['role'] ?? 'siswa';
    $logout_id = $_GET['id'] ?? null;
    
    if ($logout_id && $logout_role) {
        // Hapus user tertentu dari array
        if (isset($_SESSION['logged_users'][$logout_role][$logout_id])) {
            unset($_SESSION['logged_users'][$logout_role][$logout_id]);
        }
    }
    
    // Jika tidak ada user yang login, destroy session
    if (empty($_SESSION['logged_users']['siswa']) && empty($_SESSION['logged_users']['guru'])) {
        session_destroy();
    }
    
    header("Location: login_user.php?role=" . $logout_role);
    exit();
}

include '../../db.php';



$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    if ($role === 'siswa') {
        // Query untuk mencari siswa berdasarkan NIS
        $query = "SELECT * FROM siswa WHERE nis = '$username'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Cek apakah siswa ini sudah login
            if (isset($_SESSION['logged_users']['siswa'][$user['id']])) {
                $error = "Siswa dengan NIS ini sudah login! Silakan buka dashboard atau logout terlebih dahulu.";
            } else {
                // Verifikasi password
                if (password_verify($password, $user['password'])) {
                   
                    
                    // Simpan data siswa ke array dengan ID sebagai key
                    $_SESSION['logged_users']['siswa'][$user['id']] = [
                        'user_id' => $user['id'],
                        'nis' => $user['nis'],
                        'nama' => $user['nama'],
                        'email' => $user['email'],
                        'kelas' => $user['kelas'],
                        'jurusan' => $user['jurusan'],
                        'role' => 'siswa',
                        'login_time' => time()
                    ];
                    
                    $success = "Login berhasil sebagai " . htmlspecialchars($user['nama']) . "! Redirecting...";
                    
                    // Redirect dengan parameter user_id
                    header("refresh:1;url=../siswa_panel/dashboard_siswa.php?user_id=" . $user['id']);
                } else {
                    $error = "Password salah!";
                }
            }
        } else {
            $error = "NIS tidak ditemukan!";
        }
    } elseif ($role === 'guru') {
        // Query untuk mencari guru berdasarkan NIP
        $query = "SELECT * FROM guru WHERE nip = '$username'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Cek apakah guru ini sudah login
            if (isset($_SESSION['logged_users']['guru'][$user['id']])) {
                $error = "Guru dengan NIP ini sudah login! Silakan buka dashboard atau logout terlebih dahulu.";
            } else {
                // Verifikasi password
                if (password_verify($password, $user['password'])) {
                    
                    
                    // Simpan data guru ke array dengan ID sebagai key
                    $_SESSION['logged_users']['guru'][$user['id']] = [
                        'user_id' => $user['id'],
                        'nip' => $user['nip'],
                        'nama' => $user['nama'],
                        'email' => $user['email'],
                        'mata_pelajaran' => $user['mata_pelajaran'],
                        'role' => 'guru',
                        'login_time' => time()
                    ];
                    
                    $success = "Login berhasil sebagai " . htmlspecialchars($user['nama']) . "! Redirecting...";
                    
                    // Redirect dengan parameter user_id
                    header("refresh:1;url=../guru_panel/dashboard_guru.php?user_id=" . $user['id']);
                } else {
                    $error = "Password salah!";
                }
            }
        } else {
            $error = "NIP tidak ditemukan!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-wrapper {
            display: flex;
            background: white;
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            min-height: 600px;
        }
        
        /* Left Side - Image Section */
        .image-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.95) 0%, rgba(42, 82, 152, 0.95) 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .image-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.03) 10px,
                rgba(255, 255, 255, 0.03) 20px
            );
            animation: slidePattern 20s linear infinite;
        }
        
        @keyframes slidePattern {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .school-logo-container {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .school-logo {
            width: 200px;
            height: 200px;
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin: 0 auto 2rem auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .school-logo-placeholder {
            font-size: 5rem;
            color: #1e3c72;
        }
        
        .school-info {
            text-align: center;
        }
        
        .school-info h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .school-info p {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 300;
            line-height: 1.6;
        }
        
        .school-info .tagline {
            margin-top: 1rem;
            font-size: 0.9rem;
            font-style: italic;
            opacity: 0.8;
        }
        
        /* Right Side - Login Form */
        .form-section {
            flex: 1;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            margin-bottom: 2.5rem;
        }
        
        .form-header h3 {
            color: #1e3c72;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .role-option {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .role-option:hover {
            border-color: #2a5298;
            background: #f8fafc;
        }
        
        .role-option.active {
            border-color: #2a5298;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .role-option.active .role-icon {
            color: white;
        }
        
        .role-label {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            color: #334155;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 4px rgba(42, 82, 152, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30, 60, 114, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .footer-text i {
            color: #2a5298;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }
            
            .image-section {
                padding: 2rem;
                min-height: 250px;
            }
            
            .school-logo {
                width: 140px;
                height: 140px;
                padding: 15px;
                margin-bottom: 1rem;
            }
            
            .school-info h2 {
                font-size: 1.4rem;
            }
            
            .school-info p {
                font-size: 0.9rem;
            }
            
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Image/Logo Section -->
        <div class="image-section">
            <div class="school-logo-container">
                <div class="school-logo">
                    <img src="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp" alt="Logo SMA PGRI 2 Padang">
                </div>
                <div class="school-info">
                    <h2>SMA PGRI 2 PADANG</h2>
                    <p>Sistem Informasi Absensi Digital</p>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="form-section">
            <div class="form-header">
                <h3>Selamat Datang</h3>
                <p>Silakan login untuk mengakses sistem absensi</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <!-- Role Selection -->
                <div class="role-selector">
                    <label class="role-option <?php echo $current_role === 'siswa' ? 'active' : ''; ?>" data-role="siswa">
                        <input type="radio" name="role" value="siswa" <?php echo $current_role === 'siswa' ? 'checked' : ''; ?>>
                        <i class="fas fa-user-graduate role-icon"></i>
                        <span class="role-label">Siswa</span>
                    </label>
                    <label class="role-option <?php echo $current_role === 'guru' ? 'active' : ''; ?>" data-role="guru">
                        <input type="radio" name="role" value="guru" <?php echo $current_role === 'guru' ? 'checked' : ''; ?>>
                        <i class="fas fa-chalkboard-teacher role-icon"></i>
                        <span class="role-label">Guru</span>
                    </label>
                </div>
                
                <!-- Username Input -->
                <div class="form-group">
                    <label class="form-label" id="usernameLabel">
                        <?php echo $current_role === 'guru' ? 'NIP (Nomor Induk Pegawai)' : 'NIS (Nomor Induk Siswa)'; ?>
                    </label>
                    <div class="input-group">
                        <i class="fas fa-<?php echo $current_role === 'guru' ? 'id-badge' : 'id-card'; ?> input-icon" id="usernameIcon"></i>
                        <input type="text" 
                               class="form-control" 
                               name="username" 
                               id="usernameInput"
                               placeholder="Masukkan <?php echo $current_role === 'guru' ? 'NIP' : 'NIS'; ?> Anda" 
                               required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <!-- Password Input -->
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               placeholder="Masukkan password Anda" 
                               required>
                    </div>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>
            
            <div class="footer-text">
                <i class="fas fa-info-circle"></i> Lupa password? Hubungi administrator sekolah
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('.role-option');
            const usernameLabel = document.getElementById('usernameLabel');
            const usernameInput = document.getElementById('usernameInput');
            const usernameIcon = document.getElementById('usernameIcon');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    roleOptions.forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    const selectedRole = this.getAttribute('data-role');
                    
                    // Update form labels and placeholders
                    if (selectedRole === 'siswa') {
                        usernameLabel.textContent = 'NIS (Nomor Induk Siswa)';
                        usernameInput.placeholder = 'Masukkan NIS Anda';
                        usernameIcon.className = 'fas fa-id-card input-icon';
                    } else {
                        usernameLabel.textContent = 'NIP (Nomor Induk Pegawai)';
                        usernameInput.placeholder = 'Masukkan NIP Anda';
                        usernameIcon.className = 'fas fa-id-badge input-icon';
                    }
                    
                    // Clear username input
                    usernameInput.value = '';
                    usernameInput.focus();
                });
            });
        });
    </script>
</body>
</html>