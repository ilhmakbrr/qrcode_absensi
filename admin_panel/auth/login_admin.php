<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Admin</title>
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

    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 0;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow: hidden;
      max-width: 1000px;
      width: 100%;
    }

    .login-left {
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

    .login-left::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }

    .login-left h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .login-left p {
      font-size: 1.2rem;
      opacity: 0.9;
      margin-bottom: 2rem;
    }

    .feature-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .feature-list li {
      padding: 0.5rem 0;
      display: flex;
      align-items: center;
      opacity: 0.9;
    }

    .feature-list li::before {
      content: "✓";
      color: #10b981;
      font-weight: bold;
      margin-right: 0.5rem;
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      width: 25px;
      height: 25px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-right {
      padding: 4rem 3rem;
      background: white;
    }

    .login-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .login-header h2 {
      color: var(--text-color);
      font-size: 2.5rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .login-header p {
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

    .login-btn {
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

    .login-btn:hover {
      background: var(--primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .register-link {
      text-align: center;
      margin-top: 2rem;
    }

    .register-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .register-link a:hover {
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

    .admin-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.8;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .login-left {
        display: none;
      }
      
      .login-right {
        padding: 2rem 1.5rem;
      }
      
      .login-header h2 {
        font-size: 2rem;
      }
      
      .login-container {
        padding: 1rem;
      }
    }

    @media (max-width: 576px) {
      .login-right {
        padding: 1.5rem 1rem;
      }
      
      .login-header h2 {
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="row g-0 h-100">
        <!-- Left Panel -->
        <div class="col-lg-6 login-left">
          <div class="admin-icon">🛡️</div>
          <h1>Admin Portal</h1>
          <p>Sistem Manajemen Terpusat</p>
          <ul class="feature-list">
            <li>Dashboard Analytics</li>
            <li>User Management</li>
            <li>Content Control</li>
            <li>Security Monitoring</li>
            <li>System Configuration</li>
          </ul>
        </div>
        
        <!-- Right Panel -->
        <div class="col-lg-6 login-right">
          <div class="login-header">
            <h2>Selamat Datang</h2>
            <p>Silakan masuk ke akun administrator Anda</p>
          </div>
          
          <form action="check_login.php" method="POST">
            <div class="form-floating">
              <input type="text" name="username" class="form-control" id="floatingUsername" placeholder="Username" required>
              <label for="floatingUsername">Username</label>
            </div>
            
            <div class="form-floating">
              <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
              <label for="floatingPassword">Password</label>
            </div>
            
            <button type="submit" class="login-btn">
              Masuk Sekarang
            </button>
          </form>
          
          <div class="register-link">
            <p>Belum punya akun? <a href="register_admin.php">Daftar Admin Baru</a></p>
          </div>
          
          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center">
              <strong>Error!</strong> Username atau password salah!
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.form-control');
      
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
          this.parentElement.style.transform = 'translateY(0)';
        });
      });
    });
  </script>
</body>
</html>