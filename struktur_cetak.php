<?php
/**
 * struktur_cetak.php - Halaman Cetak Struktur Organisasi
 * Menampilkan pratinjau bagan dengan opsi kertas, lalu window.print().
 */
require_once 'lib/struktur_functions.php';
require_once 'lib/versi_aset.php';
require_once 'lib/cetak_pilihan.php';

// Ambil daftar semua bagan
$semua_bagan = struktur_get_all($koneksi);

// Tentukan bagan aktif dari parameter ?id=
$id_aktif = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Jika tidak ada id di URL, pakai bagan pertama (jika ada)
if ($id_aktif <= 0 && count($semua_bagan) > 0) {
    $id_aktif = (int) $semua_bagan[0]['id'];
}

// Ambil data bagan aktif
$bagan = null;
$nodes = [];
$gaya_bagan = [];

if ($id_aktif > 0) {
    $bagan = struktur_get_by_id($koneksi, $id_aktif);
}

if ($bagan) {
    $nodes = struktur_get_nodes($koneksi, $bagan['id']);
    $gaya_bagan = struktur_validate_chart_style($bagan['gaya_json']);
}

// Siapkan data node untuk JSON (escape aman)
$nodes_json = json_encode($nodes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$gaya_json  = json_encode($gaya_bagan, JSON_HEX_TAG | JSON_HEX_AMP);

$page_title = 'Cetak Struktur Organisasi';
require 'header.php';
?>

<link rel="stylesheet" href="<?php echo versi_aset('assets/css/struktur_cetak.css'); ?>">

<!-- Pemilih Jenis Cetakan -->
<?php render_pilihan_cetak('struktur'); ?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-print me-2"></i>Cetak Struktur Organisasi</h1>
</div>

<?php if (count($semua_bagan) === 0): ?>
    <!-- Tidak ada bagan sama sekali -->
    <div class="sc-pesan-kosong">
        <i class="fas fa-sitemap d-block"></i>
        <h4>Belum Ada Bagan</h4>
        <p>Belum ada bagan struktur organisasi yang dibuat.</p>
        <a href="struktur.php" class="btn btn-primary mt-2">
            <i class="fas fa-plus me-1"></i> Buat Bagan Baru
        </a>
    </div>

<?php elseif (!$bagan): ?>
    <!-- id tidak valid -->
    <div class="sc-pesan-kosong">
        <i class="fas fa-exclamation-circle d-block"></i>
        <h4>Bagan Tidak Ditemukan</h4>
        <p>Bagan dengan ID <?php echo e($id_aktif); ?> tidak ditemukan atau sudah dihapus.</p>
        <a href="struktur_cetak.php" class="btn btn-primary mt-2">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

<?php elseif (count($nodes) === 0): ?>
    <!-- Bagan ada tapi kosong -->
    <div class="sc-pesan-kosong">
        <i class="fas fa-folder-open d-block"></i>
        <h4>Bagan Belum Berisi Node</h4>
        <p>Bagan &ldquo;<?php echo e($bagan['judul']); ?>&rdquo; belum memiliki jabatan/node.</p>
        <a href="struktur_editor.php?id=<?php echo (int)$bagan['id']; ?>" class="btn btn-primary mt-2">
            <i class="fas fa-edit me-1"></i> Buka Editor
        </a>
    </div>

<?php else: ?>

    <!-- Dropdown Pilih Bagan (jika lebih dari satu) -->
    <?php if (count($semua_bagan) > 1): ?>
    <div class="no-print mb-3 d-flex align-items-center gap-2 flex-wrap">
        <label for="scPilihBagan" class="form-label mb-0 fw-semibold text-secondary small">
            <i class="fas fa-sitemap me-1"></i> Pilih Bagan:
        </label>
        <select id="scPilihBagan" class="form-select form-select-sm sc-pilih-bagan"
                onchange="if(this.value) window.location.href='struktur_cetak.php?id='+this.value;">
            <?php foreach ($semua_bagan as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>"
                <?php echo ((int)$b['id'] === $id_aktif) ? 'selected' : ''; ?>>
                <?php echo e($b['judul']); ?>
                <?php if ($b['nama_kelas']): ?> (<?php echo e($b['nama_kelas']); ?>)<?php endif; ?>
                &mdash; <?php echo (int)$b['jumlah_node']; ?> node
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- Panel Opsi Cetak -->
    <div class="sc-opsi-panel no-print">
        <h6 class="fw-semibold mb-3">
            <i class="fas fa-cog me-1 text-primary"></i> Opsi Cetak
        </h6>

        <div class="sc-opsi-grid">
            <div class="sc-opsi-group">
                <label for="scKertas">Ukuran Kertas</label>
                <select id="scKertas">
                    <option value="A4" selected>A4 (210 × 297 mm)</option>
                    <option value="A3">A3 (297 × 420 mm)</option>
                </select>
            </div>
            <div class="sc-opsi-group">
                <label for="scOrientasi">Orientasi</label>
                <select id="scOrientasi">
                    <option value="landscape" selected>Lanskap</option>
                    <option value="portrait">Potret</option>
                </select>
            </div>
            <div class="sc-opsi-group">
                <label for="scJudul">Judul Cetak</label>
                <input type="text" id="scJudul" value="<?php echo e($bagan['judul']); ?>"
                       placeholder="Struktur Organisasi">
            </div>
            <div class="sc-opsi-group">
                <label for="scSekolah">Nama Sekolah</label>
                <input type="text" id="scSekolah" value="" placeholder="Nama sekolah (opsional)">
            </div>
        </div>

        <div class="sc-opsi-toggles">
            <label class="sc-opsi-toggle">
                <input type="checkbox" id="scMuat" checked> Muat ke Satu Halaman
            </label>
            <label class="sc-opsi-toggle">
                <input type="checkbox" id="scFoto" checked> Tampilkan Foto
            </label>
            <label class="sc-opsi-toggle">
                <input type="checkbox" id="scKeterangan" checked> Tampilkan Keterangan
            </label>
            <label class="sc-opsi-toggle">
                <input type="checkbox" id="scGrid"> Tampilkan Grid
            </label>
            <label class="sc-opsi-toggle">
                <input type="checkbox" id="scTtd"> Blok Tanda Tangan
            </label>
        </div>

        <!-- Isian Tanda Tangan (muncul jika centang) -->
        <div id="scTtdFields" class="sc-opsi-ttd" style="display: none;">
            <div class="sc-opsi-grid">
                <div class="sc-opsi-group">
                    <label for="scTtdJabatan">Jabatan Penandatangan</label>
                    <input type="text" id="scTtdJabatan" placeholder="Kepala Sekolah">
                </div>
                <div class="sc-opsi-group">
                    <label for="scTtdNama">Nama Penandatangan</label>
                    <input type="text" id="scTtdNama" placeholder="Nama lengkap">
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar Cetak -->
    <div class="sc-toolbar no-print">
        <span class="text-secondary small me-auto">
            <i class="fas fa-calendar-alt me-1"></i>
            <span id="scTanggal"></span>
        </span>
        <a href="struktur_editor.php?id=<?php echo (int)$bagan['id']; ?>" class="btn btn-outline btn-sm">
            <i class="fas fa-edit me-1"></i> Buka Editor
        </a>
        <button type="button" id="scBtnCetak" class="btn btn-primary btn-sm">
            <i class="fas fa-print me-1"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Pratinjau Kertas -->
    <div class="sc-paper-wrap">
        <div class="sc-paper" id="scPaper">
            <div class="sc-paper-header" id="scPaperHeader">
                <h2><?php echo e($bagan['judul']); ?></h2>
                <p class="sc-sekolah-text"></p>
                <div class="sc-paper-date"></div>
            </div>

            <div class="sc-bagan-area" id="scBaganArea">
                <div class="sc-bagan-viewport" id="scViewport">
                    <svg class="sc-bagan-svg" id="scSvg" xmlns="http://www.w3.org/2000/svg"></svg>
                </div>
            </div>

            <div class="sc-ttd-area" id="scTtdArea" style="display: none;">
                <div class="sc-ttd-blok">
                    <p class="sc-ttd-jabatan"></p>
                    <div class="sc-ttd-garis"></div>
                    <p class="sc-ttd-nama fw-semibold">___________________</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data JSON untuk JS (dibaca via data-attribute, tanpa inline script) -->
    <div id="scData" hidden
         data-nodes="<?php echo e($nodes_json); ?>"
         data-gaya="<?php echo e($gaya_json); ?>"></div>

<?php endif; ?>

<script src="<?php echo versi_aset('assets/js/cetak_pilihan.js'); ?>"></script>
<script src="<?php echo versi_aset('assets/js/struktur_render.js'); ?>"></script>
<script src="<?php echo versi_aset('assets/js/struktur_cetak.js'); ?>"></script>

<?php require 'footer.php'; ?>
