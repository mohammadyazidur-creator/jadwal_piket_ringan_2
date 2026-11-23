<?php
/**
 * lib/cetak_pilihan.php - Komponen Reusable Pemilihan Jenis Cetakan
 * Menampilkan dropdown pemilihan antara Jadwal Piket dan Struktur Organisasi.
 * Otomatis tersembunyi saat cetak (.no-print).
 */

function render_pilihan_cetak($aktif = 'jadwal') {
    $is_jadwal = ($aktif === 'jadwal');
    $is_struktur = ($aktif === 'struktur');
    ?>
    <div class="card no-print cetak-pilihan-card">
        <div class="card-body py-2 px-3 d-flex align-items-center flex-wrap gap-2">
            <label for="selectJenisCetak" class="form-label mb-0 fw-semibold text-secondary small text-nowrap">
                <i class="fas fa-print me-1 text-primary"></i> Jenis Cetakan:
            </label>
            <select id="selectJenisCetak" class="form-select form-select-sm cetak-selector" data-cetak-selector>
                <option value="cetak_jadwal.php" <?php echo $is_jadwal ? 'selected' : ''; ?>>
                    Jadwal Piket Kelas
                </option>
                <option value="struktur_cetak.php" <?php echo $is_struktur ? 'selected' : ''; ?>>
                    Struktur Organisasi
                </option>
            </select>
            <noscript>
                <span class="ms-2 small">
                    Pindah ke:
                    <a href="cetak_jadwal.php" class="me-2 text-decoration-none">Jadwal Piket</a> |
                    <a href="struktur_cetak.php" class="ms-2 text-decoration-none">Struktur Organisasi</a>
                </span>
            </noscript>
        </div>
    </div>
    <?php
}
