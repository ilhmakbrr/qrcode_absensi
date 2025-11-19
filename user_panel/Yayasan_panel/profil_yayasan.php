<?php
require_once '../auth_user/auth_check_yayasan.php';
include '../../db.php';

$ketua_id = $_SESSION['user_id'];

// ==================== PROCESSING SECTION ====================

// PROSES UPDATE PROFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    
    if (empty($nama) || empty($email)) {
        header("Location: profil_yayasan.php?error=empty_fields");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: profil_yayasan.php?error=invalid_email");
        exit();
    }
    
    $check_email = "SELECT id FROM ketua_yayasan WHERE email = '$email' AND id != $ketua_id";
    $result_check = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($result_check) > 0) {
        header("Location: profil_yayasan.php?error=email_exists");
        exit();
    }
    
    $query = "UPDATE ketua_yayasan SET 
              nama = '$nama',
              email = '$email',
              tanggal_lahir = " . ($tanggal_lahir ? "'$tanggal_lahir'" : "NULL") . ",
              alamat = '$alamat'
              WHERE id = $ketua_id";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['user_name'] = $nama;
        $_SESSION['user_email'] = $email;
        header("Location: profil_yayasan.php?success=profile_updated");
    } else {
        header("Location: profil_yayasan.php?error=update_failed");
    }
    exit();
}

// PROSES UPLOAD FOTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && isset($_POST['upload_photo'])) {
    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        $allowed = ['jpg', 'jpeg', 'png'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowed)) {
            header("Location: profil_yayasan.php?error=invalid_photo");
            exit();
        }
        
        if ($fileSize > 2097152) {
            header("Location: profil_yayasan.php?error=photo_too_large");
            exit();
        }
        
        $newFileName = 'profile_' . $ketua_id . '_' . time() . '.' . $fileExt;
        $uploadDir = '../../uploads/ketua_yayasan/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . $newFileName;
        
        $query_old = "SELECT foto FROM ketua_yayasan WHERE id = $ketua_id";
        $result_old = mysqli_query($conn, $query_old);
        $old_photo = mysqli_fetch_assoc($result_old)['foto'];
        
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            $query = "UPDATE ketua_yayasan SET foto = '$newFileName' WHERE id = $ketua_id";
            
            if (mysqli_query($conn, $query)) {
                if ($old_photo && file_exists($uploadDir . $old_photo)) {
                    unlink($uploadDir . $old_photo);
                }
                header("Location: profil_yayasan.php?success=photo_updated");
            } else {
                unlink($uploadPath);
                header("Location: profil_yayasan.php?error=update_failed");
            }
        } else {
            header("Location: profil_yayasan.php?error=upload_failed");
        }
    } else {
        header("Location: profil_yayasan.php?error=no_file");
    }
    exit();
}

// PROSES HAPUS FOTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    $query_old = "SELECT foto FROM ketua_yayasan WHERE id = $ketua_id";
    $result_old = mysqli_query($conn, $query_old);
    $old_photo = mysqli_fetch_assoc($result_old)['foto'];
    
    $query = "UPDATE ketua_yayasan SET foto = NULL WHERE id = $ketua_id";
    
    if (mysqli_query($conn, $query)) {
        if ($old_photo) {
            $uploadDir = '../../uploads/ketua_yayasan/';
            $filePath = $uploadDir . $old_photo;
            
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        header("Location: profil_yayasan.php?success=photo_deleted");
    } else {
        header("Location: profil_yayasan.php?error=update_failed");
    }
    exit();
}

// PROSES UBAH PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        header("Location: profil_yayasan.php?error=empty_password");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        header("Location: profil_yayasan.php?error=password_too_short");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        header("Location: profil_yayasan.php?error=password_mismatch");
        exit();
    }
    
    $query = "SELECT password FROM ketua_yayasan WHERE id = $ketua_id";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
    
    if (!password_verify($old_password, $user['password'])) {
        header("Location: profil_yayasan.php?error=wrong_password");
        exit();
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_query = "UPDATE ketua_yayasan SET password = '$hashed_password' WHERE id = $ketua_id";
    
    if (mysqli_query($conn, $update_query)) {
        header("Location: profil_yayasan.php?success=password_updated");
    } else {
        header("Location: profil_yayasan.php?error=update_failed");
    }
    exit();
}

// ==================== GET DATA PROFIL ====================

$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);

// Format tanggal lahir untuk input
$tanggal_lahir_formatted = $profile['tanggal_lahir'] ? date('Y-m-d', strtotime($profile['tanggal_lahir'])) : '';

// ==================== PESAN NOTIFIKASI ====================

$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    $message_type = 'success';
    switch ($_GET['success']) {
        case 'profile_updated':
            $message = 'Profil berhasil diperbarui!';
            break;
        case 'photo_updated':
            $message = 'Foto profil berhasil diperbarui!';
            break;
        case 'photo_deleted':
            $message = 'Foto profil berhasil dihapus!';
            break;
        case 'password_updated':
            $message = 'Password berhasil diubah!';
            break;
    }
}

if (isset($_GET['error'])) {
    $message_type = 'error';
    switch ($_GET['error']) {
        case 'invalid_photo':
            $message = 'Format foto tidak valid! Gunakan JPG, JPEG, atau PNG.';
            break;
        case 'photo_too_large':
            $message = 'Ukuran foto terlalu besar! Maksimal 2MB.';
            break;
        case 'upload_failed':
            $message = 'Gagal mengupload foto!';
            break;
        case 'no_file':
            $message = 'Tidak ada file yang dipilih!';
            break;
        case 'wrong_password':
            $message = 'Password lama salah!';
            break;
        case 'password_mismatch':
            $message = 'Password baru tidak cocok!';
            break;
        case 'password_too_short':
            $message = 'Password minimal 8 karakter!';
            break;
        case 'empty_password':
            $message = 'Semua field password harus diisi!';
            break;
        case 'update_failed':
            $message = 'Gagal memperbarui data!';
            break;
        case 'empty_fields':
            $message = 'Nama dan email harus diisi!';
            break;
        case 'invalid_email':
            $message = 'Format email tidak valid!';
            break;
        case 'email_exists':
            $message = 'Email sudah digunakan oleh user lain!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Ketua Yayasan - EduAttend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .main-content {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header - More Compact */
        .profile-header {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 1.5rem;
        }

        .profile-header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 0.25rem;
        }

        .profile-header-subtitle {
            font-size: 0.875rem;
            color: #86868b;
        }

        /* Alert - Compact */
        .alert-custom {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideDown 0.3s ease;
            font-size: 0.875rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-custom.success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-custom.error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Profile Card - Compact */
        .profile-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.5rem;
            border: 1px solid #e5e5e7;
            margin-bottom: 1rem;
            height: fit-content;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #667eea;
            font-size: 0.9rem;
        }

        /* Photo - Smaller */
        .current-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e5e7;
            background: #f5f5f7;
            margin: 0 auto;
            display: block;
        }

        /* Form - Compact */
        .form-group-compact {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 0.4rem;
            font-size: 0.8125rem;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e5e5e7;
            padding: 0.6rem 0.85rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-control:disabled {
            background: #f5f5f7;
            color: #86868b;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons - Compact */
        .btn-custom {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            justify-content: center;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary-custom {
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .btn-secondary-custom:hover {
            background: #e5e5e7;
        }

        .btn-danger-custom {
            background: #ef4444;
            color: white;
        }

        .btn-danger-custom:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* File Upload - Compact */
        .file-upload-wrapper {
            position: relative;
            display: block;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .file-upload-label {
            display: block;
            padding: 0.6rem 1rem;
            background: #f5f5f7;
            border: 2px dashed #d2d2d7;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.875rem;
        }

        .file-upload-label:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        /* Info Display - Compact */
        .info-display {
            background: #f5f5f7;
            padding: 0.75rem;
            border-radius: 8px;
        }

        .info-item-compact {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e5e7;
        }

        .info-item-compact:last-child {
            border-bottom: none;
        }

        .info-label-compact {
            font-weight: 500;
            color: #86868b;
            font-size: 0.8125rem;
        }

        .info-value-compact {
            color: #1d1d1f;
            font-size: 0.8125rem;
            text-align: right;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 3px;
            background: #e5e5e7;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }

        .strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        /* Modal */
        .modal-content {
            border-radius: 14px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #e5e5e7;
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            color: #1d1d1f;
            font-size: 1rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e5e5e7;
            padding: 1.25rem 1.5rem;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                padding: 1rem;
            }

            .current-photo {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 576px) {
            .profile-header {
                padding: 1rem;
            }

            .profile-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_yayasan.php'; ?>

<div class="main-content">
    <!-- Header -->
    <div class="profile-header">
        <h1 class="profile-header-title">Profil Saya</h1>
        <p class="profile-header-subtitle">Kelola informasi profil dan keamanan akun Anda</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-custom <?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- 2 Column Layout -->
    <div class="row g-3">
        <!-- Left Column -->
        <div class="col-lg-4">
            <!-- Photo Upload Section -->
            <div class="profile-card">
                <h2 class="section-title">
                    <i class="fas fa-camera"></i>
                    Foto Profil
                </h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-3">
                        <img src="<?php echo $profile['foto'] ? '../../uploads/ketua_yayasan/' . $profile['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($profile['nama']) . '&size=150&background=667eea&color=fff'; ?>" 
                             alt="Foto Profil" 
                             class="current-photo"
                             id="photoPreview">
                    </div>
                    
                    <div class="photo-info mb-3" style="text-align: center;">
                        <p style="font-size: 0.75rem; color: #86868b; margin: 0;"><strong>Persyaratan:</strong></p>
                        <p style="font-size: 0.75rem; color: #86868b; margin: 0;">JPG, JPEG, PNG • Max 2 MB</p>
                    </div>
                    
                    <div class="file-upload-wrapper mb-2">
                        <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/jpg,image/png" onchange="previewPhoto(this)">
                        <label class="file-upload-label w-100">
                            <i class="fas fa-upload"></i> Pilih Foto
                        </label>
                    </div>
                    <p class="text-center mb-3" id="fileName" style="font-size: 0.75rem; color: #86868b;">Belum ada file</p>
                    
                    <button type="submit" name="upload_photo" class="btn-custom btn-primary-custom w-100 mb-2">
                        <i class="fas fa-save"></i> Simpan Foto
                    </button>
                    <?php if ($profile['foto']): ?>
                        <button type="button" class="btn-custom btn-danger-custom w-100" onclick="deletePhotoConfirm()">
                            <i class="fas fa-trash"></i> Hapus Foto
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-8">
            <!-- Profile Information -->
            <div class="profile-card">
                <h2 class="section-title">
                    <i class="fas fa-user"></i>
                    Informasi Profil
                </h2>
                
                <form action="" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-compact">
                                <label class="form-label">Nama Lengkap <span style="color: red;">*</span></label>
                                <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($profile['nama']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-compact">
                                <label class="form-label">Email <span style="color: red;">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-compact">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" class="form-control" name="tanggal_lahir" value="<?php echo $tanggal_lahir_formatted; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-compact">
                                <label class="form-label">ID User</label>
                                <input type="text" class="form-control" value="<?php echo $profile['id']; ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-compact">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="3"><?php echo htmlspecialchars($profile['alamat']); ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn-custom btn-primary-custom">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Account Information & Security in One Row -->
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="profile-card">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Info Akun
                        </h2>
                        
                        <div class="info-display">
                            <div class="info-item-compact">
                                <div class="info-label-compact">Terdaftar</div>
                                <div class="info-value-compact"><?php echo date('d M Y', strtotime($profile['created_at'])); ?></div>
                            </div>
                            <div class="info-item-compact">
                                <div class="info-label-compact">Diperbarui</div>
                                <div class="info-value-compact"><?php echo date('d M Y', strtotime($profile['updated_at'])); ?></div>
                            </div>
                            <div class="info-item-compact">
                                <div class="info-label-compact">Role</div>
                                <div class="info-value-compact">
                                    <span style="background: #667eea; color: white; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem;">
                                        Ketua Yayasan
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="profile-card">
                        <h2 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Keamanan
                        </h2>
                        
                        <div class="info-display mb-3">
                            <div class="info-item-compact">
                                <div class="info-label-compact">Password</div>
                                <div class="info-value-compact">••••••••</div>
                            </div>
                            <div class="info-item-compact">
                                <div class="info-label-compact">Terakhir Diubah</div>
                                <div class="info-value-compact"><?php echo date('d M Y', strtotime($profile['updated_at'])); ?></div>
                            </div>
                        </div>

                        <button type="button" class="btn-custom btn-primary-custom w-100" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST" id="changePasswordForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key"></i> Ubah Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group-compact">
                        <label class="form-label">Password Lama <span style="color: red;">*</span></label>
                        <input type="password" class="form-control" name="old_password" id="oldPassword" required>
                    </div>

                    <div class="form-group-compact">
                        <label class="form-label">Password Baru <span style="color: red;">*</span></label>
                        <input type="password" class="form-control" name="new_password" id="newPassword" required onkeyup="checkPasswordStrength(this.value)">
                        <div class="password-strength" id="passwordStrength" style="display: none;">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>
                    </div>

                    <div class="form-group-compact">
                        <label class="form-label">Konfirmasi Password Baru <span style="color: red;">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                    </div>

                    <div style="background: #fef3c7; color: #92400e; padding: 0.75rem; border-radius: 8px; margin-top: 0.75rem; font-size: 0.8125rem;">
                        <i class="fas fa-info-circle"></i>
                        <div style="margin-top: 0.5rem;">
                            <strong>Password harus:</strong>
                            <ul style="margin: 0.25rem 0 0 1rem; padding: 0;">
                                <li>Min 8 karakter</li>
                                <li>Huruf besar & kecil</li>
                                <li>Mengandung angka</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-custom btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="change_password" class="btn-custom btn-primary-custom">
                        <i class="fas fa-check"></i> Ubah Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Photo Confirmation Modal -->
<div class="modal fade" id="deletePhotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Konfirmasi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size: 0.875rem;">
                <p>Yakin hapus foto profil?</p>
                <p style="color: #86868b; font-size: 0.8125rem;">Akan diganti avatar default.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-custom btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                <form action="" method="POST" style="display: inline;">
                    <button type="submit" name="delete_photo" class="btn-custom btn-danger-custom">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preview photo
function previewPhoto(input) {
    const fileName = input.files[0]?.name || 'Belum ada file';
    document.getElementById('fileName').textContent = fileName;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Delete photo confirmation
function deletePhotoConfirm() {
    const modal = new bootstrap.Modal(document.getElementById('deletePhotoModal'));
    modal.show();
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    
    if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    strengthDiv.style.display = 'block';
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    strengthFill.className = 'strength-fill';
    if (strength <= 2) {
        strengthFill.classList.add('strength-weak');
        strengthText.textContent = 'Lemah';
        strengthText.style.color = '#ef4444';
    } else if (strength <= 4) {
        strengthFill.classList.add('strength-medium');
        strengthText.textContent = 'Sedang';
        strengthText.style.color = '#f59e0b';
    } else {
        strengthFill.classList.add('strength-strong');
        strengthText.textContent = 'Kuat';
        strengthText.style.color = '#10b981';
    }
}

// Validate form
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Password tidak cocok!');
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password minimal 8 karakter!');
        return false;
    }
});

// Auto hide alert
<?php if ($message): ?>
setTimeout(function() {
    const alert = document.querySelector('.alert-custom');
    if (alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);
<?php endif; ?>
</script>

</body>
</html>