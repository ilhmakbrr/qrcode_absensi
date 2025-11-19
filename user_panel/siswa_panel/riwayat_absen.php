<?php
date_default_timezone_set('Asia/Jakarta');
include '../auth_user/auth_check_siswa.php';

$query_siswa = $conn->prepare("SELECT nis, nama, kelas, jurusan FROM siswa WHERE id = ?");
$query_siswa->bind_param("i", $siswa_id);
$query_siswa->execute();
$result_siswa = $query_siswa->get_result();
$data_siswa = $result_siswa->fetch_assoc();

// Ambil semua mata pelajaran untuk kelas siswa ini dari jadwal_guru
$query_mapel = $conn->prepare("
    SELECT DISTINCT mata_pelajaran 
    FROM jadwal_guru 
    WHERE kelas = ? AND status = 'aktif'
    ORDER BY mata_pelajaran
");
$query_mapel->bind_param("s", $data_siswa['kelas']);
$query_mapel->execute();
$result_mapel = $query_mapel->get_result();

// Array untuk menyimpan data absensi per mata pelajaran
$absensi_per_mapel = [];
$total_pertemuan = 24; // 24 minggu dalam semester

while ($mapel = $result_mapel->fetch_assoc()) {
    $mata_pelajaran = $mapel['mata_pelajaran'];
    
    // Ambil data absensi untuk mata pelajaran ini
    $query_absensi = $conn->prepare("
        SELECT tanggal, status 
        FROM absensi 
        WHERE nis = ? AND mata_pelajaran = ?
        ORDER BY tanggal ASC
    ");
    $query_absensi->bind_param("ss", $data_siswa['nis'], $mata_pelajaran);
    $query_absensi->execute();
    $result_absensi = $query_absensi->get_result();
    
    // Inisialisasi array untuk 24 pertemuan
    $pertemuan_data = array_fill(1, $total_pertemuan, '-');
    
    $pertemuan_ke = 1;
    while ($absensi = $result_absensi->fetch_assoc()) {
        if ($pertemuan_ke <= $total_pertemuan) {
            $status = strtolower($absensi['status']);
            switch ($status) {
                case 'hadir':
                    $pertemuan_data[$pertemuan_ke] = 'H';
                    break;
                case 'alpha':
                    $pertemuan_data[$pertemuan_ke] = 'A';
                    break;
                case 'sakit':
                    $pertemuan_data[$pertemuan_ke] = 'S';
                    break;
                case 'izin':
                    $pertemuan_data[$pertemuan_ke] = 'I';
                    break;
                default:
                    $pertemuan_data[$pertemuan_ke] = '-';
            }
            $pertemuan_ke++;
        }
    }
    
    // Hitung statistik
    $hadir = count(array_filter($pertemuan_data, fn($v) => $v === 'H'));
    $alpha = count(array_filter($pertemuan_data, fn($v) => $v === 'A'));
    $sakit = count(array_filter($pertemuan_data, fn($v) => $v === 'S'));
    $izin = count(array_filter($pertemuan_data, fn($v) => $v === 'I'));
    $total_tercatat = $hadir + $alpha + $sakit + $izin;
    $persentase = $total_tercatat > 0 ? round(($hadir / $total_tercatat) * 100, 1) : 0;
    
    $absensi_per_mapel[$mata_pelajaran] = [
        'pertemuan' => $pertemuan_data,
        'hadir' => $hadir,
        'alpha' => $alpha,
        'sakit' => $sakit,
        'izin' => $izin,
        'persentase' => $persentase
    ];
}

// Hitung statistik keseluruhan
$total_hadir = array_sum(array_column($absensi_per_mapel, 'hadir'));
$total_alpha = array_sum(array_column($absensi_per_mapel, 'alpha'));
$total_sakit = array_sum(array_column($absensi_per_mapel, 'sakit'));
$total_izin = array_sum(array_column($absensi_per_mapel, 'izin'));
$total_semua = $total_hadir + $total_alpha + $total_sakit + $total_izin;
$persentase_keseluruhan = $total_semua > 0 ? round(($total_hadir / $total_semua) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - <?= htmlspecialchars($data_siswa['nama']); ?></title>
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
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: #7f8c8d;
            font-size: 15px;
            margin: 0;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
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

        .stat-card.total::before { background: #3498db; }
        .stat-card.hadir::before { background: #2ecc71; }
        .stat-card.alpha::before { background: #e74c3c; }
        .stat-card.sakit::before { background: #f39c12; }
        .stat-card.izin::before { background: #9b59b6; }

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

        .stat-card.total .stat-icon { background: #3498db; }
        .stat-card.hadir .stat-icon { background: #2ecc71; }
        .stat-card.alpha .stat-icon { background: #e74c3c; }
        .stat-card.sakit .stat-icon { background: #f39c12; }
        .stat-card.izin .stat-icon { background: #9b59b6; }

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

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8ecf1;
            overflow: hidden;
            margin-bottom: 25px;
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

        .table-card-stats {
            display: flex;
            gap: 15px;
            font-size: 13px;
        }

        .table-card-stats span {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
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
            text-align: center;
            padding: 12px 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e8ecf1;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        thead th:first-child {
            text-align: left;
            padding-left: 15px;
        }
        
        tbody td {
            text-align: center;
            padding: 14px 8px;
            font-size: 13px;
            border-bottom: 1px solid #f1f3f6;
        }

        tbody td:first-child {
            text-align: left;
            padding-left: 15px;
            font-weight: 600;
            color: #2c3e50;
        }

        tbody tr:hover {
            background: #f8f9fc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Cell */
        .status-cell {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .status-cell.hadir {
            background: #d4edda;
            color: #155724;
        }

        .status-cell.alpha {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cell.sakit {
            background: #fff3cd;
            color: #856404;
        }

        .status-cell.izin {
            background: #e7d6f5;
            color: #6c2a8f;
        }

        .status-cell.kosong {
            background: #e9ecef;
            color: #adb5bd;
        }

        .status-cell:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-number {
                font-size: 28px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .table-card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .table-card-stats {
                flex-wrap: wrap;
            }

            .table-wrapper {
                padding: 15px;
            }

            thead th {
                font-size: 10px;
                padding: 10px 5px;
            }

            tbody td {
                padding: 10px 5px;
            }

            .status-cell {
                width: 32px;
                height: 32px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .legend-grid {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>
</head>
<body>

<?php include 'sidebar_siswa.php'; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Riwayat Absensi Semester</h1>
        <p>Rekap kehadiran per mata pelajaran (24 pertemuan)</p>
    </div>

    <!-- Info Card -->
    <div class="info-card">
        <div class="info-grid">
            <div class="info-item">
                <label>NIS</label>
                <div class="value"><?= htmlspecialchars($data_siswa['nis']); ?></div>
            </div>
            <div class="info-item">
                <label>Nama Lengkap</label>
                <div class="value"><?= htmlspecialchars($data_siswa['nama']); ?></div>
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

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $persentase_keseluruhan; ?>%</div>
                    <div class="stat-label">Persentase Kehadiran</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
        </div>

        <div class="stat-card hadir">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $total_hadir; ?></div>
                    <div class="stat-label">Total Hadir</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="stat-card alpha">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $total_alpha; ?></div>
                    <div class="stat-label">Total Alpha</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>

        <div class="stat-card sakit">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $total_sakit; ?></div>
                    <div class="stat-label">Total Sakit</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-notes-medical"></i>
                </div>
            </div>
        </div>

        <div class="stat-card izin">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $total_izin; ?></div>
                    <div class="stat-label">Total Izin</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Single Table -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="table-card-title">
                <i class="fas fa-table"></i>
                Rekap Absensi Semua Mata Pelajaran
            </div>
        </div>
        <?php if (count($absensi_per_mapel) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 50px;">No</th>
                            <th style="min-width: 200px;">Mata Pelajaran</th>
                            <?php for ($i = 1; $i <= 24; $i++): ?>
                                <th><?= $i; ?></th>
                            <?php endfor; ?>
                            <th style="min-width: 80px;">Hadir</th>
                            <th style="min-width: 80px;">Alpha</th>
                            <th style="min-width: 80px;">Sakit</th>
                            <th style="min-width: 80px;">Izin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($absensi_per_mapel as $mapel => $data): 
                        ?>
                            <tr>
                                <td style="text-align: center; font-weight: 600; color: #7f8c8d;"><?= $no++; ?></td>
                                <td style="text-align: left; font-weight: 600; color: #2c3e50;"><?= htmlspecialchars($mapel); ?></td>
                                <?php for ($i = 1; $i <= 24; $i++): ?>
                                    <td>
                                        <?php 
                                        $status = $data['pertemuan'][$i];
                                        $class = '';
                                        switch ($status) {
                                            case 'H': $class = 'hadir'; break;
                                            case 'A': $class = 'alpha'; break;
                                            case 'S': $class = 'sakit'; break;
                                            case 'I': $class = 'izin'; break;
                                            default: $class = 'kosong';
                                        }
                                        ?>
                                        <span class="status-cell <?= $class; ?>" title="Pertemuan <?= $i; ?>: <?= $status == 'H' ? 'Hadir' : ($status == 'A' ? 'Alpha' : ($status == 'S' ? 'Sakit' : ($status == 'I' ? 'Izin' : 'Belum Ada Data'))); ?>">
                                            <?= $status; ?>
                                        </span>
                                    </td>
                                <?php endfor; ?>
                                <td style="font-weight: 700;"><?= $data['hadir']; ?></td>
                                <td style="font-weight: 700;"><?= $data['alpha']; ?></td>
                                <td style="font-weight: 700;"><?= $data['sakit']; ?></td>
                                <td style="font-weight: 700;"><?= $data['izin']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="empty-text">
                    Belum ada data absensi yang tercatat
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Tooltip untuk status cell
    document.querySelectorAll('.status-cell').forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            this.style.cursor = 'pointer';
        });
    });
</script>

</body>
</html>