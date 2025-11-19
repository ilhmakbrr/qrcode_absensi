<?php
// =====================================================
// HELPER FUNCTIONS UNTUK SISTEM KINERJA GURU
// File: func_kinerja.php
// =====================================================

/**
 * Hitung kinerja guru berdasarkan kehadiran siswa
 * 
 * @param mysqli $conn Database connection
 * @param int $guru_id ID guru
 * @param string $periode Format: YYYY-MM
 * @return array Data kinerja guru
 */
function hitungKinerjaGuru($conn, $guru_id, $periode) {
    $data = [
        'total_pertemuan' => 0,
        'total_siswa_seharusnya' => 0,
        'total_siswa_hadir' => 0,
        'total_siswa_alpha' => 0,
        'total_siswa_izin' => 0,
        'total_siswa_sakit' => 0,
        'persentase_hadir' => 0,
        'skor_kinerja' => 0,
        'kategori' => 'average',
        'kelas_diajar' => []
    ];
    
    // Hitung total pertemuan mengajar dalam periode
    $query_pertemuan = "
        SELECT COUNT(DISTINCT DATE(a.tanggal)) as total
        FROM absensi a
        JOIN jadwal_guru jg ON a.kelas = jg.kelas AND a.mata_pelajaran = jg.mata_pelajaran
        WHERE jg.guru_id = ?
        AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
    ";
    
    $stmt = mysqli_prepare($conn, $query_pertemuan);
    mysqli_stmt_bind_param($stmt, "is", $guru_id, $periode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $data['total_pertemuan'] = $row['total'] ?? 0;
    
    // Hitung kehadiran siswa
    $query_kehadiran = "
        SELECT 
            COUNT(*) as total_siswa,
            SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) as alpha,
            SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit
        FROM absensi a
        JOIN jadwal_guru jg ON a.kelas = jg.kelas AND a.mata_pelajaran = jg.mata_pelajaran
        WHERE jg.guru_id = ?
        AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
    ";
    
    $stmt = mysqli_prepare($conn, $query_kehadiran);
    mysqli_stmt_bind_param($stmt, "is", $guru_id, $periode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $data['total_siswa_seharusnya'] = $row['total_siswa'] ?? 0;
    $data['total_siswa_hadir'] = $row['hadir'] ?? 0;
    $data['total_siswa_alpha'] = $row['alpha'] ?? 0;
    $data['total_siswa_izin'] = $row['izin'] ?? 0;
    $data['total_siswa_sakit'] = $row['sakit'] ?? 0;
    
    // Hitung persentase dan skor
    if ($data['total_siswa_seharusnya'] > 0) {
        $data['persentase_hadir'] = round(($data['total_siswa_hadir'] / $data['total_siswa_seharusnya']) * 100, 2);
        $data['skor_kinerja'] = $data['persentase_hadir']; // Skor = Persentase kehadiran
    }
    
    // Tentukan kategori
    $data['kategori'] = getKategoriKinerja($data['skor_kinerja']);
    
    // Ambil detail kelas yang diajar
    $data['kelas_diajar'] = getKelasYangDiajar($conn, $guru_id, $periode);
    
    return $data;
}

/**
 * Get kategori kinerja berdasarkan skor
 */
function getKategoriKinerja($skor) {
    if ($skor >= 90) return 'excellent';
    if ($skor >= 75) return 'good';
    if ($skor >= 60) return 'average';
    return 'poor';
}

/**
 * Get label dan warna badge kategori
 */
function getBadgeKategori($kategori) {
    $badges = [
        'excellent' => ['label' => 'Excellent', 'color' => 'success', 'bg' => '#d7f5e3', 'text' => '#0e6b31'],
        'good' => ['label' => 'Good', 'color' => 'primary', 'bg' => '#cfe2ff', 'text' => '#084298'],
        'average' => ['label' => 'Average', 'color' => 'warning', 'bg' => '#fff3cd', 'text' => '#997404'],
        'poor' => ['label' => 'Poor', 'color' => 'danger', 'bg' => '#f8d7da', 'text' => '#842029']
    ];
    
    return $badges[$kategori] ?? $badges['average'];
}

/**
 * Get detail kelas yang diajar guru dalam periode
 */
function getKelasYangDiajar($conn, $guru_id, $periode) {
    $kelas_data = [];
    
    $query = "
        SELECT 
            jg.kelas,
            jg.mata_pelajaran,
            jg.jurusan,
            COUNT(DISTINCT s.id) as total_siswa,
            COUNT(DISTINCT a.student_id) as total_absensi,
            SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
            ROUND((SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as persentase_hadir
        FROM jadwal_guru jg
        JOIN siswa s ON s.kelas = jg.kelas AND s.jurusan = jg.jurusan
        LEFT JOIN absensi a ON a.kelas = jg.kelas 
            AND a.mata_pelajaran = jg.mata_pelajaran 
            AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
        WHERE jg.guru_id = ?
        GROUP BY jg.kelas, jg.mata_pelajaran, jg.jurusan
        ORDER BY jg.kelas ASC
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $periode, $guru_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $persentase = $row['persentase_hadir'] ?? 0;
        $row['kategori'] = getKategoriKinerja($persentase);
        $kelas_data[] = $row;
    }
    
    return $kelas_data;
}

/**
 * Get semua guru dengan skor kinerja untuk periode tertentu
 */
function getAllGuruKinerja($conn, $periode) {
    $guru_list = [];
    
    // Ambil semua guru
    $query_guru = "SELECT id, nama, nip, mata_pelajaran, email FROM guru ORDER BY nama ASC";
    $result_guru = mysqli_query($conn, $query_guru);
    
    while ($guru = mysqli_fetch_assoc($result_guru)) {
        $kinerja = hitungKinerjaGuru($conn, $guru['id'], $periode);
        
        $guru_list[] = [
            'id' => $guru['id'],
            'nama' => $guru['nama'],
            'nip' => $guru['nip'],
            'mata_pelajaran' => $guru['mata_pelajaran'],
            'email' => $guru['email'],
            'total_kelas' => count($kinerja['kelas_diajar']),
            'persentase_hadir' => $kinerja['persentase_hadir'],
            'skor_kinerja' => $kinerja['skor_kinerja'],
            'kategori' => $kinerja['kategori']
        ];
    }
    
    return $guru_list;
}

/**
 * Simpan atau update evaluasi guru ke database
 */
function simpanEvaluasiGuru($conn, $guru_id, $periode) {
    $kinerja = hitungKinerjaGuru($conn, $guru_id, $periode);
    
    $query = "
        INSERT INTO evaluasi_guru (
            guru_id, periode, total_pertemuan,
            total_siswa_seharusnya, total_siswa_hadir,
            persentase_kehadiran_siswa, skor_kinerja, kategori
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_pertemuan = VALUES(total_pertemuan),
            total_siswa_seharusnya = VALUES(total_siswa_seharusnya),
            total_siswa_hadir = VALUES(total_siswa_hadir),
            persentase_kehadiran_siswa = VALUES(persentase_kehadiran_siswa),
            skor_kinerja = VALUES(skor_kinerja),
            kategori = VALUES(kategori),
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isiiddds", 
        $guru_id, 
        $periode, 
        $kinerja['total_pertemuan'],
        $kinerja['total_siswa_seharusnya'],
        $kinerja['total_siswa_hadir'],
        $kinerja['persentase_hadir'],
        $kinerja['skor_kinerja'],
        $kinerja['kategori']
    );
    
    return mysqli_stmt_execute($stmt);
}

/**
 * Format bulan Indonesia
 */
function formatBulanIndonesia($periode) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    
    list($tahun, $bulan_angka) = explode('-', $periode);
    return $bulan[$bulan_angka] . ' ' . $tahun;
}

/**
 * Get list periode (6 bulan terakhir)
 */
function getPeriodeList($jumlah_bulan = 6) {
    $periode_list = [];
    
    for ($i = 0; $i < $jumlah_bulan; $i++) {
        $tanggal = date('Y-m', strtotime("-$i months"));
        $periode_list[] = [
            'value' => $tanggal,
            'label' => formatBulanIndonesia($tanggal)
        ];
    }
    
    return $periode_list;
}

/**
 * Get warna untuk progress bar berdasarkan persentase
 */
function getProgressColor($persentase) {
    if ($persentase >= 90) return '#30d158'; // Hijau
    if ($persentase >= 75) return '#007bff'; // Biru
    if ($persentase >= 60) return '#ffc107'; // Kuning
    return '#dc3545'; // Merah
}
?>