<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #4f46e5;
      --primary-hover: #4338ca;
      --secondary-color: #e5e7eb;
      --text-color: #374151;
      --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    body {
      background: var(--bg-gradient);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .register-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 0;
    }

    .register-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow: hidden;
      max-width: 1000px;
      width: 100%;
    }

    .register-left {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 4rem 2rem;
      position: relative;
      overflow: hidden;
    }

    .register-left h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .register-left p {
      font-size: 1.2rem;
      opacity: 0.9;
      margin-bottom: 2rem;
    }

    .register-right {
      padding: 4rem 3rem;
      background: white;
    }

    .register-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .register-header h2 {
      color: var(--text-color);
      font-size: 2.5rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .register-header p {
      color: #6b7280;
      font-size: 1.1rem;
    }

    .form-floating {
      margin-bottom: 1.5rem;
    }

    .form-floating input {
      border: 2px solid var(--secondary-color);
      border-radius: 10px;
      padding: 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-floating input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
    }

    .form-floating label {
      color: #6b7280;
      font-weight: 500;
    }

    .register-btn {
      background: var(--primary-color);
      border: none;
      border-radius: 10px;
      padding: 1rem 2rem;
      font-size: 1.1rem;
      font-weight: 600;
      color: white;
      width: 100%;
      transition: all 0.3s ease;
      margin-bottom: 1.5rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .register-btn:hover {
      background: var(--primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }

    .register-btn:active {
      transform: translateY(0);
    }

    .login-link {
      text-align: center;
      margin-top: 2rem;
    }

    .login-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: var(--primary-hover);
      text-decoration: underline;
    }

    .alert {
      border-radius: 10px;
      border: none;
      padding: 1rem;
      margin-top: 1rem;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .register-left {
        display: none;
      }
      
      .register-right {
        padding: 2rem 1.5rem;
      }
      
      .register-header h2 {
        font-size: 2rem;
      }
      
      .register-container {
        padding: 1rem;
      }
    }

    @media (max-width: 576px) {
      .register-right {
        padding: 1.5rem 1rem;
      }
      
      .register-header h2 {
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-card">
      <div class="row g-0 h-100">
        <!-- Left Panel -->
        <div class="col-lg-6 register-left">
          <h1>Admin Portal</h1>
          <p>Daftarkan akun administrator baru untuk mengelola sistem</p>
        </div>
        
        <!-- Right Panel -->
        <div class="col-lg-6 register-right">
          <div class="register-header">
            <h2>Daftar Admin</h2>
            <p>Silakan isi data berikut untuk membuat akun</p>
          </div>
          
          <form action="proses_register.php" method="POST">
            <div class="form-floating">
              <input type="text" name="nama_lengkap" class="form-control" id="floatingNamaLengkap" placeholder="Nama Lengkap" required>
              <label for="floatingNamaLengkap">Nama Lengkap</label>
            </div>
            
            <div class="form-floating">
              <input type="text" name="username" class="form-control" id="floatingUsername" placeholder="Username" required>
              <label for="floatingUsername">Username</label>
            </div>
            
            <div class="form-floating">
              <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
              <label for="floatingPassword">Password</label>
            </div>
            
            <button type="submit" class="register-btn">
              Daftar Sekarang
            </button>
          </form>
          
          <div class="login-link">
            <p>Sudah punya akun? <a href="login_admin.php">Masuk di sini</a></p>
          </div>
          
          <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success text-center">
              Admin berhasil didaftarkan!
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>