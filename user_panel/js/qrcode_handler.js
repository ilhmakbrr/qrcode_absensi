// Global variables
let currentJadwalId = null;
let qrRefreshInterval = null;
let countdownInterval = null;
let modalInstance = null;

// Update waktu real-time
setInterval(() => {
    const now = new Date();
    document.getElementById('current-time').textContent = now.toLocaleTimeString('id-ID', {hour12: false});
}, 1000);

// Check status setiap 30 detik
setInterval(() => {
    document.querySelectorAll('.schedule-card').forEach(card => {
        const jadwalId = card.getAttribute('data-jadwal-id');
        if (jadwalId) {
            checkScheduleStatus(jadwalId);
        }
    });
}, 30000);

// Check schedule status
function checkScheduleStatus(jadwalId) {
    const formData = new FormData();
    formData.append('action', 'check_status');
    formData.append('jadwal_id', jadwalId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Cek apakah response OK
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        // Cek content-type
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("Response bukan JSON! Mungkin ada error di PHP.");
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`[data-jadwal-id="${jadwalId}"]`);
            if (!card) return;
            
            const statusBadge = card.querySelector('.status-badge');
            const button = card.querySelector('.btn-generate');
            
            // Update card style
            card.classList.remove('active', 'today');
            if (data.is_qr_active) {
                card.classList.add('active');
            } else if (data.is_today) {
                card.classList.add('today');
            }
            
            // Update status badge
            statusBadge.className = 'status-badge ' + (data.is_qr_active ? 'active' : (data.is_today ? 'today' : 'inactive'));
            if (data.is_qr_active) {
                statusBadge.innerHTML = '<i class="fas fa-check-circle"></i> AKTIF SEKARANG';
            } else if (data.is_today) {
                statusBadge.innerHTML = '<i class="fas fa-clock"></i> Siap Generate';
            } else {
                statusBadge.innerHTML = '<i class="fas fa-calendar"></i> Preview';
            }
            
            // Update button
            button.className = 'btn-generate ' + (data.is_qr_active ? 'active' : (data.is_today ? 'today' : 'inactive'));
            button.innerHTML = '<i class="fas fa-qrcode"></i> ' + (data.is_qr_active ? 'Lihat QR Code' : (data.is_today ? 'Generate QR' : 'Preview QR'));
        }
    })
    .catch(error => {
        console.error('Error checking status:', error);
        // Jangan tampilkan alert untuk error otomatis
    });
}

// Generate QR Code
function generateQR(jadwalId) {
    currentJadwalId = jadwalId;
    
    const formData = new FormData();
    formData.append('action', 'generate_qr');
    formData.append('jadwal_id', jadwalId);
    
    // Show loading
    const loadingAlert = document.createElement('div');
    loadingAlert.className = 'alert alert-info';
    loadingAlert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating QR Code...';
    loadingAlert.style.position = 'fixed';
    loadingAlert.style.top = '20px';
    loadingAlert.style.right = '20px';
    loadingAlert.style.zIndex = '9999';
    loadingAlert.id = 'loading-alert';
    document.body.appendChild(loadingAlert);
    
    fetch('', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        // Remove loading
        const loading = document.getElementById('loading-alert');
        if (loading) loading.remove();
        
        // Cek apakah response OK
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        // Cek content-type
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            // Log response text untuk debugging
            return response.text().then(text => {
                console.error('Response bukan JSON:', text);
                throw new Error("Server mengembalikan HTML/Text, bukan JSON. Cek error di PHP!");
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayQRCode(data);
            
            // Setup auto refresh QR setiap 30 detik jika aktif
            if (data.is_qr_active) {
                setupAutoRefresh(jadwalId);
            }
        } else {
            alert(data.message || 'Gagal generate QR Code');
        }
    })
    .catch(error => {
        console.error('Error generating QR:', error);
        alert('Error: ' + error.message + '\n\nSilakan cek console browser (F12) untuk detail.');
    });
}

// Setup auto refresh QR
function setupAutoRefresh(jadwalId) {
    // Clear existing intervals
    if (qrRefreshInterval) clearInterval(qrRefreshInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    
    // Refresh QR setiap 30 detik
    qrRefreshInterval = setInterval(() => {
        if (modalInstance && modalInstance._isShown) {
            refreshQR(jadwalId);
        } else {
            clearInterval(qrRefreshInterval);
            clearInterval(countdownInterval);
        }
    }, 30000); // 30000 ms = 30 detik
    
    // Start countdown dari 30 detik
    startCountdown(30);
}

// Start countdown timer
function startCountdown(seconds) {
    let remainingSeconds = seconds;
    const countdownElement = document.getElementById('countdown');
    const timerElement = document.getElementById('countdownTimer');
    
    if (!countdownElement || !timerElement) return;
    
    if (countdownInterval) clearInterval(countdownInterval);
    
    // Update countdown setiap 1 detik
    countdownInterval = setInterval(() => {
        remainingSeconds--;
        
        // Format waktu
        const minutes = Math.floor(remainingSeconds / 60);
        const secs = remainingSeconds % 60;
        countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
        
        // Warning di 5 detik terakhir
        if (remainingSeconds <= 5) {
            timerElement.classList.add('warning');
        } else {
            timerElement.classList.remove('warning');
        }
        
        // Reset countdown saat habis
        if (remainingSeconds <= 0) {
            remainingSeconds = 30; // Reset ke 30 detik
            timerElement.classList.remove('warning');
        }
    }, 1000);
}

// Refresh QR Code
function refreshQR(jadwalId) {
    const formData = new FormData();
    formData.append('action', 'generate_qr');
    formData.append('jadwal_id', jadwalId);
    
    fetch('', {
        method: 'POST',
        body: formData,
        headers: {
            'Cache-Control': 'no-cache',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("Response bukan JSON");
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success && data.is_qr_active) {
            displayQRCode(data, false);
            startCountdown(30); // Reset ke 30 detik setelah refresh
        } else {
            // Jika tidak aktif lagi, tutup modal
            if (modalInstance) {
                modalInstance.hide();
            }
            clearInterval(qrRefreshInterval);
            clearInterval(countdownInterval);
        }
    })
    .catch(error => {
        console.error('Error refreshing QR:', error);
        // Jangan alert saat auto-refresh error
    });
}

// Display QR Code in Modal
function displayQRCode(data, showModal = true) {
    // Clear previous QR
    const qrcodeDiv = document.getElementById('qrcode');
    if (!qrcodeDiv) {
        console.error('QR Code container not found!');
        return;
    }
    
    qrcodeDiv.innerHTML = '';
    
    try {
        // Generate QR Code
        const qr = qrcode(0, 'M');
        qr.addData(data.qr_string);
        qr.make();
        
        const qrSize = 256;
        const qrImage = qr.createDataURL(8, 0);
        
        const img = document.createElement('img');
        img.src = qrImage;
        img.style.width = qrSize + 'px';
        img.style.height = qrSize + 'px';
        img.style.border = '2px solid #e2e8f0';
        img.style.borderRadius = '8px';
        img.id = 'qr-image';
        
        qrcodeDiv.appendChild(img);
    } catch (error) {
        console.error('Error generating QR image:', error);
        qrcodeDiv.innerHTML = '<div class="alert alert-danger">Error generating QR Code</div>';
        return;
    }
    
    // Display info
    const info = data.qr_display;
    const isActive = data.is_qr_active;
    
    let statusHtml = '';
    if (isActive) {
        statusHtml = `<div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; text-align: center;">
            <i class="fas fa-check-circle"></i> QR CODE AKTIF ${info.time_slot}
        </div>`;
    } else {
        statusHtml = '<div style="background: #fef3c7; color: #92400e; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; text-align: center;"><i class="fas fa-exclamation-triangle"></i> PREVIEW MODE</div>';
    }
    
    const jadwalInfoDiv = document.getElementById('jadwalInfo');
    if (jadwalInfoDiv) {
        jadwalInfoDiv.innerHTML = `
            ${statusHtml}
            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px;">
                <h6 style="color: #4f46e5; margin-bottom: 1rem; font-weight: 700;">Informasi Jadwal</h6>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">Mata Pelajaran</small>
                    <div><strong>${info.mata_pelajaran}</strong></div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">Kelas / Jurusan</small>
                    <div><strong>${info.kelas} - ${info.jurusan}</strong></div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">Hari / Jam</small>
                    <div><strong>${info.hari}, ${info.jam}</strong></div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">Tanggal</small>
                    <div><strong>${info.tanggal}</strong></div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">Guru</small>
                    <div><strong>${info.nama_guru}</strong></div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <small class="text-muted">NIP</small>
                    <div><strong>${info.nip}</strong></div>
                </div>
                ${isActive ? `
                <div style="background: #e0f2fe; padding: 0.75rem; border-radius: 8px; margin-top: 1rem;">
                    <small class="text-muted">Waktu Valid</small>
                    <div><strong>${data.valid_from} - ${data.valid_until}</strong></div>
                </div>
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 8px; margin-top: 0.5rem; text-align: center;">
                    <small style="color: #92400e;"><i class="fas fa-sync-alt"></i> QR otomatis berubah setiap 30 Detik</small>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    // Set copy text
    const copyTextArea = document.getElementById('copyText');
    if (copyTextArea) {
        copyTextArea.value = data.qr_string;
    }
    
    // Setup download button
    const downloadBtn = document.getElementById('downloadQR');
    if (downloadBtn) {
        downloadBtn.onclick = function() {
            downloadQRCode(info.mata_pelajaran, info.kelas, info.tanggal, info.time_slot || 'preview');
        };
    }
    
    // Show modal only if requested
    if (showModal) {
        const modalElement = document.getElementById('qrModal');
        if (!modalElement) {
            console.error('Modal element not found!');
            return;
        }
        
        modalInstance = new bootstrap.Modal(modalElement);
        
        // Clear intervals when modal is closed
        modalElement.addEventListener('hidden.bs.modal', function () {
            if (qrRefreshInterval) {
                clearInterval(qrRefreshInterval);
                qrRefreshInterval = null;
            }
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        });
        
        modalInstance.show();
        
        // Start countdown if active
        if (isActive && data.remaining_seconds) {
            startCountdown(Math.max(1, data.remaining_seconds)); // Minimal 1 detik
        }
    }
}

// Download QR Code
function downloadQRCode(mapel, kelas, tanggal, timeSlot) {
    const img = document.getElementById('qr-image');
    if (img) {
        const link = document.createElement('a');
        const filename = `QR_${mapel}_${kelas}_${tanggal}_${timeSlot}`.replace(/[^a-zA-Z0-9_-]/g, '_');
        link.download = `${filename}.png`;
        link.href = img.src;
        link.click();
    } else {
        alert('QR Code image not found');
    }
}

// Copy data to clipboard
function copyData(e) {
    const copyText = document.getElementById('copyText');
    
    if (!copyText || !copyText.value) {
        alert('Tidak ada data untuk disalin');
        return;
    }
    
    // Method 1: Modern Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(copyText.value).then(() => {
            showCopySuccess(e);
        }).catch(err => {
            console.error('Clipboard API failed:', err);
            // Fallback to old method
            fallbackCopy(copyText, e);
        });
    } else {
        // Fallback for older browsers
        fallbackCopy(copyText, e);
    }
}

// Fallback copy method for older browsers
function fallbackCopy(copyText, e) {
    try {
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices
        const successful = document.execCommand('copy');
        
        if (successful) {
            showCopySuccess(e);
        } else {
            alert('Gagal menyalin. Silakan copy manual.');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        alert('Gagal menyalin. Silakan copy manual.');
    }
}

// Show copy success feedback
function showCopySuccess(e) {
    let btn = null;
    
    // Try to get button from event
    if (e && e.target) {
        btn = e.target.closest('button');
    }
    
    // Fallback: find button by onclick attribute
    if (!btn) {
        btn = document.querySelector('button[onclick*="copyData"]');
    }
    
    if (btn) {
        const originalHtml = btn.innerHTML;
        const originalClass = btn.className;
        
        btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        btn.className = 'btn btn-success btn-sm w-100 mt-2';
        
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.className = originalClass;
        }, 2000);
    } else {
        // Show alert if button not found
        alert('Data berhasil disalin ke clipboard!');
    }
}

// Print QR
function printQR() {
    const qrImage = document.getElementById('qr-image');
    const jadwalInfo = document.getElementById('jadwalInfo');
    
    if (!qrImage || !jadwalInfo) {
        alert('QR Code tidak ditemukan');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    const info = jadwalInfo.innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print QR Code</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 20px; 
                    text-align: center;
                }
                img { 
                    max-width: 300px; 
                    margin: 20px auto;
                    border: 2px solid #ccc;
                }
                .info {
                    text-align: left;
                    max-width: 500px;
                    margin: 0 auto;
                }
            </style>
        </head>
        <body>
            <h2>QR Code Absensi</h2>
            <img src="${qrImage.src}" />
            <div class="info">${info}</div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}