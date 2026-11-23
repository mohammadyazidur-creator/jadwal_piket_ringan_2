<?php
/**
 * api_struktur.php - API Endpoint untuk Operasi Editor Struktur Organisasi
 * Sesuai AGENTS.md:
 * - Aksi mengubah data memakai POST + CSRF token
 * - Prepared statement pada semua query
 * - Output JSON konsisten: { "ok": true/false, "pesan": "..." }
 * - Validasi input ketat di sisi server
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'koneksi.php';
require_once 'lib/struktur_functions.php';
global $koneksi;

// Fungsi helper output JSON
function json_response($ok, $pesan, $extra = []) {
    echo json_encode(array_merge([
        'ok' => (bool)$ok,
        'pesan' => (string)$pesan
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Hanya menerima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode HTTP tidak diizinkan. Hanya POST yang didukung.');
}

// Validasi Token CSRF
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_validate($csrf)) {
    json_response(false, 'Token CSRF tidak valid atau sesi telah kedaluwarsa. Silakan muat ulang halaman.');
}

$action = trim($_POST['action'] ?? '');
$strukturId = intval($_POST['struktur_id'] ?? 0);

if ($strukturId <= 0) {
    json_response(false, 'ID bagan struktur tidak valid.');
}

// Verifikasi keberadaan bagan struktur
$struktur = struktur_get_by_id($koneksi, $strukturId);
if (!$struktur) {
    json_response(false, 'Bagan struktur organisasi tidak ditemukan.');
}

switch ($action) {
    // -------------------------------------------------------------------------
    // 1. Simpan Seluruh Kanvas (Posisi Node & Pengaturan Bagan)
    // -------------------------------------------------------------------------
    case 'save_canvas':
        $positionsRaw = $_POST['positions'] ?? '';
        $positions = [];
        if (!empty($positionsRaw)) {
            $decoded = is_string($positionsRaw) ? json_decode($positionsRaw, true) : $positionsRaw;
            if (is_array($decoded)) {
                $positions = $decoded;
            }
        }

        if (!empty($positions)) {
            struktur_save_positions($koneksi, $strukturId, $positions);
        }

        // Simpan konfigurasi gaya bagan global jika disertakan
        if (isset($_POST['gaya_bagan'])) {
            $gayaBaganRaw = $_POST['gaya_bagan'];
            $gayaBagan = is_string($gayaBaganRaw) ? json_decode($gayaBaganRaw, true) : $gayaBaganRaw;
            if (is_array($gayaBagan)) {
                struktur_save_chart_settings($koneksi, $strukturId, $gayaBagan);
            }
        }

        json_response(true, 'Semua perubahan berhasil disimpan ke database.');
        break;

    // -------------------------------------------------------------------------
    // 2. Tambah / Ubah Node Jabatan
    // -------------------------------------------------------------------------
    case 'save_node':
        $nodeId = intval($_POST['id'] ?? 0);
        $jabatan = trim($_POST['jabatan'] ?? '');
        $sumberNama = ($_POST['sumber_nama'] ?? '') === 'siswa' ? 'siswa' : 'manual';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $warna = strtolower(trim($_POST['warna'] ?? 'blue'));
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $gayaJsonRaw = $_POST['gaya_json'] ?? null;

        // Validasi input wajib
        if ($jabatan === '') {
            json_response(false, 'Nama jabatan tidak boleh kosong.');
        }

        $nama = '';
        $siswaId = null;

        if ($sumberNama === 'siswa') {
            $siswaId = intval($_POST['siswa_id'] ?? 0);
            if ($siswaId <= 0) {
                json_response(false, 'Silakan pilih siswa dari data siswa yang tersedia.');
            }
            // Ambil nama siswa dari tabel siswa
            $sStmt = mysqli_prepare($koneksi, "SELECT nama FROM siswa WHERE id = ?");
            mysqli_stmt_bind_param($sStmt, "i", $siswaId);
            mysqli_stmt_execute($sStmt);
            $sRes = mysqli_stmt_get_result($sStmt);
            $sRow = mysqli_fetch_assoc($sRes);
            mysqli_stmt_close($sStmt);
            if (!$sRow) {
                json_response(false, 'Data siswa yang dipilih tidak ditemukan.');
            }
            $nama = $sRow['nama'];
        } else {
            $nama = trim($_POST['nama'] ?? '');
            if ($nama === '') {
                json_response(false, 'Nama pejabat/anggota tidak boleh kosong saat memilih ketik manual.');
            }
            $siswaId = null;
        }

        // Whitelist warna node
        $validWarna = ['blue', 'emerald', 'green', 'amber', 'purple', 'rose', 'slate'];
        if (!in_array($warna, $validWarna, true)) {
            $warna = 'blue';
        }

        // Validasi Atasan & Cegah Siklus (Loop)
        if ($parentId && $parentId > 0) {
            // Pastikan parent ada di struktur ini
            $pStmt = mysqli_prepare($koneksi, "SELECT id FROM struktur_node WHERE id = ? AND struktur_id = ?");
            mysqli_stmt_bind_param($pStmt, "ii", $parentId, $strukturId);
            mysqli_stmt_execute($pStmt);
            $pRes = mysqli_stmt_get_result($pStmt);
            $pExists = mysqli_fetch_assoc($pRes);
            mysqli_stmt_close($pStmt);

            if (!$pExists) {
                json_response(false, 'Atasan yang dipilih tidak ditemukan dalam bagan ini.');
            }

            if ($nodeId > 0 && struktur_check_cycle($koneksi, $nodeId, $parentId)) {
                json_response(false, 'Hubungan atasan tidak valid karena dapat membentuk perulangan hierarki (loop/siklus).');
            }
        } else {
            $parentId = null;
        }

        // Validasi gaya kustom node
        $cleanGaya = struktur_validate_node_style($gayaJsonRaw);
        $gayaJsonStr = $cleanGaya ? json_encode($cleanGaya) : null;

        // Ambil data foto lama jika update
        $oldFoto = null;
        if ($nodeId > 0) {
            $oldStmt = mysqli_prepare($koneksi, "SELECT foto FROM struktur_node WHERE id = ? AND struktur_id = ?");
            mysqli_stmt_bind_param($oldStmt, "ii", $nodeId, $strukturId);
            mysqli_stmt_execute($oldStmt);
            $oldRes = mysqli_stmt_get_result($oldStmt);
            $oldRow = mysqli_fetch_assoc($oldRes);
            mysqli_stmt_close($oldStmt);
            if (!$oldRow) {
                json_response(false, 'Node jabatan yang ingin diubah tidak ditemukan.');
            }
            $oldFoto = $oldRow['foto'];
        }

        // Penanganan Upload Foto
        $fotoName = $oldFoto;
        if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] == '1') {
            if ($oldFoto && file_exists(__DIR__ . '/uploads/struktur/' . $oldFoto)) {
                @unlink(__DIR__ . '/uploads/struktur/' . $oldFoto);
            }
            $fotoName = null;
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $maxSize) {
                json_response(false, 'Ukuran foto maksimal adalah 2 MB.');
            }

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts, true)) {
                json_response(false, 'Format foto tidak valid. Gunakan JPG, PNG, atau WebP.');
            }

            // Validasi MIME type menggunakan finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowedMimes, true)) {
                json_response(false, 'Tipe berkas foto tidak valid.');
            }

            // Hapus foto lama jika ada
            if ($oldFoto && file_exists(__DIR__ . '/uploads/struktur/' . $oldFoto)) {
                @unlink(__DIR__ . '/uploads/struktur/' . $oldFoto);
            }

            $targetDir = __DIR__ . '/uploads/struktur/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $newFotoName = 'node_' . $strukturId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $targetDir . $newFotoName)) {
                $fotoName = $newFotoName;
            }
        }

        if ($nodeId > 0) {
            // UPDATE
            $upSql = "UPDATE struktur_node 
                      SET parent_id = ?, jabatan = ?, nama = ?, keterangan = ?, 
                          siswa_id = ?, foto = ?, warna = ?, gaya_json = ?
                      WHERE id = ? AND struktur_id = ?";
            $upStmt = mysqli_prepare($koneksi, $upSql);
            mysqli_stmt_bind_param($upStmt, "isssisssii", 
                $parentId, $jabatan, $nama, $keterangan, 
                $siswaId, $fotoName, $warna, $gayaJsonStr, 
                $nodeId, $strukturId
            );
            $exec = mysqli_stmt_execute($upStmt);
            mysqli_stmt_close($upStmt);

            if (!$exec) {
                json_response(false, 'Gagal memperbarui data node jabatan: ' . mysqli_error($koneksi));
            }
            $targetId = $nodeId;
            $msg = 'Node jabatan berhasil diperbarui.';
        } else {
            // INSERT
            // Tentukan koordinat awal: di dekat parent atau di posisi strategis
            $posX = 460;
            $posY = 100;
            if ($parentId) {
                $pPosStmt = mysqli_prepare($koneksi, "SELECT pos_x, pos_y FROM struktur_node WHERE id = ?");
                mysqli_stmt_bind_param($pPosStmt, "i", $parentId);
                mysqli_stmt_execute($pPosStmt);
                $pPosRes = mysqli_stmt_get_result($pPosStmt);
                $pPos = mysqli_fetch_assoc($pPosRes);
                mysqli_stmt_close($pPosStmt);
                if ($pPos) {
                    $posX = intval($pPos['pos_x']) + (rand(-40, 40));
                    $posY = intval($pPos['pos_y']) + 140;
                }
            } else {
                // Cari posisi terbawah
                $maxStmt = mysqli_prepare($koneksi, "SELECT MAX(pos_y) AS max_y FROM struktur_node WHERE struktur_id = ?");
                mysqli_stmt_bind_param($maxStmt, "i", $strukturId);
                mysqli_stmt_execute($maxStmt);
                $maxRes = mysqli_stmt_get_result($maxStmt);
                $maxRow = mysqli_fetch_assoc($maxRes);
                mysqli_stmt_close($maxStmt);
                if ($maxRow && $maxRow['max_y']) {
                    $posY = intval($maxRow['max_y']) + 120;
                }
            }

            $inSql = "INSERT INTO struktur_node 
                      (struktur_id, parent_id, jabatan, nama, keterangan, siswa_id, foto, warna, gaya_json, pos_x, pos_y, urutan)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $inStmt = mysqli_prepare($koneksi, $inSql);
            mysqli_stmt_bind_param($inStmt, "iisssisssii",
                $strukturId, $parentId, $jabatan, $nama, $keterangan,
                $siswaId, $fotoName, $warna, $gayaJsonStr, $posX, $posY
            );
            $exec = mysqli_stmt_execute($inStmt);
            $targetId = $exec ? mysqli_insert_id($koneksi) : 0;
            mysqli_stmt_close($inStmt);

            if (!$exec || $targetId <= 0) {
                json_response(false, 'Gagal menambahkan node jabatan baru: ' . mysqli_error($koneksi));
            }
            $msg = 'Node jabatan baru berhasil ditambahkan.';
        }

        // Ambil data node lengkap yang sudah tersimpan
        $nodeStmt = mysqli_prepare($koneksi, "SELECT sn.*, s.nama AS nama_siswa, s.nis AS nis, k.nama AS nama_kelas 
                                             FROM struktur_node sn
                                             LEFT JOIN siswa s ON sn.siswa_id = s.id
                                             LEFT JOIN kelas k ON s.kelas_id = k.id
                                             WHERE sn.id = ?");
        mysqli_stmt_bind_param($nodeStmt, "i", $targetId);
        mysqli_stmt_execute($nodeStmt);
        $nodeRes = mysqli_stmt_get_result($nodeStmt);
        $savedNode = mysqli_fetch_assoc($nodeRes);
        mysqli_stmt_close($nodeStmt);

        json_response(true, $msg, ['node' => $savedNode]);
        break;

    // -------------------------------------------------------------------------
    // 3. Hapus Node (Pilihan 'reparent' atau 'cascade')
    // -------------------------------------------------------------------------
    case 'delete_node':
        $nodeId = intval($_POST['node_id'] ?? 0);
        $deleteAction = ($_POST['delete_action'] ?? '') === 'cascade' ? 'cascade' : 'reparent';

        if ($nodeId <= 0) {
            json_response(false, 'ID node yang ingin dihapus tidak valid.');
        }

        $res = struktur_delete_node($koneksi, $nodeId, $strukturId, $deleteAction);
        json_response($res['ok'], $res['pesan']);
        break;

    // -------------------------------------------------------------------------
    // 4. Terapkan Gaya ke Seluruh Node
    // -------------------------------------------------------------------------
    case 'apply_style_all':
        $gayaJsonRaw = $_POST['gaya_json'] ?? null;
        $cleanGaya = struktur_validate_node_style($gayaJsonRaw);

        $res = struktur_apply_style_all($koneksi, $strukturId, $cleanGaya);
        if ($res) {
            json_response(true, 'Gaya tampilan berhasil diterapkan ke seluruh node dalam bagan ini.');
        } else {
            json_response(false, 'Gagal menerapkan gaya ke seluruh node.');
        }
        break;

    // -------------------------------------------------------------------------
    // 5. Simpan Pengaturan Bagan Global
    // -------------------------------------------------------------------------
    case 'save_chart_settings':
        $gayaBaganRaw = $_POST['gaya_bagan'] ?? null;
        $res = struktur_save_chart_settings($koneksi, $strukturId, $gayaBaganRaw);
        if ($res) {
            json_response(true, 'Pengaturan tampilan bagan berhasil disimpan.');
        } else {
            json_response(false, 'Gagal menyimpan pengaturan tampilan bagan.');
        }
        break;

    // -------------------------------------------------------------------------
    // 6. Ambil Data Node Terbaru (untuk Sinkronisasi / Undo-Redo / Refresh)
    // -------------------------------------------------------------------------
    case 'get_data':
        $nodes = struktur_get_nodes($koneksi, $strukturId);
        $st = struktur_get_by_id($koneksi, $strukturId);
        json_response(true, 'Data berhasil dimuat.', [
            'nodes' => $nodes,
            'gaya_bagan' => $st ? json_decode($st['gaya_json'] ?? '{}', true) : []
        ]);
        break;

    default:
        json_response(false, 'Aksi tidak dikenali.');
        break;
}
