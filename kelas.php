<?php
// Aktifkan semua pelaporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'koneksi.php'; // Cek apakah file ini ada dan tidak error

// --- HANDLER AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'get_siswa') {
    // Set header untuk JSON
    header('Content-Type: application/json');

    // Cek apakah koneksi benar-benar ada
    if (!isset($koneksi) || !$koneksi) {
        http_response_code(500);
        die(json_encode(['error' => 'Koneksi database gagal atau variabel $koneksi tidak ada. Periksa file koneksi.php']));
    }
    
    $kelas_id = isset($_GET['kelas_id']) ? intval($_GET['kelas_id']) : 0;
    if ($kelas_id === 0) {
        http_response_code(400); // Bad Request
        die(json_encode(['error' => 'Parameter kelas_id tidak valid.']));
    }

    // Query yang sangat sederhana untuk mengurangi kemungkinan error
    $query = "SELECT id, nama, nis FROM siswa WHERE kelas_id = $kelas_id ORDER BY nama ASC";
    
    $siswa_result = mysqli_query($koneksi, $query);

    if (!$siswa_result) {
        http_response_code(500);
        die(json_encode(['error' => 'Query SQL gagal: ' . mysqli_error($koneksi), 'query' => $query]));
    }

    $siswa_list = [];
    while ($s = mysqli_fetch_assoc($siswa_result)) {
        $siswa_list[] = [
            'id'   => $s['id'],
            'nama' => htmlspecialchars($s['nama']),
            'nis'  => htmlspecialchars($s['nis'])
        ];
    }
    
    // Menghentikan script dengan benar
    die(json_encode($siswa_list));
}

// --- HANDLER FORM ---
 $action = $_GET['action'] ?? '';

// Proses form submit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    
    if ($_POST['mode'] === 'add') {
        mysqli_query($koneksi, "INSERT INTO kelas (nama) VALUES ('$nama')");
    } elseif ($_POST['mode'] === 'edit') {
        $id = intval($_POST['id']);
        mysqli_query($koneksi, "UPDATE kelas SET nama='$nama' WHERE id=$id");
    }
    
    // Redirect setelah proses
    header('Location: kelas.php');
    exit;
}

// Proses delete
if ($action === 'delete') {
    $id = intval($_GET['id']);
    mysqli_query($koneksi, "DELETE FROM kelas WHERE id=$id");
    
    // Redirect setelah proses
    header('Location: kelas.php');
    exit;
}

// Ambil data kelas untuk ditampilkan
 $result = mysqli_query($koneksi, "
    SELECT k.*, 
           COUNT(s.id) as jumlah_siswa 
    FROM kelas k 
    LEFT JOIN siswa s ON k.id = s.kelas_id
    GROUP BY k.id 
    ORDER BY k.id DESC
");

// Siapkan data untuk form edit
 $row = ['id'=>'','nama'=>''];
if ($action === 'edit') {
    $id = intval($_GET['id']);
    $r = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM kelas WHERE id=$id"));
    if ($r) $row = $r;
}
$page_title = 'Kelas';
require 'header.php';
?>
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h1 class="page-title">Manajemen Kelas</h1>
                    <a href="kelas.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Tambah Kelas
                    </a>
                </div>
            </div>

            <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="mode" value="<?php echo $action === 'add' ? 'add' : 'edit'; ?>">
                        <?php if ($action === 'edit'): ?><input type="hidden" name="id"
                            value="<?php echo $row['id']; ?>"><?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Nama Kelas</label>
                            <input class="form-control" name="nama"
                                value="<?php echo htmlspecialchars($row['nama']); ?>" required>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-success">
                                <i class="fas fa-save me-2"></i> Simpan
                            </button>
                            <a href="kelas.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Kelas</th>
                                    <th>Jumlah Siswa</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; while($row=mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td>
                                        <span class="badge bg-primary badge-siswa">
                                            <i class="fas fa-users"></i> <?php echo $row['jumlah_siswa']; ?> siswa
                                        </span>
                                        <?php if ($row['jumlah_siswa'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-info ms-2" data-bs-toggle="modal"
                                            data-bs-target="#siswaModal"
                                            onclick="tampilkanSiswa(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama']); ?>')">
                                            <i class="fas fa-eye"></i> Lihat
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a class="btn btn-sm btn-warning"
                                                href="kelas.php?action=edit&id=<?php echo $row['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-danger"
                                                href="kelas.php?action=delete&id=<?php echo $row['id']; ?>"
                                                onclick="return confirm('Hapus data?')">
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

            <!-- Modal untuk menampilkan daftar siswa -->
            <div class="modal fade" id="siswaModal" tabindex="-1" aria-labelledby="siswaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="siswaModalLabel">Daftar Siswa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="siswa-list-container">
                                <div class="spinner-container">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i> Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

    <script>
    function tampilkanSiswa(kelasId, namaKelas) {
        document.getElementById('siswaModalLabel').innerText = 'Daftar Siswa Kelas ' + namaKelas;

        document.getElementById('siswa-list-container').innerHTML = `
            <div class="spinner-container">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        fetch(`kelas.php?action=get_siswa&kelas_id=${kelasId}`, {
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(
                            `Server error: ${response.status}. Response: ${text.substring(0, 200)}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                let html = '';
                if (data.error) {
                    throw new Error(data.error);
                }
                if (data.length > 0) {
                    html =
                        '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>#</th><th>NIS</th><th>Nama Siswa</th></tr></thead><tbody>';
                    data.forEach((siswa, index) => {
                        html += `<tr><td>${index + 1}</td><td>${siswa.nis}</td><td>${siswa.nama}</td></tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = '<div class="alert alert-info">Tidak ada siswa di kelas ini.</div>';
                }
                document.getElementById('siswa-list-container').innerHTML = html;
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error fetching siswa:', error);
                let errorMessage = 'Gagal memuat data siswa.';
                if (error.name === 'AbortError') {
                    errorMessage = 'Request terlalu lama, tidak ada respon dari server.';
                } else {
                    errorMessage += ` Detail: ${error.message}`;
                }
                document.getElementById('siswa-list-container').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error!</strong><br>
                        ${errorMessage}
                    </div>`;
            });
    }
    </script>
