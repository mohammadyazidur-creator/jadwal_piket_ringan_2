<?php
require 'koneksi.php';


 $action = $_GET['action'] ?? '';

// Jumlah maksimal siswa yang bisa diinput
 $max_siswa_input = 50;

// Tangani aksi hapus
if ($action === 'delete') {
    $id = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM jadwal_piket WHERE id=$id");
    header('Location: jadwal.php');
    exit;
}

// Periksa kolom yang ada di database
 $existing_columns = [];
 $result_columns = mysqli_query($koneksi, "SHOW COLUMNS FROM jadwal_piket");
while ($row = mysqli_fetch_assoc($result_columns)) {
    $existing_columns[] = $row['Field'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hari = mysqli_real_escape_string($koneksi, $_POST['hari']);
    $jumlah_siswa = intval($_POST['jumlah_siswa']);
    
    // Siapkan array untuk menyimpan nama siswa
    $siswa_list = [];
    for ($i = 1; $i <= $jumlah_siswa; $i++) {
        $nama = mysqli_real_escape_string($koneksi, $_POST["nama_siswa$i"]);
        $siswa_list[] = $nama;
    }
    
    // Konversi ke JSON
    $siswa_json = json_encode($siswa_list);
    
    if ($_POST['mode'] === 'add') {
        // Periksa apakah kolom siswa_list ada, jika tidak tambahkan
        if (!in_array('siswa_list', $existing_columns)) {
            mysqli_query($koneksi, "ALTER TABLE jadwal_piket ADD COLUMN siswa_list TEXT NULL");
        }
        
        // Simpan data
        mysqli_query($koneksi, "INSERT INTO jadwal_piket (hari, siswa_list) VALUES ('$hari', '$siswa_json')");
        
    } elseif ($_POST['mode'] === 'edit') {
        $id = intval($_POST['id']);
        
        // Periksa apakah kolom siswa_list ada, jika tidak tambahkan
        if (!in_array('siswa_list', $existing_columns)) {
            mysqli_query($koneksi, "ALTER TABLE jadwal_piket ADD COLUMN siswa_list TEXT NULL");
        }
        
        // Update data
        mysqli_query($koneksi, "UPDATE jadwal_piket SET hari='$hari', siswa_list='$siswa_json' WHERE id=$id");
    }
    
    header('Location: jadwal.php');
    exit;
}

 $result = mysqli_query($koneksi, "SELECT * FROM jadwal_piket ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')");

// Periksa apakah kolom siswa_list ada, jika tidak tambahkan
if (!in_array('siswa_list', $existing_columns)) {
    mysqli_query($koneksi, "ALTER TABLE jadwal_piket ADD COLUMN siswa_list TEXT NULL");
}

// Simpan data jadwal ke session untuk digunakan di halaman cetak_jadwal.php
 $jadwalData = [];
while($row=mysqli_fetch_assoc($result)){
    $jadwalData[] = $row;
}
 $_SESSION['jadwal_piket_data'] = $jadwalData;

// Reset pointer result untuk digunakan lagi di bawah
mysqli_data_seek($result, 0);

// Ambil data untuk form edit jika diperlukan
 $edit_data = null;
if ($action === 'edit') {
    $id = intval($_GET['id']);
    $edit_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM jadwal_piket WHERE id=$id"));
}
$page_title = 'Jadwal';
require 'header.php';
?>
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h1 class="page-title">Manajemen Jadwal Piket</h1>
                    <div>
                        <a href="cetak_jadwal.php" class="btn btn-success me-2">
                            <i class="fas fa-print me-2"></i> Cetak Jadwal
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jadwalModal" onclick="resetForm()">
                            <i class="fas fa-plus me-2"></i> Tambah Jadwal
                        </button>
                    </div>
                </div>
            </div>

            <div class="view-toggle no-print">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="table-view-btn">
                        <i class="fas fa-table"></i> Tabel Data
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="grid-view-btn">
                        <i class="fas fa-th"></i> Tampilan siswa
                    </button>
                </div>
            </div>

            <div class="card table-view">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Hari</th>
                                    <th>Jumlah Siswa</th>
                                    <th class="no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $nomor=1; while($row=mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($row['hari']); ?></td>
                                        <td>
                                            <?php 
                                                $jumlah = 0;
                                                if (!empty($row['siswa_list'])) {
                                                    $siswa_list = json_decode($row['siswa_list'], true);
                                                    if (is_array($siswa_list)) {
                                                        $jumlah = count($siswa_list);
                                                    }
                                                }
                                                echo "<span class='badge bg-primary badge-siswa'><i class='fas fa-users'></i> " . $jumlah . " siswa</span>";
                                            ?>
                                        </td>
                                        <td class="no-print">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#jadwalModal" onclick="editJadwal(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a class="btn btn-sm btn-danger" href="jadwal.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Hapus data?')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid-view" id="grid-view">
                <div class="day-grid">
                    <?php 
                    // Reset pointer hasil
                    mysqli_data_seek($result, 0);
                    while($row=mysqli_fetch_assoc($result)): 
                    ?>
                        <div class="day-card">
                            <div class="day-title"><?php echo htmlspecialchars($row['hari']); ?></div>
                            <ul class="student-list">
                                <?php
                                    if (!empty($row['siswa_list'])) {
                                        $siswa_list = json_decode($row['siswa_list'], true);
                                        if (is_array($siswa_list)) {
                                            foreach ($siswa_list as $siswa) {
                                                if (!empty($siswa)) {
                                                    echo "<li>" . htmlspecialchars($siswa) . "</li>";
                                                }
                                            }
                                        }
                                    }
                                ?>
                            </ul>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

    <!-- Modal Tambah/Edit Jadwal -->
    <div class="modal fade" id="jadwalModal" tabindex="-1" aria-labelledby="jadwalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jadwalModalLabel">Tambah Jadwal Piket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="add" id="formMode">
                        <input type="hidden" name="id" value="" id="formId">
                        <input type="hidden" id="jumlah-siswa" name="jumlah_siswa" value="6">
                        
                        <div class="mb-3">
                            <label class="form-label">Hari</label>
                            <select name="hari" class="form-select" required id="formHari">
                                <?php
                                    $hari_hari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                                    foreach($hari_hari as $h){
                                        echo "<option value='$h'>$h</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Daftar Siswa Piket</label>
                            <div class="jumlah-info" id="jumlah-info">
                                Jumlah siswa: 6
                            </div>
                            <div class="mb-2">
                                <button type="button" id="add-siswa" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus me-1"></i> Tambah Siswa
                                </button>
                                <button type="button" id="remove-siswa" class="btn btn-sm btn-danger">
                                    <i class="fas fa-minus me-1"></i> Kurangi Siswa
                                </button>
                            </div>
                            <div id="siswa-container" class="row">
                                <!-- Input siswa akan ditambahkan di sini dengan JavaScript -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Alihkan antara tampilan tabel dan grid
        const tableViewBtn = document.getElementById('table-view-btn');
        const gridViewBtn = document.getElementById('grid-view-btn');
        const tableView = document.querySelector('.table-view');
        const gridView = document.getElementById('grid-view');
        
        tableViewBtn.addEventListener('click', function() {
            tableViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            tableView.style.display = 'block';
            gridView.classList.remove('active');
            gridView.style.display = 'none';
        });
        
        gridViewBtn.addEventListener('click', function() {
            gridViewBtn.classList.add('active');
            tableViewBtn.classList.remove('active');
            gridView.classList.add('active');
            gridView.style.display = 'block';
            tableView.style.display = 'none';
        });
        
        // Fungsi untuk form tambah/edit jadwal
        const container = document.getElementById('siswa-container');
        const addButton = document.getElementById('add-siswa');
        const removeButton = document.getElementById('remove-siswa');
        const jumlahSiswaInput = document.getElementById('jumlah-siswa');
        const jumlahInfo = document.getElementById('jumlah-info');
        
        let jumlahSiswa = 6;
        const maxSiswaInput = <?php echo $max_siswa_input; ?>;
        
        // Fungsi untuk memperbarui tampilan kolom siswa
        function updateSiswaFields() {
            container.innerHTML = '';
            
            for (let i = 1; i <= jumlahSiswa; i++) {
                const div = document.createElement('div');
                div.className = 'col-md-6 mb-2';
                
                div.innerHTML = `
                    <label class="form-label">Nama Siswa ${i}</label>
                    <input type="text" class="form-control" name="nama_siswa${i}" value="" placeholder="Nama Siswa ${i}">
                `;
                container.appendChild(div);
            }
            
            // Perbarui input tersembunyi dan info
            jumlahSiswaInput.value = jumlahSiswa;
            jumlahInfo.textContent = `Jumlah siswa: ${jumlahSiswa}`;
        }
        
        // Event listener untuk tombol tambah
        addButton.addEventListener('click', function() {
            if (jumlahSiswa < maxSiswaInput) {
                jumlahSiswa++;
                updateSiswaFields();
            } else {
                alert(`Jumlah maksimal siswa adalah ${maxSiswaInput}`);
            }
        });
        
        // Event listener untuk tombol hapus
        removeButton.addEventListener('click', function() {
            if (jumlahSiswa > 1) {
                jumlahSiswa--;
                updateSiswaFields();
            } else {
                alert('Minimal harus ada 1 siswa');
            }
        });
        
        // Inisialisasi tampilan
        updateSiswaFields();
    });
    
    // Fungsi untuk reset form (mode tambah)
    function resetForm() {
        document.getElementById('formMode').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formHari').value = 'Senin';
        
        // Reset jumlah siswa ke default
        jumlahSiswa = 6;
        updateSiswaFields();
        
        // Update modal title
        document.getElementById('jadwalModalLabel').textContent = 'Tambah Jadwal Piket';
    }
    
    // Fungsi untuk edit jadwal
    function editJadwal(id) {
        // Fetch data jadwal via AJAX
        fetch(`get_jadwal_data.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                // Set form values
                document.getElementById('formMode').value = 'edit';
                document.getElementById('formId').value = data.id;
                document.getElementById('formHari').value = data.hari;
                
                // Parse siswa list
                let siswaList = [];
                if (data.siswa_list) {
                    siswaList = JSON.parse(data.siswa_list);
                }
                
                // Set jumlah siswa
                jumlahSiswa = Math.max(siswaList.length, 6);
                updateSiswaFields();
                
                // Set nilai untuk setiap input siswa
                for (let i = 0; i < siswaList.length; i++) {
                    const input = document.querySelector(`input[name="nama_siswa${i+1}"]`);
                    if (input) {
                        input.value = siswaList[i];
                    }
                }
                
                // Update modal title
                document.getElementById('jadwalModalLabel').textContent = 'Edit Jadwal Piket';
            })
            .catch(error => console.error('Error:', error));
    }
    </script>
 