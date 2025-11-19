<?php
date_default_timezone_set('Asia/Jakarta');
include '../auth_user/auth_check_siswa.php';

$query_siswa = $conn->prepare("SELECT nis, nama, kelas, jurusan FROM siswa WHERE id = ?");
$query_siswa->bind_param("i", $siswa_id);
$query_siswa->execute();
$result_siswa = $query_siswa->get_result();
$data_siswa = $result_siswa->fetch_assoc();

// Ambil jadwal untuk kelas siswa ini dari jadwal_guru
$query_jadwal = $conn->prepare("
    SELECT 
        jg.id,
        jg.hari,
        jg.jam_mulai,
        jg.jam_selesai,
        jg.mata_pelajaran,
        jg.jurusan,
        jg.kelas,
        g.nama as nama_guru,
        g.nip
    FROM jadwal_guru jg
    LEFT JOIN guru g ON jg.guru_id = g.id
    WHERE jg.kelas = ? AND jg.status = 'aktif'
    ORDER BY 
        FIELD(jg.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'),
        jg.jam_mulai
");
$query_jadwal->bind_param("s", $data_siswa['kelas']);
$query_jadwal->execute();
$result_jadwal = $query_jadwal->get_result();

// Fungsi untuk mendapatkan hari ini dalam bahasa Indonesia
function getHariIni() {
    $hari_en = date('l');
    $hari_id = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    return $hari_id[$hari_en];
}

$hari_ini = getHariIni();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pelajaran - <?= htmlspecialchars($data_siswa['nama']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            line-height: 1.6;
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

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .page-header p {
            font-size: 15px;
            margin: 0;
            opacity: 0.9;
        }

        /* Info Card */
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .info-item label {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Current Time Indicator */
        .current-time-indicator {
            background: linear-gradient(90deg, #2ecc71, #27ae60);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .pulse {
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
            overflow: hidden;
        }

        .table-card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-card-title {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-card-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .table-wrapper {
            overflow-x: auto;
            padding: 25px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead th {
            background: #f8f9fc;
            color: #5a6c7d;
            text-align: left;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e8ecf1;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        thead th:first-child {
            text-align: center;
            width: 60px;
        }

        tbody td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f3f6;
            vertical-align: middle;
        }

        tbody td:first-child {
            text-align: center;
            font-weight: 600;
            color: #7f8c8d;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fc;
            transform: scale(1.01);
        }

        tbody tr.current-day {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
        }

        tbody tr.current-class {
            background: #d4edda;
            border-left: 4px solid #2ecc71;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .badge-hari {
            background: #e8f4fd;
            color: #3498db;
        }

        .badge-hari.active {
            background: #3498db;
            color: white;
        }

        .badge-waktu {
            background: #d4edda;
            color: #27ae60;
        }

        .badge-jurusan {
            background: #fff3cd;
            color: #856404;
        }

        .badge-kelas {
            background: #f8d7da;
            color: #721c24;
        }

        /* Mata Pelajaran */
        .mapel-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .guru-info {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #f8f9fc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e0;
            font-size: 36px;
        }

        .empty-text {
            color: #7f8c8d;
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 22px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .table-card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .table-wrapper {
                padding: 15px;
            }

            thead th {
                font-size: 11px;
                padding: 12px 10px;
            }

            tbody td {
                padding: 12px 10px;
                font-size: 13px;
            }

            .mapel-name {
                font-size: 14px;
            }

            .guru-info {
                font-size: 12px;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }

            .main-content {
                box-shadow: none;
                margin: 0;
                padding: 20px;
            }

            .current-time-indicator {
                display: none;
            }

            tbody tr {
                break-inside: avoid;
            }
        }

    </style>
</head>
<body>

<?php include 'sidebar_siswa.php'; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</h1>
        <p>Jadwal mata pelajaran untuk kelas <?= htmlspecialchars($data_siswa['kelas']); ?> - Jurusan <?= htmlspecialchars($data_siswa['jurusan']); ?></p>
    </div>

    <!-- Info Card -->
    <div class="info-card">
        <div class="info-grid">
            <div class="info-item">
                <label>Nama Lengkap</label>
                <div class="value"><?= htmlspecialchars($data_siswa['nama']); ?></div>
            </div>
            <div class="info-item">
                <label>NIS</label>
                <div class="value"><?= htmlspecialchars($data_siswa['nis']); ?></div>
            </div>
            <div class="info-item">
                <label>Kelas</label>
                <div class="value"><?= htmlspecialchars($data_siswa['kelas']); ?></div>
            </div>
            <div class="info-item">
                <label>Jurusan</label>
                <div class="value"><?= htmlspecialchars($data_siswa['jurusan']); ?></div>
            </div>
        </div>
    </div>

    <?php if ($hari_ini !== 'Minggu'): ?>
        <div class="current-time-indicator">
            <div class="pulse"></div>
            <i class="fas fa-calendar-day"></i>
            <strong>Hari Ini: <?= $hari_ini; ?>, <?= date('d F Y'); ?></strong>
        </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="table-card-title">
                <i class="fas fa-table"></i>
                Jadwal Pelajaran
            </div>
            <div class="table-card-badge">
                <?= mysqli_num_rows($result_jadwal); ?> Jadwal
            </div>
        </div>

        <?php if (mysqli_num_rows($result_jadwal) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Jadwal</th>
                            <th>Mata Pelajaran</th>
                            <th>Jurusan</th>
                            <th>Kelas</th>
                            <th>Hari</th>
                            <th>Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        mysqli_data_seek($result_jadwal, 0); // Reset pointer
                        while ($jadwal = mysqli_fetch_assoc($result_jadwal)): 
                            // Cek apakah ini hari ini
                            $is_today = ($jadwal['hari'] === $hari_ini);
                            
                            // Cek apakah jadwal sedang berlangsung
                            $is_current = false;
                            if ($is_today) {
                                $current_time = date('H:i:s');
                                if ($current_time >= $jadwal['jam_mulai'] && $current_time <= $jadwal['jam_selesai']) {
                                    $is_current = true;
                                }
                            }
                            
                            $row_class = '';
                            if ($is_current) {
                                $row_class = 'current-class';
                            } elseif ($is_today) {
                                $row_class = 'current-day';
                            }
                        ?>
                            <tr class="<?= $row_class; ?>" data-start="<?= $jadwal['jam_mulai']; ?>" data-end="<?= $jadwal['jam_selesai']; ?>">
                                <td><?= $no++; ?></td>
                                <td>
                                    <span style="color: #7f8c8d; font-weight: 600;">#<?= htmlspecialchars($jadwal['id']); ?></span>
                                </td>
                                <td>
                                    <div class="mapel-name"><?= htmlspecialchars($jadwal['mata_pelajaran']); ?></div>
                                    <div class="guru-info">
                                        <i class="fas fa-user-tie"></i>
                                        <?= htmlspecialchars($jadwal['nama_guru'] ?? 'Belum ditentukan'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-jurusan"><?= htmlspecialchars($jadwal['jurusan']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-kelas"><?= htmlspecialchars($jadwal['kelas']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-hari <?= $is_today ? 'active' : ''; ?>">
                                        <?= htmlspecialchars($jadwal['hari']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-waktu">
                                        <i class="fas fa-clock"></i>
                                        <?= date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?= date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="empty-text">
                    Belum ada jadwal pelajaran yang tersedia
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Check and highlight current class
    function checkCurrentClass() {
        const now = new Date();
        const currentTime = now.getHours() * 60 + now.getMinutes();
        const hariIni = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][now.getDay()];
        
        document.querySelectorAll('tbody tr').forEach(row => {
            const startTime = row.dataset.start;
            const endTime = row.dataset.end;
            
            if (startTime && endTime) {
                const [startHour, startMin, startSec] = startTime.split(':').map(Number);
                const [endHour, endMin, endSec] = endTime.split(':').map(Number);
                
                const startMinutes = startHour * 60 + startMin;
                const endMinutes = endHour * 60 + endMin;
                
                // Reset classes
                row.classList.remove('current-class', 'current-day');
                
                // Check if this is the current class
                if (currentTime >= startMinutes && currentTime <= endMinutes) {
                    const dayBadge = row.querySelector('.badge-hari');
                    if (dayBadge && dayBadge.classList.contains('active')) {
                        row.classList.add('current-class');
                        
                        // Scroll to current class
                        setTimeout(() => {
                            row.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                        }, 500);
                    }
                } else {
                    // Check if this is today's schedule
                    const dayBadge = row.querySelector('.badge-hari');
                    if (dayBadge && dayBadge.classList.contains('active')) {
                        row.classList.add('current-day');
                    }
                }
            }
        });
    }

    // Run on load and every minute
    window.addEventListener('load', checkCurrentClass);
    setInterval(checkCurrentClass, 60000);

    // Hover effect for table rows
    document.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.cursor = 'pointer';
        });
    });
</script>
</body>
</html>