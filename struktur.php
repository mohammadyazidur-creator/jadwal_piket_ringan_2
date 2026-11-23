<?php
/**
 * struktur.php - Halaman Daftar Bagan Struktur Organisasi
 * Menampilkan daftar seluruh bagan, pembuatan bagan baru, pengubahan judul, dan penghapusan.
 */

require_once 'koneksi.php';
require_once 'lib/struktur_functions.php';

// Judul halaman untuk header
$page_title = 'Struktur Organisasi';

// Tangani aksi POST (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($csrf)) {
        $_SESSION['error_message'] = "Validasi keamanan (CSRF) gagal. Silakan coba lagi.";
        header('Location: struktur.php');
        exit;
    }

    $mode = $_POST['mode'] ?? '';

    if ($mode === 'create') {
        $judul = trim($_POST['judul'] ?? '');
        $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : null;

        if ($judul === '') {
            $_SESSION['error_message'] = "Judul bagan organisasi tidak boleh kosong.";
        } else {
            $new_id = struktur_create($koneksi, $judul, $kelas_id);
            if ($new_id > 0) {
                $_SESSION['success_message'] = "Bagan struktur baru berhasil dibuat. Silakan buka editor untuk menata node.";
                header("Location: struktur_editor.php?id=$new_id");
                exit;
            } else {
                $_SESSION['error_message'] = "Gagal membuat bagan struktur. Silakan coba lagi.";
            }
        }
        header('Location: struktur.php');
        exit;

    } elseif ($mode === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : null;

        if ($id <= 0 || $judul === '') {
            $_SESSION['error_message'] = "Data perubahan bagan tidak valid.";
        } else {
            $ok = struktur_update($koneksi, $id, $judul, $kelas_id);
            if ($ok) {
                $_SESSION['success_message'] = "Informasi bagan struktur berhasil diperbarui.";
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui informasi bagan struktur.";
            }
        }
        header('Location: struktur.php');
        exit;

    } elseif ($mode === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error_message'] = "ID bagan tidak valid untuk dihapus.";
        } else {
            $ok = struktur_delete($koneksi, $id);
            if ($ok) {
                $_SESSION['success_message'] = "Bagan struktur beserta seluruh node-nya berhasil dihapus.";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus bagan struktur.";
            }
        }
        header('Location: struktur.php');
        exit;
    }
}

// Ambil data untuk tampilan
$daftar_struktur = struktur_get_all($koneksi);
$daftar_kelas = struktur_get_kelas_list($koneksi);
$csrf_token = csrf_token();

require 'header.php';
?>

<!-- Header Halaman Sesuai AGENTS.md Bagian 2 -->
<div class="page-header">
    <h1 class="page-title">Struktur Organisasi</h1>
    <p class="page-subtitle">Kelola dan susun bagan kepengurusan kelas dan struktur piket secara interaktif</p>
</div>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo e($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo e($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Bar Aksi Atas -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <span class="text-muted">Total: <strong><?php echo count($daftar_struktur); ?></strong> bagan struktur</span>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahStruktur">
            <i class="fas fa-plus me-2"></i> Buat Bagan Baru
        </button>
    </div>
</div>

<!-- Daftar Bagan (Grid Kartu Reusable) -->
<?php if (empty($daftar_struktur)): ?>
    <div class="card p-5 text-center">
        <div class="mb-3">
            <i class="fas fa-sitemap fa-3x text-muted"></i>
        </div>
        <h5 class="fw-bold">Belum Ada Bagan Struktur Organisasi</h5>
        <p class="text-muted">Klik tombol "Buat Bagan Baru" di atas untuk mulai membuat bagan struktur organisasi pertama Anda.</p>
        <div class="mt-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahStruktur">
                <i class="fas fa-plus me-2"></i> Buat Bagan Baru
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($daftar_struktur as $st): ?>
            <div class="col-md-6 col-lg-4 d-flex">
                <div class="card struktur-card h-100 w-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="struktur-icon-box flex-shrink-0">
                                <i class="fas fa-sitemap"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <h5 class="fw-bold mb-1 card-title-clamp" title="<?php echo e($st['judul']); ?>">
                                    <?php echo e($st['judul']); ?>
                                </h5>
                                <div class="text-muted small">
                                    <i class="fas fa-door-open me-1"></i>
                                    <?php echo !empty($st['nama_kelas']) ? 'Kelas: ' . e($st['nama_kelas']) : 'Bagan Umum / Semua Kelas'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center py-2 mb-3 border-top border-bottom small">
                            <span class="struktur-badge-count">
                                <i class="fas fa-users"></i> <?php echo intval($st['jumlah_node']); ?> Node Jabatan
                            </span>
                            <span class="text-muted" title="Dibuat pada">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d M Y', strtotime($st['dibuat_pada'])); ?>
                            </span>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="mt-auto d-flex justify-content-between align-items-center gap-2">
                            <a href="struktur_editor.php?id=<?php echo $st['id']; ?>" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fas fa-pencil-ruler me-1"></i> Buka Editor
                            </a>
                            <a href="struktur_cetak.php?id=<?php echo $st['id']; ?>" class="btn btn-outline-success btn-sm" title="Tampilan Cetak">
                                <i class="fas fa-print"></i>
                            </a>
                            <button type="button" class="btn btn-outline-warning btn-sm" title="Ubah Judul / Kelas" 
                                    onclick="editStruktur(<?php echo $st['id']; ?>, '<?php echo addslashes(e($st['judul'])); ?>', '<?php echo intval($st['kelas_id']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" title="Hapus Bagan" 
                                    onclick="konfirmasiHapus(<?php echo $st['id']; ?>, '<?php echo addslashes(e($st['judul'])); ?>', <?php echo intval($st['jumlah_node']); ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ========================================================
     MODAL TAMBAH BAGAN BARU (Pola modal standar kelas/siswa)
     ======================================================== -->
<div class="modal fade" id="modalTambahStruktur" tabindex="-1" aria-labelledby="modalTambahStrukturLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTambahStrukturLabel">
                    <i class="fas fa-plus me-2 text-primary"></i> Buat Bagan Struktur Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="struktur.php">
                <input type="hidden" name="mode" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="createJudul" class="form-label fw-semibold">Judul Bagan Struktur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="createJudul" name="judul" required 
                               placeholder="Contoh: Struktur Organisasi Kelas VII A">
                    </div>
                    
                    <div class="mb-3">
                        <label for="createKelas" class="form-label fw-semibold">Tautkan ke Kelas (Opsional)</label>
                        <select class="form-select" id="createKelas" name="kelas_id">
                            <option value="">-- Bagan Umum / Tanpa Kelas --</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?php echo $k['id']; ?>"><?php echo e($k['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Menautkan ke kelas akan memudahkan pemfilteran dan pengisian siswa pada editor.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Buat & Buka Editor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL EDIT BAGAN (Ubah Judul / Kelas)
     ======================================================== -->
<div class="modal fade" id="modalEditStruktur" tabindex="-1" aria-labelledby="modalEditStrukturLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalEditStrukturLabel">
                    <i class="fas fa-edit me-2 text-warning"></i> Edit Informasi Bagan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="struktur.php">
                <input type="hidden" name="mode" value="update">
                <input type="hidden" name="id" id="editStrukturId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editJudul" class="form-label fw-semibold">Judul Bagan Struktur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editJudul" name="judul" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editKelas" class="form-label fw-semibold">Tautkan ke Kelas</label>
                        <select class="form-select" id="editKelas" name="kelas_id">
                            <option value="">-- Bagan Umum / Tanpa Kelas --</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?php echo $k['id']; ?>"><?php echo e($k['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL KONFIRMASI HAPUS BAGAN (POST + CSRF)
     ======================================================== -->
<div class="modal fade" id="modalHapusStruktur" tabindex="-1" aria-labelledby="modalHapusStrukturLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger" id="modalHapusStrukturLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus Bagan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="struktur.php">
                <input type="hidden" name="mode" value="delete">
                <input type="hidden" name="id" id="hapusStrukturId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus bagan struktur <strong id="hapusStrukturJudul"></strong>?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-1"></i> 
                        Tindakan ini tidak dapat dibatalkan. Sebanyak <strong id="hapusStrukturNodeCount">0</strong> node jabatan di dalam bagan ini akan ikut terhapus.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Ya, Hapus Bagan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script penunjang pengisian modal Edit dan Hapus
function editStruktur(id, judul, kelasId) {
    document.getElementById('editStrukturId').value = id;
    document.getElementById('editJudul').value = judul;
    document.getElementById('editKelas').value = (kelasId && kelasId > 0) ? kelasId : '';
    
    var modal = new bootstrap.Modal(document.getElementById('modalEditStruktur'));
    modal.show();
}

function konfirmasiHapus(id, judul, nodeCount) {
    document.getElementById('hapusStrukturId').value = id;
    document.getElementById('hapusStrukturJudul').textContent = judul;
    document.getElementById('hapusStrukturNodeCount').textContent = nodeCount;
    
    var modal = new bootstrap.Modal(document.getElementById('modalHapusStruktur'));
    modal.show();
}
</script>

