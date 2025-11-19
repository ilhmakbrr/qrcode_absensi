<?php
require_once '../auth_user/auth_check_yayasan.php';

// Koneksi database
include '../../db.php';
include 'func_kinerja.php';

$ketua_id = $_SESSION['user_id'];
$query_profile = "SELECT * FROM ketua_yayasan WHERE id = $ketua_id";
$result_profile = mysqli_query($conn, $query_profile);
$profile = mysqli_fetch_assoc($result_profile);


$periode = isset($_GET['periode']) ? $_GET['periode'] : date('Y-m');

// Get profile ketua yayasan
$ketua_id = $_SESSION['user_id'];
$query_ketua = "SELECT * FROM ketua_yayasan WHERE id = ?";
$stmt = mysqli_prepare($conn, $query_ketua);
mysqli_stmt_bind_param($stmt, "i", $ketua_id);
mysqli_stmt_execute($stmt);
$result_ketua = mysqli_stmt_get_result($stmt);
$ketua = mysqli_fetch_assoc($result_ketua);

// Get data kinerja semua guru
$guru_kinerja = getAllGuruKinerja($conn, $periode);

// Sorting berdasarkan skor (tertinggi ke terendah)
usort($guru_kinerja, function($a, $b) {
    return $b['skor_kinerja'] <=> $a['skor_kinerja'];
});

// Hitung statistik
$total_guru = count($guru_kinerja);
$rata_rata_skor = $total_guru > 0 ? array_sum(array_column($guru_kinerja, 'skor_kinerja')) / $total_guru : 0;
$guru_excellent = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'excellent'));
$guru_good = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'good'));
$guru_average = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'average'));
$guru_poor = count(array_filter($guru_kinerja, fn($g) => $g['kategori'] === 'poor'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kinerja Guru - <?php echo formatBulanIndonesia($periode); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image" href="../../gambar/SMA-PGRI-2-Padang-logo-ok.webp">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ========== TAMPILAN WEB (Optimized Layout) ========== */
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f5f5f5;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Card Styling */
        .card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Header Card - Compact */
        .header-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border: none;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            flex: 1;
        }

        .school-name {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .report-title {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .header-right {
            text-align: right;
        }

        .period {
            font-size: 1.125rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
        }

        /* Top Section - 2 Columns */
        .top-section {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Info Card - Compact */
        .info-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .info-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .info-row {
            display: flex;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 120px;
            color: #6c757d;
            font-weight: 500;
        }

        .info-value {
            flex: 1;
            color: #2c3e50;
            font-weight: 600;
        }

        /* Stats Card */
        .stats-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stats-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .stat-box {
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-box.excellent .stat-value { color: #27ae60; }
        .stat-box.good .stat-value { color: #3498db; }
        .stat-box.average .stat-value { color: #f39c12; }
        .stat-box.poor .stat-value { color: #e74c3c; }

        /* Bottom Section - Legend & Table Side by Side */
        .bottom-section {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1.5rem;
        }

        /* Legend Card */
        .legend-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .legend-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .legend-list {
            list-style: none;
            padding: 0;
        }

        .legend-list li {
            padding: 0.625rem 0;
            font-size: 0.8rem;
            color: #495057;
            padding-left: 1.5rem;
            position: relative;
            line-height: 1.5;
        }

        .legend-list li:before {
            content: '▪';
            position: absolute;
            left: 0;
            color: #3498db;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            background: #fafafa;
            border-bottom: 2px solid #e0e0e0;
        }

        .table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        table thead {
            background: #2c3e50;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        table th {
            padding: 0.875rem 0.75rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
            color: #2c3e50;
        }

        table tbody tr:hover {
            background: #fafafa;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.325rem 0.75rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-excellent {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #27ae60;
        }

        .badge-good {
            background: #d6eaf8;
            color: #3498db;
            border: 1px solid #3498db;
        }

        .badge-average {
            background: #fef5e7;
            color: #f39c12;
            border: 1px solid #f39c12;
        }

        .badge-poor {
            background: #fadbd8;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .score {
            display: inline-block;
            background: #2c3e50;
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Print Button */
        .btn-print {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.3);
            transition: all 0.3s;
            z-index: 1000;
        }

        .btn-print:hover {
            background: #1a252f;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(44, 62, 80, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        /* ========== PRINT STYLES (A4 - 1 HALAMAN) ========== */
        @page {
            size: A4;
            margin: 1cm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                background: white;
                padding: 0;
                font-size: 9pt;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }

            .card, .info-card, .stats-card, .legend-card, .table-card {
                box-shadow: none;
                border: 1px solid #ddd;
                border-radius: 0;
                page-break-inside: avoid;
                margin-bottom: 0.5rem;
                padding: 0.75rem;
            }

            /* Reset Grid Layouts for Print */
            .top-section,
            .bottom-section {
                display: block;
            }

            /* Header - Compact */
            .header-card {
                background: white !important;
                color: #000 !important;
                border: 2px solid #000 !important;
                padding: 0.75rem !important;
                margin-bottom: 0.5rem !important;
                display: block !important;
                text-align: center;
            }

            .header-left,
            .header-right {
                text-align: center;
            }

            .school-name {
                font-size: 1.1rem;
                color: #000;
            }

            .report-title {
                font-size: 0.9rem;
                color: #000;
            }

            .period {
                font-size: 0.75rem;
                color: #000;
                background: none !important;
                padding: 0;
            }

            /* Info - Compact */
            .info-card {
                background: white !important;
                padding: 0.5rem !important;
            }

            .info-title,
            .stats-title,
            .legend-title {
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
                padding-bottom: 0.4rem;
                border-bottom: 1px solid #000;
            }

            .info-row {
                padding: 0.25rem 0;
                font-size: 0.7rem;
                border-bottom: 1px dotted #ccc;
            }

            .info-label {
                width: 100px;
            }

            /* Stats - Compact */
            .stats-grid {
                gap: 0.4rem;
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
            }

            .stat-box {
                background: #f5f5f5 !important;
                padding: 0.5rem;
                border: 1px solid #ddd;
                border-radius: 0;
            }

            .stat-value {
                font-size: 1.2rem;
            }

            .stat-label {
                font-size: 0.6rem;
            }

            /* Table - Ultra Compact */
            .table-card {
                border: 1px solid #ddd;
                padding: 0 !important;
            }

            .table-header {
                padding: 0.5rem;
                background: #f5f5f5 !important;
                border-bottom: 1px solid #000;
            }

            .table-header h3 {
                font-size: 0.75rem;
            }

            table {
                font-size: 0.6rem;
                margin: 0;
            }

            table th {
                padding: 0.35rem 0.25rem;
                font-size: 0.55rem;
                border: 1px solid #333;
            }

            table td {
                padding: 0.35rem 0.25rem;
                border: 1px solid #ddd;
            }

            .badge {
                padding: 0.15rem 0.4rem;
                font-size: 0.5rem;
                border-radius: 2px;
            }

            .score {
                padding: 0.2rem 0.4rem;
                font-size: 0.6rem;
                border-radius: 2px;
            }

            /* Legend - Compact */
            .legend-card {
                background: white !important;
                padding: 0.5rem !important;
            }

            .legend-list li {
                padding: 0.2rem 0;
                font-size: 0.6rem;
                padding-left: 1rem;
            }

            .legend-list li:before {
                font-size: 0.8rem;
            }

            /* Signature - Only in Print */
            .signature-card {
                display: block !important;
                background: white !important;
                border: none !important;
                padding: 0.75rem 0 0 0 !important;
                margin-top: 0.5rem !important;
                page-break-inside: avoid;
            }

            .signature-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }

            .signature-box {
                text-align: center;
                font-size: 0.65rem;
            }

            .signature-date {
                margin-bottom: 0.2rem;
                color: #000;
            }

            .signature-role {
                font-weight: 600;
                color: #000;
                margin-bottom: 2rem;
            }

            .signature-line {
                border-top: 1px solid #000;
                padding-top: 0.3rem;
                margin-top: 2rem;
            }

            .signature-name {
                font-weight: 600;
                color: #000;
            }

            /* Force print colors */
            .stat-box.excellent .stat-value { color: #27ae60 !important; }
            .stat-box.good .stat-value { color: #3498db !important; }
            .stat-box.average .stat-value { color: #f39c12 !important; }
            .stat-box.poor .stat-value { color: #e74c3c !important; }
        }

        /* Hide signature in web view */
        @media screen {
            .signature-card {
                display: none;
            }
        }

        /* Responsive Web View */
        @media screen and (max-width: 1200px) {
            .container {
                padding: 1.5rem;
            }

            .top-section,
            .bottom-section {
                grid-template-columns: 1fr;
            }

            .legend-card {
                position: static;
            }
        }

        @media screen and (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-card {
                flex-direction: column;
                text-align: center;
            }

            .header-left {
                margin-bottom: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            table {
                font-size: 0.75rem;
            }

            table th,
            table td {
                padding: 0.625rem 0.5rem;
            }
        }

        @media screen and (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .school-name {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_yayasan.php'; ?>

    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Cetak Laporan
    </button>

    <div class="container">
        <!-- Header Card -->
        <div class="card header-card">
            <div class="header-left">
                <div class="school-name">SMA PGRI 2 PADANG</div>
                <div class="report-title">Laporan Kinerja Guru</div>
            </div>
            <div class="header-right">
                <div class="period"><?php echo formatBulanIndonesia($periode); ?></div>
            </div>
        </div>

        <!-- Top Section: Info + Stats -->
        <div class="top-section">
            <!-- Info Card -->
            <div class="info-card">
                <h3 class="info-title">Informasi Laporan</h3>
                <div class="info-row">
                    <span class="info-label">Tanggal Cetak</span>
                    <span class="info-value">: <?php echo date('d F Y'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dicetak Oleh</span>
                    <span class="info-value">: <?php echo htmlspecialchars($ketua['nama']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jabatan</span>
                    <span class="info-value">: Ketua Yayasan</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Guru</span>
                    <span class="info-value">: <?php echo $total_guru; ?> Guru</span>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="stats-card">
                <h3 class="stats-title">Ringkasan Statistik Kinerja</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($rata_rata_skor, 1); ?></div>
                        <div class="stat-label">Rata-rata Skor</div>
                    </div>
                    <div class="stat-box excellent">
                        <div class="stat-value"><?php echo $guru_excellent; ?></div>
                        <div class="stat-label">Excellent</div>
                    </div>
                    <div class="stat-box good">
                        <div class="stat-value"><?php echo $guru_good; ?></div>
                        <div class="stat-label">Good</div>
                    </div>
                    <div class="stat-box average">
                        <div class="stat-value"><?php echo $guru_average; ?></div>
                        <div class="stat-label">Average</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Section: Legend + Table -->
        <div class="bottom-section">
            <!-- Legend Card -->
            <div class="legend-card">
                <h3 class="legend-title">Kriteria Penilaian</h3>
                <ul class="legend-list">
                    <li><strong>Excellent (90-100)</strong><br>Kehadiran siswa sangat baik, guru sangat efektif</li>
                    <li><strong>Good (75-89)</strong><br>Kehadiran siswa baik, guru efektif</li>
                    <li><strong>Average (60-74)</strong><br>Kehadiran siswa cukup, perlu peningkatan</li>
                    <li><strong>Poor (&lt;60)</strong><br>Kehadiran siswa rendah, perlu perhatian khusus</li>
                </ul>
            </div>

            <!-- Table Card -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Detail Kinerja Guru</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th>Nama Guru</th>
                                <th>NIP</th>
                                <th>Mata Pelajaran</th>
                                <th style="width: 60px; text-align: center;">Kelas</th>
                                <th style="width: 90px; text-align: center;">Kehadiran</th>
                                <th style="width: 70px; text-align: center;">Skor</th>
                                <th style="width: 90px; text-align: center;">Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guru_kinerja)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            Tidak ada data untuk periode ini
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($guru_kinerja as $guru): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600;"><?php echo $no++; ?></td>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($guru['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($guru['nip']); ?></td>
                                        <td><?php echo htmlspecialchars($guru['mata_pelajaran']); ?></td>
                                        <td style="text-align: center; font-weight: 600;"><?php echo $guru['total_kelas']; ?></td>
                                        <td style="text-align: center; font-weight: 600; color: #3498db;">
                                            <?php echo number_format($guru['persentase_hadir'], 1); ?>%
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="score"><?php echo number_format($guru['skor_kinerja'], 1); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $badge_class = 'badge-' . $guru['kategori'];
                                            $badge_label = getBadgeKategori($guru['kategori'])['label'];
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Signature Card - ONLY VISIBLE IN PRINT -->
        <div class="card signature-card">
            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-date">Mengetahui,</div>
                    <div class="signature-role">Kepala Sekolah</div>
                    <div class="signature-line">
                        <div class="signature-name">( _________________ )</div>
                    </div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-date">Padang, <?php echo date('d F Y'); ?></div>
                    <div class="signature-role">Ketua Yayasan</div>
                    <div class="signature-line">
                        <div class="signature-name"><?php echo htmlspecialchars($ketua['nama']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>