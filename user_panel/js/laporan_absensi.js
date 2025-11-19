const kelasData = {
        'IPA': ['X IPA 1', 'X IPA 2', 'XI IPA 1', 'XI IPA 2', 'XII IPA 1', 'XII IPA 2'],
        'IPS': ['X IPS 1', 'X IPS 2', 'XI IPS 1', 'XI IPS 2', 'XII IPS 1', 'XII IPS 2']
    };

    function updateKelas() {
        const jurusan = document.querySelector('select[name="jurusan"]').value;
        const kelasSelect = document.getElementById('kelasSelect');
        const currentKelas = '<?= $kelas_filter ?>';
        
        kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
        
        if (jurusan && kelasData[jurusan]) {
            kelasData[jurusan].forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas;
                option.textContent = kelas;
                if (kelas === currentKelas) {
                    option.selected = true;
                }
                kelasSelect.appendChild(option);
            });
        }
    }

    // Update kelas saat halaman dimuat
    window.onload = function() {
        updateKelas();
        
        // Tampilkan footer saat print
        window.addEventListener('beforeprint', function() {
            document.querySelector('.print-footer').style.display = 'block';
        });
    };
