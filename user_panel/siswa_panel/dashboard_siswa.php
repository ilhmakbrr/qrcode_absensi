<?php
include '../auth_user/auth_check_siswa.php';

$today = date('Y-m-d');

// Statistik kehadiran siswa
$stats = [
    'total_hadir' => 0,
    'total_tidak_hadir' => 0,
    'persentase_kehadiran' => 0,
    'total_terlambat' => 0
];

// Query kehadiran siswa ini
$query = "SELECT 
            COUNT(CASE WHEN status = 'hadir' THEN 1 END) as total_hadir,
            COUNT(CASE WHEN status != 'hadir' THEN 1 END) as total_tidak_hadir,
            COUNT(CASE WHEN status = 'terlambat' THEN 1 END) as total_terlambat,
            COUNT(*) as total_absensi
          FROM absensi 
          WHERE nis = ? 
          AND MONTH(tanggal) = MONTH(CURRENT_DATE())
          AND YEAR(tanggal) = YEAR(CURRENT_DATE())";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $nis);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        $stats['total_hadir'] = $data['total_hadir'];
        $stats['total_tidak_hadir'] = $data['total_tidak_hadir'];
        $stats['total_terlambat'] = $data['total_terlambat'];
        
        if ($data['total_absensi'] > 0) {
            $stats['persentase_kehadiran'] = round(($data['total_hadir'] / $data['total_absensi']) * 100, 1);
        }
    }
    mysqli_stmt_close($stmt);
}

// Jadwal hari ini
$hari_ini = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
             'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 
             'Saturday' => 'Sabtu'][date('l')];

$jadwal_hari_ini = [];
$query = "SELECT * FROM jadwal_guru 
          WHERE kelas = ? 
          AND hari = ? 
          AND status = 'aktif'
          ORDER BY jam_mulai ASC";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $kelas, $hari_ini);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $jadwal_hari_ini[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Riwayat absensi terbaru
$riwayat_absensi = [];
$query = "SELECT * FROM absensi 
          WHERE nis = ? 
          ORDER BY tanggal DESC 
          LIMIT 5";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $nis);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $riwayat_absensi[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - <?php echo htmlspecialchars($nama); ?></title>
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
            background: #f8f9fc;
            color: #2c3e50;
        }
        
        .main-content {
            margin: 20px;
            flex: 1;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
        }

        /* Hero Banner Slider */
        .hero-banner {
            position: relative;
            height: 280px;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .banner-slides {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .banner-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .banner-slide.active {
            opacity: 1;
        }

        .banner-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .banner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.85) 0%, rgba(52, 73, 94, 0.75) 100%);
            display: flex;
            align-items: center;
            padding: 0 50px;
        }

        .banner-content h1 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .banner-content p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 20px;
            font-weight: 400;
        }

        .banner-info {
            display: inline-flex;
            align-items: center;
            gap: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .banner-info span {
            color: white;
            font-size: 14px;
            font-weight: 500;
        }

        .banner-info i {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        /* Banner Navigation */
        .banner-nav {
            position: absolute;
            bottom: 20px;
            right: 30px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }

        .banner-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .banner-dot.active {
            background: white;
            width: 24px;
            border-radius: 4px;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #3498db, #2980b9);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:nth-child(1)::before { background: linear-gradient(180deg, #3498db, #2980b9); }
        .stat-card:nth-child(2)::before { background: linear-gradient(180deg, #2ecc71, #27ae60); }
        .stat-card:nth-child(3)::before { background: linear-gradient(180deg, #e67e22, #d35400); }
        .stat-card:nth-child(4)::before { background: linear-gradient(180deg, #e74c3c, #c0392b); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #e67e22, #d35400); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
            overflow: hidden;
            margin-bottom: 20px;
            height: 100%;
        }

        .card-header {
            background: #f8f9fc;
            border-bottom: 1px solid #e8ecf1;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .card-header i {
            font-size: 18px;
            color: #3498db;
        }

        .card-body {
            padding: 24px;
            max-height: 450px;
            overflow-y: auto;
        }

        /* Custom Scrollbar */
        .card-body::-webkit-scrollbar {
            width: 6px;
        }

        .card-body::-webkit-scrollbar-track {
            background: #f1f3f5;
            border-radius: 10px;
        }

        .card-body::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 10px;
        }

        .card-body::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* List Items */
        .list-item {
            background: #f8f9fc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid #e8ecf1;
            transition: all 0.3s ease;
        }

        .list-item:hover {
            background: #f1f3f6;
            border-color: #d1d8e0;
            transform: translateX(4px);
        }

        .list-item:last-child {
            margin-bottom: 0;
        }

        .list-item strong {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            display: block;
            margin-bottom: 6px;
        }

        .list-item small {
            font-size: 13px;
            color: #7f8c8d;
        }

        .list-item i {
            font-size: 12px;
            margin-right: 6px;
            color: #95a5a6;
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 56px;
            color: #e8ecf1;
            margin-bottom: 16px;
        }

        .empty-state p {
            color: #95a5a6;
            font-size: 15px;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-banner {
                height: 220px;
            }

            .banner-overlay {
                padding: 0 30px;
            }

            .banner-content h1 {
                font-size: 24px;
            }

            .banner-content p {
                font-size: 14px;
            }

            .banner-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-number {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.1s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .content-card {
            animation: fadeInUp 0.5s ease-out 0.5s backwards;
        }
    </style>
</head>
<body>
    <?php include 'sidebar_siswa.php'; ?>
    
    <div class="main-content">
        <!-- Hero Banner Slider -->
        <div class="hero-banner">
            <div class="banner-slides">
                <!-- Slide 1 -->
                <div class="banner-slide active">
                    <img src="../../gambar/10303441-5.jpg" alt="Education">
                    <div class="banner-overlay">
                        <div class="banner-content">
                            <h1>Selamat Datang, <?php echo htmlspecialchars($nama); ?></h1>
                            <p>Pantau perkembangan kehadiran dan prestasi akademik Anda</p>
                        </div>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="banner-slide">
                    <img src="../../gambar/WhatsApp-Image-2023-12-14-at-12.59.08.jpeg" alt="Students">
                    <div class="banner-overlay">
                        <div class="banner-content">
                            <h1>Tingkatkan Prestasi Akademik Anda</h1>
                            <p>Kehadiran yang konsisten adalah kunci kesuksesan dalam pembelajaran</p>
                            <div class="banner-info">
                                <span><i class="fas fa-chart-line"></i> Kehadiran: <?php echo $stats['persentase_kehadiran']; ?>%</span>
                                <span><i class="fas fa-check-circle"></i> Hadir: <?php echo $stats['total_hadir']; ?> hari</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="banner-slide">
                    <img src="../../gambar/WhatsApp-Image-2023-06-14-at-13.51.17.webp" alt="Library">
                    <div class="banner-overlay">
                        <div class="banner-content">
                            <h1>Raih Masa Depan Cemerlang</h1>
                            <p>Disiplin dan dedikasi adalah pondasi kesuksesan</p>
                            <div class="banner-info">
                                <span><i class="fas fa-calendar-day"></i> <?php echo $hari_ini; ?></span>
                                <span><i class="fas fa-book-open"></i> Terus Belajar & Berkembang</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banner Navigation -->
            <div class="banner-nav">
                <span class="banner-dot active" data-slide="0"></span>
                <span class="banner-dot" data-slide="1"></span>
                <span class="banner-dot" data-slide="2"></span>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" data-target="<?php echo $stats['total_hadir']; ?>">0</div>
                            <div class="stat-label">Total Hadir</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" data-target="<?php echo $stats['persentase_kehadiran']; ?>">0</div>
                            <div class="stat-label">Persentase Kehadiran</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" data-target="<?php echo $stats['total_terlambat']; ?>">0</div>
                            <div class="stat-label">Total Terlambat</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" data-target="<?php echo $stats['total_tidak_hadir']; ?>">0</div>
                            <div class="stat-label">Tidak Hadir</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-calendar-day"></i>
                        <h5>Jadwal Hari Ini (<?php echo $hari_ini; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($jadwal_hari_ini)): ?>
                            <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                <div class="list-item">
                                    <strong><?php echo htmlspecialchars($jadwal['mata_pelajaran']); ?></strong>
                                    <small>
                                        <i class="far fa-clock"></i>
                                        <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - 
                                        <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>Tidak ada jadwal untuk hari ini</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h5>Riwayat Absensi Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($riwayat_absensi)): ?>
                            <?php foreach ($riwayat_absensi as $absensi): ?>
                                <div class="list-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>
                                                <i class="far fa-calendar"></i>
                                                <?php echo date('d M Y', strtotime($absensi['tanggal'])); ?>
                                            </strong>
                                        </div>
                                        <span class="status-badge badge-<?php 
                                            echo $absensi['status'] == 'hadir' ? 'success' : 
                                                 ($absensi['status'] == 'terlambat' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($absensi['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <p>Belum ada riwayat absensi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto Banner Slider
        let currentSlide = 0;
        const slides = document.querySelectorAll('.banner-slide');
        const dots = document.querySelectorAll('.banner-dot');
        const totalSlides = slides.length;

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            slides[index].classList.add('active');
            dots[index].classList.add('active');
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        // Auto slide setiap 5 detik
        const slideInterval = setInterval(nextSlide, 5000);

        // Manual navigation
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                showSlide(currentSlide);
                
                // Reset auto slide timer
                clearInterval(slideInterval);
                setInterval(nextSlide, 5000);
            });
        });

        // Animated Counter
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-number');
            
            const animateCounter = (counter) => {
                const target = parseFloat(counter.getAttribute('data-target'));
                const duration = 1500;
                const increment = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target % 1 === 0 ? target : target.toFixed(1);
                        // Tambahkan % untuk persentase
                        const label = counter.nextElementSibling.textContent;
                        if (label.includes('Persentase')) {
                            counter.textContent += '%';
                        }
                    }
                };
                
                updateCounter();
            };

            // Animate on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target.querySelector('.stat-number');
                        animateCounter(counter);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            counters.forEach(counter => {
                observer.observe(counter.closest('.stat-card'));
            });
        });
    </script>
</body>
</html>