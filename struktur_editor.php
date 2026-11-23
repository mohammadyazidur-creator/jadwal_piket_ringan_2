<?php
/**
 * struktur_editor.php - Halaman Interaktif Editor Bagan Struktur Organisasi (Tahap 3)
 * Fitur:
 * - Kanvas interaktif (drag node, SVG connection, pan & zoom)
 * - Tambah & Ubah node (sumber nama siswa atau manual, jabatan, keterangan, foto, parent)
 * - Kustomisasi gaya per node (font, ukuran, format, warna, bentuk, lebar) + live preview
 * - Pengaturan bagan global (gaya garis, tebal, warna, grid on/off)
 * - Dialog hapus node (pilihan reparent atau cascade)
 * - Undo / Redo & Rapikan Otomatis (Tree Layout)
 * - Penanda perubahan belum disimpan & konfirmasi beforeunload
 * Sesuai AGENTS.md: Desain konsisten, modular, reusable, dan prepared statement.
 */

require_once 'koneksi.php';
require_once 'lib/struktur_functions.php';

// Validasi parameter ID bagan
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    $_SESSION['error_message'] = "ID bagan struktur tidak valid.";
    header('Location: struktur.php');
    exit;
}

// Ambil data bagan struktur dari database
$struktur = struktur_get_by_id($koneksi, $id);
if (!$struktur) {
    $_SESSION['error_message'] = "Bagan struktur organisasi yang diminta tidak ditemukan.";
    header('Location: struktur.php');
    exit;
}

// Ambil daftar seluruh node jabatan dalam bagan ini
$nodes = struktur_get_nodes($koneksi, $id);

// Ambil daftar siswa untuk dropdown pemilih (sesuai kelas jika ada)
$daftar_siswa = struktur_get_siswa_list($koneksi, $struktur['kelas_id']);

// Konfigurasi gaya bagan global dari database
$gaya_bagan = struktur_validate_chart_style($struktur['gaya_json'] ?? '');

// Judul halaman untuk header.php
$page_title = 'Editor: ' . $struktur['judul'];
require 'header.php';
?>

<!-- Token CSRF untuk Request AJAX -->
<input type="hidden" id="csrfToken" value="<?php echo csrf_token(); ?>">

<!-- Notifikasi Mengambang Sukses Simpan -->
<div id="saveSuccessAlert" class="alert alert-success alert-dismissible fade show py-2 px-3 small position-fixed top-0 start-50 translate-middle-x mt-4 shadow-sm" style="z-index: 9999; display: none;">
    <i class="fas fa-check-circle me-1"></i> Perubahan struktur berhasil disimpan ke database.
</div>

<!-- Header Editor Sesuai AGENTS.md -->
<div class="page-header mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="struktur.php" class="btn btn-outline-secondary btn-sm" title="Kembali ke Daftar Bagan">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <h1 class="h4 mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="fas fa-sitemap text-primary me-2"></i>
                <?php echo e($struktur['judul']); ?>
            </h1>
            <?php if (!empty($struktur['nama_kelas'])): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                    <i class="fas fa-door-open me-1"></i> Kelas <?php echo e($struktur['nama_kelas']); ?>
                </span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">
                    <i class="fas fa-globe me-1"></i> Bagan Umum
                </span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="badgeUnsaved" class="badge bg-warning text-dark border border-warning" style="display: none;">
                <span class="badge-unsaved-dot"></span> Belum Disimpan
            </span>
            <div class="text-muted small">
                <i class="fas fa-cubes me-1 text-primary"></i> <span id="nodeCountBadge"><?php echo count($nodes); ?></span> Node Jabatan
            </div>
        </div>
    </div>
</div>

<!-- Toolbar Editor Lengkap (Zoom, Pan, Undo/Redo, Auto-Layout, Pengaturan, Tambah, Simpan) -->
<div class="struktur-editor-toolbar">
    <!-- Kontrol Zoom & Kanvas -->
    <div class="struktur-toolbar-group">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnZoomOut" title="Perkecil Kanvas (Zoom Out)">
            <i class="fas fa-minus"></i>
        </button>
        <span class="struktur-zoom-badge" id="zoomBadge" title="Tingkat Pembesaran">100%</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnZoomIn" title="Perbesar Kanvas (Zoom In)">
            <i class="fas fa-plus"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnZoomReset" title="Reset Ukuran (100%)">
            <i class="fas fa-compress-arrows-alt me-1"></i> Reset
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnZoomFit" title="Pusatkan Seluruh Node">
            <i class="fas fa-expand me-1"></i> Pusatkan
        </button>
    </div>

    <!-- Kontrol Riwayat & Tata Letak -->
    <div class="struktur-toolbar-group">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnUndo" title="Undo (Ctrl+Z)" disabled>
            <i class="fas fa-undo"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRedo" title="Redo (Ctrl+Y)" disabled>
            <i class="fas fa-redo"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAutoLayout" title="Tata Otomatis Susunan Pohon Hierarki">
            <i class="fas fa-stream me-1"></i> Rapikan Otomatis
        </button>
    </div>

    <!-- Tombol Aksi Utama -->
    <div class="struktur-toolbar-group">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnChartSettings" title="Pengaturan Tampilan Bagan Global">
            <i class="fas fa-sliders-h me-1"></i> Pengaturan Bagan
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddNode" title="Tambah Node Jabatan Baru">
            <i class="fas fa-plus-circle me-1"></i> Tambah Node
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="btnSaveCanvas" title="Simpan Seluruh Perubahan ke Database (Ctrl+S)">
            <i class="fas fa-save me-1"></i> Simpan Perubahan
        </button>
    </div>
</div>

<!-- Area Kanvas Viewport Interaktif -->
<div class="struktur-viewport <?php echo ($gaya_bagan['grid'] ?? true) ? 'has-grid' : 'no-grid'; ?>" id="strukturViewport">
    <div class="struktur-canvas-content" id="canvasContent">
        <!-- Layer Garis Penghubung Kurva SVG -->
        <svg class="struktur-svg-layer" id="svgLayer"></svg>

        <!-- Layer Kotak Jabatan (Nodes) -->
        <div class="struktur-nodes-layer" id="nodesLayer"></div>
    </div>
</div>

<!-- Petunjuk Navigasi Kanvas -->
<div class="struktur-editor-hint">
    <span>
        <i class="fas fa-info-circle text-primary me-1"></i>
        <strong>Cara Penggunaan:</strong> Klik/sentuh dan geser kotak jabatan untuk mengatur susunan. Hover node untuk tombol ubah & hapus.
    </span>
    <span>
        <i class="fas fa-arrows-alt me-1"></i> Geser kanvas kosong untuk pan.
    </span>
    <span>
        <i class="fas fa-mouse me-1"></i> Scroll mouse untuk zoom.
    </span>
    <span>
        <i class="fas fa-keyboard me-1"></i> Ctrl+Z: Undo | Ctrl+S: Simpan.
    </span>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: FORM TAMBAH / UBAH NODE DENGAN TAB INFORMASI & TAMPILAN          -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalNodeForm" tabindex="-1" aria-labelledby="modalNodeFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalNodeFormTitle">
                    <i class="fas fa-user-tie text-primary me-2"></i> Tambah Node Jabatan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Nav Tabs Modal Sesuai AGENTS.md -->
                <ul class="nav nav-tabs px-3 pt-3" id="nodeFormTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="tabInfoUtamaBtn" data-bs-toggle="tab" data-bs-target="#tabInfoUtamaPane" type="button" role="tab">
                            <i class="fas fa-id-card me-1"></i> Informasi Utama
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="tabTampilanBtn" data-bs-toggle="tab" data-bs-target="#tabTampilanPane" type="button" role="tab">
                            <i class="fas fa-palette me-1"></i> Tampilan (Gaya)
                        </button>
                    </li>
                </ul>

                <form id="formNode" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="nodeFormId" value="0">
                    <input type="hidden" name="warna" id="nodeWarna" value="blue">

                    <div class="tab-content p-3" id="nodeFormTabsContent">
                        <!-- TAB 1: INFORMASI UTAMA -->
                        <div class="tab-pane fade show active" id="tabInfoUtamaPane" role="tabpanel">
                            <!-- Jabatan -->
                            <div class="mb-3">
                                <label for="nodeJabatan" class="form-label fw-bold small text-muted">
                                    NAMA JABATAN / STRUKTUR <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="nodeJabatan" name="jabatan" placeholder="Contoh: Wali Kelas, Ketua Kelas, Bendahara 1" required>
                            </div>

                            <!-- Pilihan Sumber Nama: Siswa vs Ketik Manual -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted d-block">
                                    SUMBER NAMA ANGGOTA / PEJABAT <span class="text-danger">*</span>
                                </label>
                                <div class="btn-group w-100 mb-2" role="group">
                                    <input type="radio" class="btn-check" name="sumber_nama_radio" id="sumberNamaSiswa" autocomplete="off" checked>
                                    <label class="btn btn-outline-primary" for="sumberNamaSiswa">
                                        <i class="fas fa-user-graduate me-1"></i> Pilih dari Data Siswa
                                    </label>

                                    <input type="radio" class="btn-check" name="sumber_nama_radio" id="sumberNamaManual" autocomplete="off">
                                    <label class="btn btn-outline-primary" for="sumberNamaManual">
                                        <i class="fas fa-edit me-1"></i> Ketik Manual (Bebas / Guru / Pembina)
                                    </label>
                                </div>

                                <!-- Box Pilihan Siswa -->
                                <div id="boxPilihSiswa">
                                    <select class="form-select" id="nodeSiswaSelect">
                                        <option value="">-- Pilih Nama Siswa Terdaftar --</option>
                                        <?php foreach ($daftar_siswa as $sis): ?>
                                            <option value="<?php echo $sis['id']; ?>" 
                                                    data-nama="<?php echo e($sis['nama']); ?>" 
                                                    data-nis="<?php echo e($sis['nis']); ?>">
                                                <?php echo e($sis['nama']); ?> <?php echo !empty($sis['nis']) ? '(' . e($sis['nis']) . ')' : ''; ?> <?php echo !empty($sis['nama_kelas']) ? '[' . e($sis['nama_kelas']) . ']' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Nama dan NIS akan terhubung otomatis dengan data siswa.</div>
                                </div>

                                <!-- Box Ketik Manual -->
                                <div id="boxKetikManual" style="display: none;">
                                    <input type="text" class="form-control" id="nodeNamaManual" placeholder="Ketik nama lengkap pejabat / guru / pembina...">
                                    <div class="form-text small">Gunakan opsi ini untuk orang di luar data siswa (mis. Wali Kelas, Pembina OSIS, Kepala Sekolah).</div>
                                </div>
                            </div>

                            <!-- Keterangan Opsional (NIP, Kontak, dsb.) -->
                            <div class="mb-3">
                                <label for="nodeKeterangan" class="form-label fw-bold small text-muted">
                                    KETERANGAN TAMBAHAN (OPSIONAL)
                                </label>
                                <input type="text" class="form-control" id="nodeKeterangan" name="keterangan" placeholder="Contoh: NIP. 19800101 200501 1 002, Kontak: 0812..., dsb.">
                                <div class="form-text small">Keterangan akan ditampilkan dengan ukuran lebih kecil di bawah nama pejabat.</div>
                            </div>

                            <!-- Pilihan Atasan (Parent Node) -->
                            <div class="mb-3">
                                <label for="nodeParentSelect" class="form-label fw-bold small text-muted">
                                    ATASAN LANGSUNG (PARENT)
                                </label>
                                <select class="form-select" id="nodeParentSelect" name="parent_id">
                                    <option value="">(Tingkat Teratas / Tidak Ada Atasan)</option>
                                    <!-- Diisi secara dinamis dengan pencegahan siklus/loop -->
                                </select>
                                <div class="form-text small">Pilih pejabat yang membawahi posisi ini. Sistem secara otomatis mencegah hubungan hierarki melingkar (loop).</div>
                            </div>

                            <!-- Pilihan Tema Cepat Warna Node (Palet CSS Variables) -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted d-block">
                                    TEMA WARNA DASAR (PILIHAN CEPAT)
                                </label>
                                <div class="palette-swatches" id="nodeWarnaSwatches">
                                    <div class="palette-swatch active" data-warna="blue" style="background: var(--node-blue);" title="Blue (Biru Utama)"></div>
                                    <div class="palette-swatch" data-warna="emerald" style="background: var(--node-green);" title="Emerald (Hijau)"></div>
                                    <div class="palette-swatch" data-warna="amber" style="background: var(--node-amber);" title="Amber (Kuning/Emas)"></div>
                                    <div class="palette-swatch" data-warna="rose" style="background: var(--node-rose);" title="Rose (Merah/Mawar)"></div>
                                    <div class="palette-swatch" data-warna="purple" style="background: var(--node-purple);" title="Purple (Ungu)"></div>
                                    <div class="palette-swatch" data-warna="slate" style="background: var(--node-slate);" title="Slate (Abu-abu Gelap)"></div>
                                </div>
                            </div>

                            <!-- Foto Pejabat / Siswa -->
                            <div class="mb-2">
                                <label for="nodeFotoInput" class="form-label fw-bold small text-muted">
                                    FOTO PROFIL (OPSIONAL)
                                </label>
                                <div id="nodeFotoPreviewWrap" class="mb-2" style="display: none;">
                                    <div class="d-flex align-items-center gap-2">
                                        <img id="nodeFotoPreviewImg" src="" alt="Foto" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-blue);">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="checkHapusFoto" name="hapus_foto" value="1">
                                            <label class="form-check-label small text-danger" for="checkHapusFoto">
                                                Hapus foto yang tersimpan
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <input class="form-control form-control-sm" type="file" id="nodeFotoInput" name="foto" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Format JPG, PNG, atau WebP. Ukuran maksimal 2 MB.</div>
                            </div>
                        </div>

                        <!-- TAB 2: TAMPILAN (GAYA KUSTOM) -->
                        <div class="tab-pane fade" id="tabTampilanPane" role="tabpanel">
                            <!-- Area Live Preview -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted d-block">
                                    <i class="fas fa-eye me-1"></i> PRATINJAU LANGSUNG (LIVE PREVIEW)
                                </label>
                                <div class="node-preview-box" id="nodeModalLivePreview">
                                    <!-- Node preview di-render oleh createNodeElement secara real-time -->
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Font Family -->
                                <div class="col-md-6">
                                    <label for="nodeFontFamily" class="form-label fw-bold small text-muted">JENIS FONT</label>
                                    <select class="form-select form-select-sm" id="nodeFontFamily">
                                        <option value="Inter">Inter (Standar Proyek)</option>
                                        <option value="Segoe UI">Segoe UI</option>
                                        <option value="system-ui">System UI</option>
                                        <option value="Georgia">Georgia (Serif Klasik)</option>
                                        <option value="Arial">Arial (Sans-serif Modern)</option>
                                        <option value="Courier New">Courier New (Monospace)</option>
                                    </select>
                                </div>

                                <!-- Font Size -->
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between">
                                        <label for="nodeFontSize" class="form-label fw-bold small text-muted">UKURAN FONT</label>
                                        <span class="badge bg-light text-dark border small" id="nodeFontSizeBadge">14px</span>
                                    </div>
                                    <input type="range" class="form-range" id="nodeFontSize" min="10" max="24" value="14" step="1">
                                </div>

                                <!-- Format Teks & Alignment -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted d-block">FORMAT TEKS</label>
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <button type="button" class="btn btn-outline-secondary" id="nodeFontWeightBold" title="Tebal (Bold)">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="nodeFontStyleItalic" title="Miring (Italic)">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted d-block">RATA TEKS</label>
                                    <div class="btn-group btn-group-sm w-100 btn-align-group" role="group">
                                        <button type="button" class="btn btn-outline-secondary" id="nodeTextAlign_left" data-align="left" title="Rata Kiri">
                                            <i class="fas fa-align-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary active" id="nodeTextAlign_center" data-align="center" title="Rata Tengah">
                                            <i class="fas fa-align-center"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="nodeTextAlign_right" data-align="right" title="Rata Kanan">
                                            <i class="fas fa-align-right"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Bentuk & Lebar Node -->
                                <div class="col-md-6">
                                    <label for="nodeShape" class="form-label fw-bold small text-muted">BENTUK SUDUT</label>
                                    <select class="form-select form-select-sm" id="nodeShape">
                                        <option value="rounded">Membulat (Standar - 14px)</option>
                                        <option value="square">Kotak Persegi (4px)</option>
                                        <option value="pill">Pil Lonjong (32px)</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="nodeWidth" class="form-label fw-bold small text-muted">LEBAR KOTAK</label>
                                    <select class="form-select form-select-sm" id="nodeWidth">
                                        <option value="small">Kecil (180 px)</option>
                                        <option value="medium" selected>Sedang (230 px - Standar)</option>
                                        <option value="large">Lebar (280 px)</option>
                                    </select>
                                </div>

                                <!-- Toggle Ikon / Avatar -->
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="nodeShowIcon" checked>
                                        <label class="form-check-label small fw-bold text-dark" for="nodeShowIcon">
                                            Tampilkan Ikon / Foto Avatar
                                        </label>
                                    </div>
                                </div>

                                <!-- Pemilihan Warna Bebas (Hex Picker) -->
                                <div class="col-6 col-md-3">
                                    <label for="nodeColorHeader" class="form-label fw-bold small text-muted">WARNA HEADER</label>
                                    <input type="color" class="form-control form-control-color w-100" id="nodeColorHeader" value="#2563eb" title="Pilih warna header">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="nodeColorBg" class="form-label fw-bold small text-muted">LATAR KOTAK</label>
                                    <input type="color" class="form-control form-control-color w-100" id="nodeColorBg" value="#ffffff" title="Pilih warna latar">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="nodeColorText" class="form-label fw-bold small text-muted">WARNA TEKS</label>
                                    <input type="color" class="form-control form-control-color w-100" id="nodeColorText" value="#1e3a8a" title="Pilih warna teks">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="nodeColorBorder" class="form-label fw-bold small text-muted">WARNA GARIS TEPI</label>
                                    <input type="color" class="form-control form-control-color w-100" id="nodeColorBorder" value="#93c5fd" title="Pilih warna border">
                                </div>

                                <!-- Tombol Aksi Tambahan Gaya -->
                                <div class="col-12 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetStyle">
                                        <i class="fas fa-undo me-1"></i> Reset ke Gaya Bawaan
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnApplyStyleAll">
                                        <i class="fas fa-paint-roller me-1"></i> Terapkan ke Semua Node
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSubmitNodeForm">
                    <i class="fas fa-save me-1"></i> Simpan Node
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: DIALOG KONFIRMASI HAPUS NODE (PILIHAN REPARENT ATAU CASCADE)     -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalHapusNode" tabindex="-1" aria-labelledby="modalHapusNodeTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom bg-danger text-white">
                <h5 class="modal-title fw-bold" id="modalHapusNodeTitle">
                    <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus Node
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p>Anda akan menghapus posisi jabatan: <strong id="delNodeNama">...</strong></p>

                <!-- Pilihan Penanganan Anak (Jika Memiliki Bawahan) -->
                <div id="delNodeChildrenAlert" class="card border-warning bg-warning-subtle p-3 mb-3" style="display: none;">
                    <div class="d-flex align-items-center mb-2 text-warning-emphasis fw-bold">
                        <i class="fas fa-sitemap me-2"></i> Node ini memiliki <span id="delChildrenCount" class="mx-1">0</span> bawahan / anak hierarki.
                    </div>
                    <div class="small text-muted mb-2">Pilih tindakan untuk bawahan node ini:</div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="delAction" id="delActionReparent" value="reparent" checked>
                        <label class="form-check-label fw-bold text-dark small" for="delActionReparent">
                            Pindahkan anak ke atasan node ini (Rekomendasi)
                        </label>
                        <div class="form-text small">Seluruh bawahan akan naik satu tingkat dan dihubungkan ke atasan dari node yang dihapus.</div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delAction" id="delActionCascade" value="cascade">
                        <label class="form-check-label fw-bold text-danger small" for="delActionCascade">
                            Hapus beserta seluruh turunannya
                        </label>
                        <div class="form-text small text-danger">PERHATIAN: Seluruh cabang di bawah posisi ini akan ikut terhapus secara permanen.</div>
                    </div>
                </div>

                <div class="text-muted small">Tindakan ini tidak dapat dibatalkan setelah disimpan ke database.</div>
            </div>

            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDeleteNode">
                    <i class="fas fa-trash me-1"></i> Hapus Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: PENGATURAN BAGAN GLOBAL (GARIS & GRID KANVAS)                    -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalPengaturanBagan" tabindex="-1" aria-labelledby="modalChartSettingsTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalChartSettingsTitle">
                    <i class="fas fa-sliders-h text-primary me-2"></i> Pengaturan Tampilan Bagan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Gaya Garis Penghubung -->
                <div class="mb-3">
                    <label for="chartLineStyle" class="form-label fw-bold small text-muted">GAYA GARIS PENGHUBUNG</label>
                    <select class="form-select" id="chartLineStyle">
                        <option value="curve">Lengkung Halus (Cubic Bezier - Bawaan)</option>
                        <option value="step">Siku Tegak Lurus (Orthogonal Step)</option>
                        <option value="straight">Lurus Langsung (Direct Line)</option>
                    </select>
                </div>

                <!-- Ketebalan Garis -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="chartLineWidth" class="form-label fw-bold small text-muted">KETEBALAN GARIS</label>
                        <span class="badge bg-light text-dark border small" id="chartLineWidthBadge">2px</span>
                    </div>
                    <input type="range" class="form-range" id="chartLineWidth" min="1" max="6" value="2" step="1" 
                           oninput="document.getElementById('chartLineWidthBadge').textContent = this.value + 'px'">
                </div>

                <!-- Warna Garis -->
                <div class="mb-3">
                    <label for="chartLineColor" class="form-label fw-bold small text-muted">WARNA GARIS PENGHUBUNG</label>
                    <input type="color" class="form-control form-control-color w-100" id="chartLineColor" value="#2563eb" title="Pilih warna garis">
                </div>

                <!-- Grid Kanvas On/Off -->
                <div class="mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chartGridToggle" checked>
                        <label class="form-check-label fw-bold text-dark small" for="chartGridToggle">
                            Tampilkan Titik Grid pada Latar Kanvas
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveChartSettings">
                    <i class="fas fa-save me-1"></i> Simpan Pengaturan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Muat Script Interaktivitas Struktur -->
<script src="assets/js/struktur_render.js"></script>
<script src="assets/js/struktur.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var initialNodes = <?php echo json_encode($nodes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var daftarSiswa = <?php echo json_encode($daftar_siswa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var initialGayaBagan = <?php echo json_encode($gaya_bagan, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    StrukturEditor.init({
        viewportId: 'strukturViewport',
        contentId: 'canvasContent',
        svgId: 'svgLayer',
        nodesLayerId: 'nodesLayer',
        zoomBadgeId: 'zoomBadge',
        strukturId: <?php echo intval($struktur['id']); ?>,
        csrfToken: '<?php echo csrf_token(); ?>',
        daftarSiswa: daftarSiswa,
        gayaBagan: initialGayaBagan,
        nodes: initialNodes
    });
});
</script>

<?php require 'footer.php'; ?>
