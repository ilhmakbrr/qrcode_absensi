 // Kelas options berdasarkan jurusan
        const kelasOptions = {
            'IPA': ['X IPA 1', 'X IPA 2', 'XI IPA 1', 'XI IPA 2', 'XII IPA 1', 'XII IPA 2'],
            'IPS': ['X IPS 1', 'X IPS 2', 'XI IPS 1', 'XI IPS 2', 'XII IPS 1', 'XII IPS 2']
        };

        let deleteId = 0;

        // Update kelas dropdown berdasarkan jurusan
        function updateKelasOptions() {
            const jurusan = document.getElementById('jurusan').value;
            const kelasSelect = document.getElementById('kelas');
            
            kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
            
            if (jurusan && kelasOptions[jurusan]) {
                kelasOptions[jurusan].forEach(kelas => {
                    const option = document.createElement('option');
                    option.value = kelas;
                    option.text = kelas;
                    kelasSelect.appendChild(option);
                });
            }
        }

        // Submit form jadwal
        document.getElementById('jadwalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const editId = document.getElementById('editId').value;
            const formData = new FormData();
            formData.append('action', editId ? 'edit' : 'tambah');
            if (editId) formData.append('id', editId);
            formData.append('hari', document.getElementById('hari').value);
            formData.append('jam_mulai', document.getElementById('jamMulai').value);
            formData.append('jam_selesai', document.getElementById('jamSelesai').value);
            formData.append('jurusan', document.getElementById('jurusan').value);
            formData.append('kelas', document.getElementById('kelas').value);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem');
            });
        });

        // Edit jadwal
        function editJadwal(id) {
            const formData = new FormData();
            formData.append('action', 'get_jadwal');
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const jadwal = data.data;
                    
                    document.getElementById('editId').value = jadwal.id;
                    document.getElementById('hari').value = jadwal.hari;
                    document.getElementById('jamMulai').value = jadwal.jam_mulai;
                    document.getElementById('jamSelesai').value = jadwal.jam_selesai;
                    document.getElementById('jurusan').value = jadwal.jurusan;
                    updateKelasOptions();
                    setTimeout(() => {
                        document.getElementById('kelas').value = jadwal.kelas;
                    }, 100);

                    document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Jadwal Mengajar';
                    document.getElementById('btnText').textContent = 'Update Jadwal';
                    
                    // 🔥 Tampilkan modal edit
                    const jadwalModal = new bootstrap.Modal(document.getElementById('jadwalModal'));
                    jadwalModal.show();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem');
            });
        }


        // Cancel edit
        function cancelEdit() {
            document.getElementById('jadwalForm').reset();
            document.getElementById('editId').value = '';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Jadwal Mengajar Baru';
            document.getElementById('btnText').textContent = 'Simpan Jadwal';
            document.getElementById('btnCancel').style.display = 'none';
            updateKelasOptions();
        }

        // Hapus jadwal
        function hapusJadwal(id, info) {
            deleteId = id;
            document.getElementById('deleteInfo').innerHTML = '<strong>Jadwal:</strong> ' + info;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Confirm delete
        document.getElementById('confirmDelete').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'hapus');
            formData.append('id', deleteId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                modal.hide();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem');
            });
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateKelasOptions();
        });