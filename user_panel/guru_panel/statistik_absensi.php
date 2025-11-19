<?php
session_name('SCHOOL_ATTENDANCE_SYSTEM');
session_start();

// Ambil user_id dari parameter URL
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Jika tidak ada user_id di URL, cek apakah hanya ada 1 guru yang login
if (!$current_user_id) {
    if (isset($_SESSION['logged_users']['guru']) && count($_SESSION['logged_users']['guru']) == 1) {
        $current_user_id = array_key_first($_SESSION['logged_users']['guru']);
    }
}

// Validasi apakah user_id valid dan ada di session
if (!$current_user_id || !isset($_SESSION['logged_users']['guru'][$current_user_id])) {
    header("Location: ../auth/login_user.php?role=guru");
    exit();
}

// Ambil data user dari session
$user_data = $_SESSION['logged_users']['guru'][$current_user_id];
$user_id = $user_data['user_id'];
$nip = $user_data['nip'];
$nama = $user_data['nama'];
$email = $user_data['email'];
$mata_pelajaran = $user_data['mata_pelajaran'];

include '../../db.php';
include '../auth_user/auth_check_guru.php'; 

// Set zona waktu
date_default_timezone_set('Asia/Jakarta');
setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'indonesian');

// Ambil filter dari parameter URL
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : intval(date('m'));
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : intval(date('Y'));
$jurusan = isset($_GET['jurusan']) ? trim($_GET['jurusan']) : '';
$kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';

// Validasi bulan dan tahun
if ($bulan < 1 || $bulan > 12) $bulan = intval(date('m'));
if ($tahun < 2020 || $tahun > 2030) $tahun = intval(date('Y'));

// Query untuk mengambil data statistik bulanan dengan prepared statement
$query = "SELECT 
            DAY(tanggal) as tanggal,
            COUNT(CASE WHEN status = 'hadir' THEN 1 END) as hadir,
            COUNT(CASE WHEN status = 'sakit' THEN 1 END) as sakit,
            COUNT(CASE WHEN status = 'izin' THEN 1 END) as izin,
            COUNT(CASE WHEN status = 'alpha' THEN 1 END) as alpha
          FROM absensi 
          WHERE MONTH(tanggal) = ? 
          AND YEAR(tanggal) = ?
          AND mata_pelajaran = ?";

$params = [$bulan, $tahun, $mata_pelajaran];
$types = "iis";

if (!empty($jurusan)) {
    $query .= " AND jurusan = ?";
    $params[] = $jurusan;
    $types .= "s";
}

if (!empty($kelas)) {
    $query .= " AND kelas = ?";
    $params[] = $kelas;
    $types .= "s";
}

$query .= " GROUP BY DAY(tanggal) ORDER BY tanggal";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $statistik = [];
    $total = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];

    while ($row = mysqli_fetch_assoc($result)) {
        $statistik[$row['tanggal']] = $row;
        $total['hadir'] += $row['hadir'];
        $total['sakit'] += $row['sakit'];
        $total['izin'] += $row['izin'];
        $total['alpha'] += $row['alpha'];
    }
    
    mysqli_stmt_close($stmt);
} else {
    $statistik = [];
    $total = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0];
}

// Hitung total keseluruhan untuk persentase
$total_keseluruhan = array_sum($total);

// Fungsi untuk format nama bulan
function getNamaBulanIndo($bulan) {
    $bulan_indo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $bulan_indo[$bulan] ?? '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik Absensi - <?php echo htmlspecialchars($nama); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
        }   
        
        .main-content {
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            min-height: 100vh;
            margin-left: 0;
        }

        .sidebar {
            width: 260px;
            flex-shrink: 0;
        }
        
        .header-section {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .header-section h3 {
            font-weight: 700;
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            position: relative;
            z-index: 2;
        }
        
        .header-section .guru-info {
            font-size: 0.9rem;
            opacity: 0.95;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: inline-block;
            position: relative;
            z-index: 2;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .filter-title {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        .chart-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 18px 18px 0 0;
        }
        
        .stat-card.hadir::before { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-card.sakit::before { background: linear-gradient(135deg, #ed8936, #dd6b20); }
        .stat-card.izin::before { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-card.alpha::before { background: linear-gradient(135deg, #f56565, #e53e3e); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card.hadir .stat-icon { background: linear-gradient(135deg, #48bb78, #38a169); color: white; }
        .stat-card.sakit .stat-icon { background: linear-gradient(135deg, #ed8936, #dd6b20); color: white; }
        .stat-card.izin .stat-icon { background: linear-gradient(135deg, #4299e1, #3182ce); color: white; }
        .stat-card.alpha .stat-icon { background: linear-gradient(135deg, #f56565, #e53e3e); color: white; }
        
        .stat-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #718096;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            line-height: 1;
        }
        
        .stat-percentage {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stat-card.hadir .stat-percentage { background: rgba(72, 187, 120, 0.1); color: #38a169; }
        .stat-card.sakit .stat-percentage { background: rgba(237, 137, 54, 0.1); color: #dd6b20; }
        .stat-card.izin .stat-percentage { background: rgba(66, 153, 225, 0.1); color: #3182ce; }
        .stat-card.alpha .stat-percentage { background: rgba(245, 101, 101, 0.1); color: #e53e3e; }
        
        .insights-section {
            background: white;
            border-radius: 18px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .insight-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: background-color 0.3s ease;
        }
        
        .insight-item:hover {
            background: #f7fafc;
        }
        
        .insight-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .chart-wrapper {
                height: 280px;
            }
            
            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar_guru.php'; ?>

<div class="main-content">
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-section">
            <h3>
                <i class="fas fa-chart-line"></i> 
                Statistik Absensi <?php echo getNamaBulanIndo($bulan) . ' ' . $tahun; ?>
            </h3>
            <div class="guru-info">
            <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($nama); ?> 
                | <i class=""></i> <?php echo htmlspecialchars($mata_pelajaran); ?>
                | <i class=""></i><?php echo htmlspecialchars($nip); ?>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Data Statistik
            </div>
            <form method="GET" class="row g-3" id="filterForm">
                <!-- Hidden input user_id - PENTING! -->
                <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">
                
                <div class="col-md-3">
                    <label class="form-label">Bulan</label>
                    <select class="form-select" name="bulan" id="bulan">
                        <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>>
                                <?= getNamaBulanIndo($i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Tahun</label>
                    <select class="form-select" name="tahun" id="tahun">
                        <?php for($y = 2020; $y <= 2030; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Jurusan</label>
                    <select class="form-select" name="jurusan" id="jurusan">
                        <option value="">Semua Jurusan</option>
                        <option value="IPA" <?= $jurusan == 'IPA' ? 'selected' : '' ?>>IPA</option>
                        <option value="IPS" <?= $jurusan == 'IPS' ? 'selected' : '' ?>>IPS</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Kelas</label>
                    <select class="form-select" name="kelas" id="kelas">
                        <option value="">Semua Kelas</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tampilkan Statistik
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <?php if ($total_keseluruhan == 0): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h4>Tidak Ada Data</h4>
                <p>Belum ada data absensi untuk periode yang dipilih</p>
                <small>Pilih bulan, tahun, atau kelas lain untuk melihat statistik</small>
            </div>
        <?php else: ?>
            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-area"></i>
                        Tren Absensi Harian
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="statistikChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Distribusi Status
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card hadir">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-title">Total Hadir</div>
                    <div class="stat-value"><?= number_format($total['hadir']) ?></div>
                    <div class="stat-percentage">
                        <i class="fas fa-arrow-up"></i>
                        <?= $total_keseluruhan > 0 ? round(($total['hadir']/$total_keseluruhan)*100, 1) : 0 ?>%
                    </div>
                </div>
                
                <div class="stat-card sakit">
                    <div class="stat-icon">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="stat-title">Total Sakit</div>
                    <div class="stat-value"><?= number_format($total['sakit']) ?></div>
                    <div class="stat-percentage">
                        <i class="fas fa-thermometer-half"></i>
                        <?= $total_keseluruhan > 0 ? round(($total['sakit']/$total_keseluruhan)*100, 1) : 0 ?>%
                    </div>
                </div>
                
                <div class="stat-card izin">
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-title">Total Izin</div>
                    <div class="stat-value"><?= number_format($total['izin']) ?></div>
                    <div class="stat-percentage">
                        <i class="fas fa-calendar-check"></i>
                        <?= $total_keseluruhan > 0 ? round(($total['izin']/$total_keseluruhan)*100, 1) : 0 ?>%
                    </div>
                </div>
                
                <div class="stat-card alpha">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-title">Total Alpha</div>
                    <div class="stat-value"><?= number_format($total['alpha']) ?></div>
                    <div class="stat-percentage">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= $total_keseluruhan > 0 ? round(($total['alpha']/$total_keseluruhan)*100, 1) : 0 ?>%
                    </div>
                </div>
            </div>

            <!-- Insights Section -->
            <div class="insights-section">
                <div class="chart-title" style="margin-bottom: 20px;">
                    <i class="fas fa-lightbulb"></i>
                    Insight & Analisis
                </div>
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div>
                        <strong>Tingkat Kehadiran:</strong> 
                        <?php 
                        $persentase_hadir = $total_keseluruhan > 0 ? round(($total['hadir']/$total_keseluruhan)*100, 1) : 0;
                        if($persentase_hadir >= 90) echo "Sangat Baik ($persentase_hadir%)";
                        elseif($persentase_hadir >= 80) echo "Baik ($persentase_hadir%)";
                        elseif($persentase_hadir >= 70) echo "Cukup ($persentase_hadir%)";
                        else echo "Perlu Perhatian ($persentase_hadir%)";
                        ?>
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <strong>Status Perhatian:</strong> 
                        <?php
                        $persentase_alpha = $total_keseluruhan > 0 ? round(($total['alpha']/$total_keseluruhan)*100, 1) : 0;
                        if($persentase_alpha > 10) echo "Tingkat Alpha Tinggi - Perlu Tindak Lanjut ($persentase_alpha%)";
                        elseif($persentase_alpha > 5) echo "Tingkat Alpha Sedang - Perlu Monitoring ($persentase_alpha%)";
                        else echo "Tingkat Alpha Rendah - Kondisi Normal ($persentase_alpha%)";
                        ?>
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <strong>Total Hari Aktif:</strong> <?= count($statistik) ?> hari dalam bulan ini
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <strong>Mata Pelajaran:</strong> <?= htmlspecialchars($mata_pelajaran) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
console.log('=== Statistik Absensi Script Loaded ===');

const currentUserId = <?php echo $current_user_id; ?>;
const statistikData = <?= json_encode($statistik) ?>;
const totalData = <?= json_encode($total) ?>;

console.log('User ID:', currentUserId);
console.log('Statistik Data:', statistikData);
console.log('Total Data:', totalData);

// Update kelas dropdown berdasarkan jurusan
function updateKelasDropdown() {
    const kelasIPA = ['X IPA 1', 'X IPA 2', 'XI IPA 1', 'XI IPA 2', 'XII IPA 1', 'XII IPA 2'];
    const kelasIPS = ['X IPS 1', 'X IPS 2', 'XI IPS 1', 'XI IPS 2', 'XII IPS 1', 'XII IPS 2'];
    
    const jurusan = document.getElementById('jurusan').value;
    const kelasSelect = document.getElementById('kelas');
    const currentKelas = '<?= htmlspecialchars($kelas) ?>';
    
    console.log('Update kelas dropdown, jurusan:', jurusan, 'current kelas:', currentKelas);
    
    kelasSelect.innerHTML = '<option value="">Semua Kelas</option>';
    
    let kelasList = [];
    if (jurusan === 'IPA') {
        kelasList = kelasIPA;
    } else if (jurusan === 'IPS') {
        kelasList = kelasIPS;
    } else {
        kelasList = [...kelasIPA, ...kelasIPS];
    }
    
    kelasList.forEach(kelas => {
        const option = document.createElement('option');
        option.value = kelas;
        option.text = kelas;
        option.selected = kelas === currentKelas;
        kelasSelect.appendChild(option);
    });
    
    console.log('Kelas options updated:', kelasList.length);
}

function resetFilter() {
    console.log('Reset filter');
    window.location.href = window.location.pathname + '?user_id=' + currentUserId;
}

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    
    updateKelasDropdown();
    hideLoading();
    
    // Event listener untuk jurusan
    document.getElementById('jurusan').addEventListener('change', function() {
        console.log('Jurusan changed');
        updateKelasDropdown();
    });
    
    // Chart hanya jika ada data
    <?php if ($total_keseluruhan > 0): ?>
    
    // Data untuk grafik
    const labels = Object.keys(statistikData).map(day => `Tanggal ${day}`);
    const datasets = {
        hadir: Object.keys(statistikData).map(day => statistikData[day].hadir),
        sakit: Object.keys(statistikData).map(day => statistikData[day].sakit),
        izin: Object.keys(statistikData).map(day => statistikData[day].izin),
        alpha: Object.keys(statistikData).map(day => statistikData[day].alpha)
    };

    // Line Chart
    new Chart(document.getElementById('statistikChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Hadir',
                data: datasets.hadir,
                borderColor: '#48bb78',
                backgroundColor: 'rgba(72, 187, 120, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#48bb78',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'Sakit',
                data: datasets.sakit,
                borderColor: '#ed8936',
                backgroundColor: 'rgba(237, 137, 54, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#ed8936',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'Izin',
                data: datasets.izin,
                borderColor: '#4299e1',
                backgroundColor: 'rgba(66, 153, 225, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#4299e1',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'Alpha',
                data: datasets.alpha,
                borderColor: '#f56565',
                backgroundColor: 'rgba(245, 101, 101, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#f56565',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#2d3748',
                    bodyColor: '#4a5568',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '500' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    ticks: {
                        stepSize: 1,
                        font: { weight: '500' }
                    }
                }
            }
        }
    });

    // Pie Chart
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Alpha'],
            datasets: [{
                data: [
                    totalData.hadir,
                    totalData.sakit,
                    totalData.izin,
                    totalData.alpha
                ],
                backgroundColor: ['#48bb78', '#ed8936', '#4299e1', '#f56565'],
                borderWidth: 0,
                hoverBorderWidth: 3,
                hoverBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#2d3748',
                    bodyColor: '#4a5568',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    <?php endif; ?>
    
    console.log('Initialization complete');
});
</script>

</body>
</html>