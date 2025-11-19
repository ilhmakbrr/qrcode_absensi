// Data jurusan dan kelas
const kelasData = {
    'IPA': [
        { value: 'X IPA 1', text: 'X IPA 1' },
        { value: 'X IPA 2', text: 'X IPA 2' },
        { value: 'XI IPA 1', text: 'XI IPA 1' },
        { value: 'XI IPA 2', text: 'XI IPA 2' },
        { value: 'XII IPA 1', text: 'XII IPA 1' },
        { value: 'XII IPA 2', text: 'XII IPA 2' }
    ],
    'IPS': [
        { value: 'X IPS 1', text: 'X IPS 1' },
        { value: 'X IPS 2', text: 'X IPS 2' },
        { value: 'XI IPS 1', text: 'XI IPS 1' },
        { value: 'XI IPS 2', text: 'XI IPS 2' },
        { value: 'XII IPS 1', text: 'XII IPS 1' },
        { value: 'XII IPS 2', text: 'XII IPS 2' }
    ]
};

let currentStudents = [];
let existingAttendance = {};

// Initialize saat dokumen ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Sistem Absensi Dimulai ===');
    
    // Validasi data guru
    if (typeof currentGuruData === 'undefined') {
        console.error('❌ CRITICAL: Data guru tidak ditemukan!');
        alert('⚠️ Error: Data guru tidak ditemukan. Silakan refresh halaman atau login ulang.');
        return;
    }
    
    // ✅ VALIDASI BARU: Cek data jadwal guru
    if (typeof jadwalGuruData === 'undefined') {
        console.error('❌ CRITICAL: Data jadwal guru tidak ditemukan!');
        alert('⚠️ Error: Data jadwal tidak ditemukan. Silakan refresh halaman.');
        return;
    }
    
    console.log('✅ Data Guru berhasil dimuat:', currentGuruData);
    console.log('✅ Data Jadwal berhasil dimuat:', jadwalGuruData);
    console.log('📊 Total kelas yang diajar:', Object.keys(jadwalGuruData).length);
    
    // Set tanggal hari ini
    const tanggalInput = document.getElementById('tanggal');
    if (tanggalInput) {
        const today = new Date().toISOString().split('T')[0];
        tanggalInput.value = today;
        console.log('📅 Tanggal default:', today);
    }
    
    // Initialize dengan data default
    updateKelasOptions();
});

// Cek apakah absensi sudah ada untuk tanggal tertentu
async function checkExistingAttendance(kelas, tanggal) {
    console.log(`🔍 Mengecek absensi existing untuk ${kelas} tanggal ${tanggal}...`);
    
    try {
        const response = await fetch(`api/get_attendance.php?kelas=${encodeURIComponent(kelas)}&tanggal=${tanggal}`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            existingAttendance = {};
            result.data.forEach(attendance => {
                existingAttendance[attendance.student_id] = attendance.status;
            });
            console.log(`✅ Ditemukan ${result.data.length} data absensi existing`);
        } else {
            existingAttendance = {};
            console.log('ℹ️ Tidak ada data absensi existing');
        }
    } catch (error) {
        console.error('❌ Error checking existing attendance:', error);
        existingAttendance = {};
    }
}

// ✅ FUNGSI UTAMA YANG DIPERBAIKI: Update opsi kelas berdasarkan jurusan DAN jadwal mengajar
function updateKelasOptions() {
    const jurusan = document.getElementById('jurusan').value;
    const kelasSelect = document.getElementById('kelas');
    
    console.log('📚 Update kelas untuk jurusan:', jurusan);
    
    // Reset dropdown
    kelasSelect.innerHTML = '<option value="">Pilih Kelas</option>';
    
    if (jurusan && kelasData[jurusan]) {
        // ✅ FILTER: Hanya tampilkan kelas yang ada di jadwal guru
        const kelasYangDiajar = kelasData[jurusan].filter(kelas => {
            const key = jurusan + '|' + kelas.value;
            const isDiajar = jadwalGuruData[key] === true;
            
            // Debug log untuk setiap kelas
            if (isDiajar) {
                console.log(`  ✓ ${kelas.value} - DIAJAR (akan muncul di dropdown)`);
            } else {
                console.log(`  ✗ ${kelas.value} - TIDAK DIAJAR (disembunyikan)`);
            }
            
            return isDiajar;
        });
        
        // Jika ada kelas yang diajar
        if (kelasYangDiajar.length > 0) {
            kelasSelect.disabled = false;
            
            // Tambahkan hanya kelas yang diajar ke dropdown
            kelasYangDiajar.forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas.value;
                option.textContent = kelas.text;
                kelasSelect.appendChild(option);
            });
            
            console.log(`✅ ${kelasYangDiajar.length} kelas yang Anda ajar berhasil dimuat untuk ${jurusan}`);
            
        } else {
            // Jika tidak ada kelas yang diajar di jurusan ini
            kelasSelect.disabled = true;
            kelasSelect.innerHTML = '<option value="">Tidak ada kelas yang Anda ajar di jurusan ini</option>';
            
            console.log(`⚠️ Tidak ada kelas yang diajar di jurusan ${jurusan}`);
            
            // Tampilkan pesan info ke user
            document.getElementById('studentsList').innerHTML = `
                <div class="table-container">
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher" style="color: #f39c12; font-size: 3rem; margin-bottom: 16px;"></i>
                        <h3>Tidak Ada Kelas</h3>
                        <p>Anda tidak memiliki jadwal mengajar di jurusan ${jurusan}</p>
                        <small style="color: #666; margin-top: 10px; display: block;">
                            Silakan pilih jurusan lain atau hubungi admin untuk menambahkan jadwal mengajar
                        </small>
                    </div>
                </div>
            `;
        }
    } else {
        kelasSelect.disabled = true;
        console.log('ℹ️ Kelas select disabled - pilih jurusan terlebih dahulu');
        document.getElementById('studentsList').innerHTML = '';
    }
    
    // Disable save button
    document.getElementById('saveBtn').disabled = true;
}

// Load data siswa dari database
async function loadStudents() {
    const jurusan = document.getElementById('jurusan').value;
    const kelas = document.getElementById('kelas').value;
    const tanggal = document.getElementById('tanggal').value;
    
    console.log('=== Memulai Load Students ===');
    console.log('Jurusan:', jurusan);
    console.log('Kelas:', kelas);
    console.log('Tanggal:', tanggal);
    
    if (!jurusan || !kelas || !tanggal) {
        alert('⚠️ Harap lengkapi semua field terlebih dahulu!');
        console.warn('⚠️ Ada field yang kosong');
        return;
    }

    // ✅ VALIDASI TAMBAHAN: Cek apakah guru benar-benar mengajar kelas ini
    const kelasKey = jurusan + '|' + kelas;
    if (!jadwalGuruData[kelasKey]) {
        alert('⚠️ Error: Anda tidak memiliki jadwal mengajar untuk kelas ' + kelas + ' di jurusan ' + jurusan);
        console.error('❌ Akses ditolak: Guru tidak mengajar kelas ini');
        return;
    }
    
    console.log('✅ Validasi jadwal passed - Guru mengajar kelas ini');

    // Show loading dengan styling konsisten
    document.getElementById('studentsList').innerHTML = `
        <div class="table-container">
            <div class="empty-state">
                <i class="fas fa-spinner loading-spinner"></i>
                <h3>Memuat data siswa...</h3>
                <p>Mohon tunggu sebentar</p>
            </div>
        </div>
    `;

    try {
        // Fetch data from database
        const url = `api/get_students.php?jurusan=${encodeURIComponent(jurusan)}&kelas=${encodeURIComponent(kelas)}`;
        console.log('🌐 Fetching:', url);
        
        const response = await fetch(url);
        const result = await response.json();
        
        console.log('📥 Response:', result);

        if (result.success) {
            currentStudents = result.data;
            console.log(`✅ ${currentStudents.length} siswa berhasil dimuat`);
            
            if (currentStudents.length === 0) {
                document.getElementById('studentsList').innerHTML = `
                    <div class="table-container">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>Tidak ada siswa ditemukan</h3>
                            <p>Tidak ada siswa untuk kelas ${kelas} jurusan ${jurusan}</p>
                        </div>
                    </div>
                `;
                return;
            }

            // Check if attendance already exists for this date
            await checkExistingAttendance(kelas, tanggal);
            
            renderStudentsTable();
            document.getElementById('saveBtn').disabled = false;
            console.log('✅ Tabel siswa berhasil dirender');
            
        } else {
            throw new Error(result.message || 'Gagal memuat data siswa');
        }
        
    } catch (error) {
        console.error('❌ Error loading students:', error);
        document.getElementById('studentsList').innerHTML = `
            <div class="table-container">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error memuat data</h3>
                    <p>${error.message}</p>
                    <small style="margin-top: 10px; color: #666;">
                        Pastikan server PHP berjalan dan konfigurasi database sudah benar
                    </small>
                </div>
            </div>
        `;
    }
}

// Render tabel siswa dengan form absensi
function renderStudentsTable() {
    const jurusan = document.getElementById('jurusan').value;
    const kelas = document.getElementById('kelas').value;
    const tanggal = document.getElementById('tanggal').value;

    console.log('🎨 Rendering tabel untuk', currentStudents.length, 'siswa');

    let html = `
        <div class="data-info">
            <i class="fas fa-clipboard-list"></i>
            Absensi Kelas <strong>${kelas} - ${jurusan}</strong> | 
            Tanggal: <strong>${formatDate(tanggal)}</strong> | 
            Total Siswa: <strong>${currentStudents.length}</strong> |
            Mata Pelajaran: <strong>${currentGuruData.mata_pelajaran}</strong>
        </div>
    `;

    if (Object.keys(existingAttendance).length > 0) {
        html += `
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Perhatian:</strong> Data absensi untuk tanggal ini sudah ada dan akan diperbarui jika Anda menyimpan kembali.
            </div>
        `;
    }

    html += `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
    `;

    currentStudents.forEach((student, index) => {
        const existingStatus = existingAttendance[student.id] || '';
        
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${student.nis}</td>
                <td>${student.nama}</td>
                <td>${student.kelas}</td>
                <td>
                    <select class="status-select ${existingStatus ? 'status-' + existingStatus : ''}" 
                            id="status_${student.id}" 
                            onchange="updateStatusStyle(this)"
                            data-student-id="${student.id}"
                            data-student-name="${student.nama}">
                        <option value="">Pilih Status</option>
                        <option value="hadir" ${existingStatus === 'hadir' ? 'selected' : ''}>Hadir</option>
                        <option value="alpha" ${existingStatus === 'alpha' ? 'selected' : ''}>Alpha</option>
                        <option value="sakit" ${existingStatus === 'sakit' ? 'selected' : ''}>Sakit</option>
                        <option value="izin" ${existingStatus === 'izin' ? 'selected' : ''}>Izin</option>
                    </select>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        
        <div class="summary" id="summary">
            <div class="summary-card summary-hadir">
                <h4><i class="fas fa-check-circle"></i> Hadir</h4>
                <div class="count-number" id="count-hadir">0</div>
            </div>
            <div class="summary-card summary-alpha">
                <h4><i class="fas fa-times-circle"></i> Alpha</h4>
                <div class="count-number" id="count-alpha">0</div>
            </div>
            <div class="summary-card summary-sakit">
                <h4><i class="fas fa-heartbeat"></i> Sakit</h4>
                <div class="count-number" id="count-sakit">0</div>
            </div>
            <div class="summary-card summary-izin">
                <h4><i class="fas fa-hand-paper"></i> Izin</h4>
                <div class="count-number" id="count-izin">0</div>
            </div>
        </div>
    `;

    document.getElementById('studentsList').innerHTML = html;
    updateSummary();
}

// Update styling status select saat diubah
function updateStatusStyle(selectElement) {
    const status = selectElement.value;
    const studentName = selectElement.getAttribute('data-student-name');
    
    console.log(`📝 Status ${studentName}: ${status || 'belum dipilih'}`);
    
    selectElement.className = 'status-select';
    
    if (status) {
        selectElement.classList.add(`status-${status}`);
    }
    
    updateSummary();
}

// Update ringkasan jumlah per status
function updateSummary() {
    const counts = { hadir: 0, alpha: 0, sakit: 0, izin: 0 };
    
    currentStudents.forEach(student => {
        const statusSelect = document.getElementById(`status_${student.id}`);
        if (statusSelect && statusSelect.value) {
            counts[statusSelect.value]++;
        }
    });
    
    Object.keys(counts).forEach(status => {
        const element = document.getElementById(`count-${status}`);
        if (element) {
            element.textContent = counts[status];
        }
    });
    
    console.log('📊 Summary updated:', counts);
}

// Simpan data absensi ke database
async function saveAttendance() {
    console.log('=== Memulai Proses Simpan Absensi ===');
    
    const jurusan = document.getElementById('jurusan').value;
    const kelas = document.getElementById('kelas').value;
    const tanggal = document.getElementById('tanggal').value;
    
    // Validasi data guru
    if (typeof currentGuruData === 'undefined' || !currentGuruData.mata_pelajaran) {
        console.error('❌ CRITICAL: Data guru tidak lengkap!', currentGuruData);
        alert('❌ Error: Data guru tidak ditemukan atau tidak lengkap. Silakan refresh halaman atau login ulang.');
        return;
    }
    
    // ✅ VALIDASI TAMBAHAN: Cek apakah guru benar-benar mengajar kelas ini
    const kelasKey = jurusan + '|' + kelas;
    if (!jadwalGuruData[kelasKey]) {
        alert('❌ Error: Anda tidak memiliki jadwal mengajar untuk kelas ' + kelas + ' di jurusan ' + jurusan);
        console.error('❌ Akses ditolak saat save: Guru tidak mengajar kelas ini');
        return;
    }
    
    console.log('✅ Validasi data guru OK');
    console.log('✅ Validasi jadwal OK');
    console.log('👨‍🏫 Guru:', currentGuruData.nama);
    console.log('📚 Mata Pelajaran:', currentGuruData.mata_pelajaran);
    console.log('🏫 Kelas:', kelas, '-', jurusan);
    
    const attendanceData = [];
    let hasEmptyStatus = false;
    let emptyStudents = [];
    
    // Collect attendance data
    currentStudents.forEach(student => {
        const statusSelect = document.getElementById(`status_${student.id}`);
        const status = statusSelect.value;
        
        if (!status) {
            hasEmptyStatus = true;
            emptyStudents.push(student.nama);
            return;
        }
        
        // PENTING: Tambahkan mata_pelajaran dari data guru
        attendanceData.push({
            student_id: student.id,
            nis: student.nis,
            nama: student.nama,
            kelas: kelas,
            jurusan: jurusan,
            status: status,
            tanggal: tanggal,
            mata_pelajaran: currentGuruData.mata_pelajaran // KUNCI UTAMA!
        });
    });
    
    console.log(`📝 Data dikumpulkan: ${attendanceData.length} dari ${currentStudents.length} siswa`);
    
    if (hasEmptyStatus) {
        console.warn('⚠️ Ada siswa yang belum diatur statusnya:', emptyStudents);
        alert(`⚠️ Masih ada ${emptyStudents.length} siswa yang belum diatur status kehadirannya!\n\nSiswa:\n${emptyStudents.slice(0, 5).join('\n')}${emptyStudents.length > 5 ? '\n...' : ''}`);
        return;
    }
    
    // Validasi final sebelum kirim
    const invalidData = attendanceData.filter(item => !item.mata_pelajaran);
    if (invalidData.length > 0) {
        console.error('❌ Ada data tanpa mata_pelajaran:', invalidData);
        alert('❌ Error: Ada data yang tidak lengkap. Silakan refresh halaman.');
        return;
    }
    
    console.log('✅ Semua validasi passed');
    console.log('📤 Data yang akan dikirim:', attendanceData);
    
    // Show saving state
    const saveBtn = document.getElementById('saveBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    saveBtn.disabled = true;
    
    try {
        console.log('🌐 Mengirim request ke server...');
        
        const response = await fetch('api/save_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                attendance_data: attendanceData
            })
        });
        
        console.log('📥 Response status:', response.status);
        
        const result = await response.json();
        console.log('📥 Response data:', result);
        
        if (result.success) {
            console.log('✅ Absensi berhasil disimpan!');
            console.log(`✅ Total tersimpan: ${result.total_saved}`);
            
            if (result.errors && result.errors.length > 0) {
                console.warn('⚠️ Ada beberapa error:', result.errors);
            }
            
            // Calculate summary
            const summary = attendanceData.reduce((acc, curr) => {
                acc[curr.status] = (acc[curr.status] || 0) + 1;
                return acc;
            }, {});
            
            const summaryText = Object.entries(summary)
                .map(([status, count]) => {
                    const statusLabels = {
                        'hadir': 'Hadir',
                        'alpha': 'Alpha',
                        'sakit': 'Sakit',
                        'izin': 'Izin'
                    };
                    return `${statusLabels[status]}: ${count}`;
                }).join('\n');
            
            alert(`✅ Absensi berhasil disimpan ke database!\n\n📊 Ringkasan:\n${summaryText}\n\n📅 Tanggal: ${formatDate(tanggal)}\n🏫 Kelas: ${kelas} - ${jurusan}\n👨‍🏫 Guru: ${currentGuruData.nama}\n📚 Mata Pelajaran: ${currentGuruData.mata_pelajaran}\n\n💾 Total tersimpan: ${result.total_saved} data`);
            
            // Reload data untuk melihat perubahan
            console.log('🔄 Reloading data...');
            await loadStudents();
            
        } else {
            throw new Error(result.message || 'Gagal menyimpan absensi');
        }
        
    } catch (error) {
        console.error('❌ Error saving attendance:', error);
        alert(`❌ Error menyimpan absensi:\n${error.message}\n\nSilakan cek console browser (F12) untuk detail error.`);
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        console.log('=== Proses Simpan Selesai ===');
    }
}

// Format tanggal ke bahasa Indonesia
function formatDate(dateString) {
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('id-ID', options);
}

// Debug helper - panggil di console untuk cek status
function debugStatus() {
    console.log('=== Debug Status ===');
    console.log('Current Guru Data:', currentGuruData);
    console.log('Jadwal Guru Data:', jadwalGuruData);
    console.log('Current Students:', currentStudents);
    console.log('Existing Attendance:', existingAttendance);
    
    const statusCounts = {};
    currentStudents.forEach(student => {
        const select = document.getElementById(`status_${student.id}`);
        if (select) {
            const status = select.value || 'belum_dipilih';
            statusCounts[status] = (statusCounts[status] || 0) + 1;
        }
    });
    console.log('Status Distribution:', statusCounts);
    console.log('===================');
}

// Export untuk debugging (opsional)
window.debugAbsensi = debugStatus;

console.log('✅ absensi_handler.js (WITH FILTER) loaded successfully');