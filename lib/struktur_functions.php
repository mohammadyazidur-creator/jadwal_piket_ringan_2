<?php
/**
 * lib/struktur_functions.php
 * Kumpulan fungsi backend untuk modul Struktur Organisasi
 * Mematuhi aturan AGENTS.md: Prepared Statement, Escape HTML, CSRF Protection, Tanpa Duplikasi.
 */

require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper untuk escape HTML output
 *
 * @param mixed $str
 * @return string
 */
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Menghasilkan token CSRF unik per sesi
 *
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memvalidasi token CSRF dari form / header AJAX
 *
 * @param string|null $token
 * @return bool
 */
function csrf_validate($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Mengambil daftar seluruh bagan struktur organisasi
 * Beserta nama kelas dan jumlah node yang dimiliki.
 *
 * @param mysqli $koneksi
 * @return array
 */
function struktur_get_all($koneksi) {
    $sql = "SELECT s.id, s.judul, s.kelas_id, s.dibuat_pada, 
                   k.nama AS nama_kelas,
                   COUNT(sn.id) AS jumlah_node
            FROM struktur s
            LEFT JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN struktur_node sn ON s.id = sn.struktur_id
            GROUP BY s.id, s.judul, s.kelas_id, s.dibuat_pada, k.nama
            ORDER BY s.id DESC";

    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Mengambil satu bagan struktur berdasarkan ID
 *
 * @param mysqli $koneksi
 * @param int $id
 * @return array|null
 */
function struktur_get_by_id($koneksi, $id) {
    $sql = "SELECT s.id, s.judul, s.kelas_id, s.gaya_json, s.dibuat_pada, k.nama AS nama_kelas
            FROM struktur s
            LEFT JOIN kelas k ON s.kelas_id = k.id
            WHERE s.id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Membuat bagan struktur organisasi baru
 *
 * @param mysqli $koneksi
 * @param string $judul
 * @param int|null $kelas_id
 * @return int ID struktur yang baru dibuat, atau 0 bila gagal
 */
function struktur_create($koneksi, $judul, $kelas_id = null) {
    $sql = "INSERT INTO struktur (judul, kelas_id, dibuat_pada) VALUES (?, ?, NOW())";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return 0;
    }

    $kelasParam = ($kelas_id && $kelas_id > 0) ? $kelas_id : null;
    mysqli_stmt_bind_param($stmt, "si", $judul, $kelasParam);
    $success = mysqli_stmt_execute($stmt);
    $newId = $success ? mysqli_insert_id($koneksi) : 0;
    mysqli_stmt_close($stmt);

    return $newId;
}

/**
 * Mengubah judul dan kelas_id dari bagan struktur
 *
 * @param mysqli $koneksi
 * @param int $id
 * @param string $judul
 * @param int|null $kelas_id
 * @return bool
 */
function struktur_update($koneksi, $id, $judul, $kelas_id = null) {
    $sql = "UPDATE struktur SET judul = ?, kelas_id = ? WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }

    $kelasParam = ($kelas_id && $kelas_id > 0) ? $kelas_id : null;
    mysqli_stmt_bind_param($stmt, "sii", $judul, $kelasParam, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success;
}

/**
 * Menghapus bagan struktur (cascade akan menghapus semua node-nya)
 *
 * @param mysqli $koneksi
 * @param int $id
 * @return bool
 */
function struktur_delete($koneksi, $id) {
    $sql = "DELETE FROM struktur WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success;
}

/**
 * Mengambil daftar kelas untuk dropdown pilihan
 *
 * @param mysqli $koneksi
 * @return array
 */
function struktur_get_kelas_list($koneksi) {
    $sql = "SELECT id, nama FROM kelas ORDER BY nama ASC";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Mengambil semua node dari suatu struktur
 *
 * @param mysqli $koneksi
 * @param int $struktur_id
 * @return array
 */
function struktur_get_nodes($koneksi, $struktur_id) {
    $sql = "SELECT sn.*, s.nama AS nama_siswa, s.nis AS nis, k.nama AS nama_kelas
            FROM struktur_node sn
            LEFT JOIN siswa s ON sn.siswa_id = s.id
            LEFT JOIN kelas k ON s.kelas_id = k.id
            WHERE sn.struktur_id = ?
            ORDER BY sn.urutan ASC, sn.id ASC";

    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $struktur_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $nodes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $nodes[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $nodes;
}

/**
 * Mengambil daftar siswa untuk dropdown pemilih
 *
 * @param mysqli $koneksi
 * @param int|null $kelas_id
 * @return array
 */
function struktur_get_siswa_list($koneksi, $kelas_id = null) {
    if ($kelas_id && $kelas_id > 0) {
        $sql = "SELECT s.id, s.nama, s.nis, s.kelas_id, k.nama AS nama_kelas
                FROM siswa s
                LEFT JOIN kelas k ON s.kelas_id = k.id
                WHERE s.kelas_id = ?
                ORDER BY s.nama ASC";
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, "i", $kelas_id);
    } else {
        $sql = "SELECT s.id, s.nama, s.nis, s.kelas_id, k.nama AS nama_kelas
                FROM siswa s
                LEFT JOIN kelas k ON s.kelas_id = k.id
                ORDER BY k.nama ASC, s.nama ASC";
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) return [];
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $list;
}

/**
 * Validasi dan sanitasi data gaya visual per node
 * Sesuai aturan: whitelist font, ukuran 10-24, warna #RRGGBB / variabel CSS
 *
 * @param mixed $gaya
 * @return array|null
 */
function struktur_validate_node_style($gaya) {
    if (empty($gaya)) {
        return null;
    }
    if (is_string($gaya)) {
        $decoded = json_decode($gaya, true);
        if (!is_array($decoded)) {
            return null;
        }
        $gaya = $decoded;
    }

    $validFonts = ['Inter', 'Segoe UI', 'system-ui', 'Georgia', 'Arial', 'Courier New', 'monospace'];
    $validAligns = ['left', 'center', 'right'];
    $validShapes = ['rounded', 'square', 'pill'];
    $validWidths = ['small', 'medium', 'large'];
    $validColorKeys = ['blue', 'emerald', 'green', 'amber', 'purple', 'rose', 'slate', 'primary-blue', 'white', 'dark-blue', 'light-blue'];

    $clean = [];

    // Font family
    if (!empty($gaya['fontFamily']) && in_array($gaya['fontFamily'], $validFonts, true)) {
        $clean['fontFamily'] = $gaya['fontFamily'];
    }

    // Font size (10 - 24)
    if (isset($gaya['fontSize'])) {
        $size = intval($gaya['fontSize']);
        if ($size >= 10 && $size <= 24) {
            $clean['fontSize'] = $size;
        }
    }

    // Text decoration
    if (isset($gaya['fontWeight'])) {
        $clean['fontWeight'] = ($gaya['fontWeight'] === 'bold' || $gaya['fontWeight'] === true || $gaya['fontWeight'] === '700') ? 'bold' : 'normal';
    }
    if (isset($gaya['fontStyle'])) {
        $clean['fontStyle'] = ($gaya['fontStyle'] === 'italic' || $gaya['fontStyle'] === true) ? 'italic' : 'normal';
    }
    if (!empty($gaya['textAlign']) && in_array($gaya['textAlign'], $validAligns, true)) {
        $clean['textAlign'] = $gaya['textAlign'];
    }

    // Color validator helper
    $validateColor = function($val) use ($validColorKeys) {
        if (!is_string($val)) return null;
        $val = trim($val);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
            return $val;
        }
        $valLower = strtolower($val);
        if (in_array($valLower, $validColorKeys, true)) {
            return $valLower;
        }
        return null;
    };

    if (!empty($gaya['colorHeader']) && ($c = $validateColor($gaya['colorHeader']))) {
        $clean['colorHeader'] = $c;
    }
    if (!empty($gaya['colorBg']) && ($c = $validateColor($gaya['colorBg']))) {
        $clean['colorBg'] = $c;
    }
    if (!empty($gaya['colorText']) && ($c = $validateColor($gaya['colorText']))) {
        $clean['colorText'] = $c;
    }
    if (!empty($gaya['colorBorder']) && ($c = $validateColor($gaya['colorBorder']))) {
        $clean['colorBorder'] = $c;
    }

    // Shape & Dimensions
    if (!empty($gaya['shape']) && in_array($gaya['shape'], $validShapes, true)) {
        $clean['shape'] = $gaya['shape'];
    }
    if (!empty($gaya['width']) && in_array($gaya['width'], $validWidths, true)) {
        $clean['width'] = $gaya['width'];
    }
    if (isset($gaya['showIcon'])) {
        $clean['showIcon'] = (bool)$gaya['showIcon'];
    }

    return !empty($clean) ? $clean : null;
}

/**
 * Validasi dan sanitasi konfigurasi tampilan bagan global
 *
 * @param mixed $gaya
 * @return array
 */
function struktur_validate_chart_style($gaya) {
    if (empty($gaya)) {
        return [
            'lineStyle' => 'curve',
            'lineWidth' => 2,
            'lineColor' => 'primary-blue',
            'grid' => true
        ];
    }
    if (is_string($gaya)) {
        $decoded = json_decode($gaya, true);
        $gaya = is_array($decoded) ? $decoded : [];
    }

    $validLineStyles = ['curve', 'step', 'straight'];
    $validColorKeys = ['blue', 'emerald', 'green', 'amber', 'purple', 'rose', 'slate', 'primary-blue', 'dark-blue'];

    $clean = [];

    // Line style
    $clean['lineStyle'] = (!empty($gaya['lineStyle']) && in_array($gaya['lineStyle'], $validLineStyles, true))
        ? $gaya['lineStyle']
        : 'curve';

    // Line width (1 - 6)
    $w = isset($gaya['lineWidth']) ? intval($gaya['lineWidth']) : 2;
    $clean['lineWidth'] = max(1, min(6, $w));

    // Line color
    $clean['lineColor'] = 'primary-blue';
    if (!empty($gaya['lineColor'])) {
        $lc = trim($gaya['lineColor']);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $lc)) {
            $clean['lineColor'] = $lc;
        } elseif (in_array(strtolower($lc), $validColorKeys, true)) {
            $clean['lineColor'] = strtolower($lc);
        }
    }

    // Grid toggle
    $clean['grid'] = isset($gaya['grid']) ? (bool)$gaya['grid'] : true;

    return $clean;
}

/**
 * Deteksi siklus/loop dalam relasi hierarki
 * Mencegah sebuah node memilih dirinya sendiri atau turunannya sebagai parent.
 *
 * @param mysqli $koneksi
 * @param int $node_id
 * @param int|null $target_parent_id
 * @return bool True jika terdeteksi loop/siklus, False jika aman
 */
function struktur_check_cycle($koneksi, $node_id, $target_parent_id) {
    if ($node_id <= 0 || empty($target_parent_id)) {
        return false;
    }
    if ((int)$node_id === (int)$target_parent_id) {
        return true;
    }

    $current = (int)$target_parent_id;
    $visited = [];
    $stmt = mysqli_prepare($koneksi, "SELECT parent_id FROM struktur_node WHERE id = ?");

    while ($current > 0 && !in_array($current, $visited, true)) {
        if ($current === (int)$node_id) {
            mysqli_stmt_close($stmt);
            return true; // Loop terdeteksi
        }
        $visited[] = $current;
        mysqli_stmt_bind_param($stmt, "i", $current);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $current = ($row && !empty($row['parent_id'])) ? (int)$row['parent_id'] : 0;
    }

    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Simpan posisi seluruh node (batch update)
 *
 * @param mysqli $koneksi
 * @param int $struktur_id
 * @param array $positions Array of ['id' => ..., 'pos_x' => ..., 'pos_y' => ...]
 * @return bool
 */
function struktur_save_positions($koneksi, $struktur_id, $positions) {
    if (empty($positions) || !is_array($positions)) {
        return true;
    }

    $sql = "UPDATE struktur_node SET pos_x = ?, pos_y = ? WHERE id = ? AND struktur_id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_begin_transaction($koneksi);
    foreach ($positions as $pos) {
        $nodeId = intval($pos['id'] ?? 0);
        $posX = intval($pos['pos_x'] ?? 0);
        $posY = intval($pos['pos_y'] ?? 0);
        if ($nodeId > 0) {
            mysqli_stmt_bind_param($stmt, "iiii", $posX, $posY, $nodeId, $struktur_id);
            mysqli_stmt_execute($stmt);
        }
    }
    mysqli_commit($koneksi);
    mysqli_stmt_close($stmt);

    return true;
}

/**
 * Menyimpan pengaturan bagan global
 *
 * @param mysqli $koneksi
 * @param int $struktur_id
 * @param array $gaya_bagan
 * @return bool
 */
function struktur_save_chart_settings($koneksi, $struktur_id, $gaya_bagan) {
    $cleanStyle = struktur_validate_chart_style($gaya_bagan);
    $json = json_encode($cleanStyle);

    $sql = "UPDATE struktur SET gaya_json = ? WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $json, $struktur_id);
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $res;
}

/**
 * Menerapkan konfigurasi gaya ke seluruh node dalam suatu bagan
 *
 * @param mysqli $koneksi
 * @param int $struktur_id
 * @param array|null $gaya
 * @return bool
 */
function struktur_apply_style_all($koneksi, $struktur_id, $gaya) {
    $cleanStyle = struktur_validate_node_style($gaya);
    $json = $cleanStyle ? json_encode($cleanStyle) : null;

    $sql = "UPDATE struktur_node SET gaya_json = ? WHERE struktur_id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $json, $struktur_id);
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $res;
}

/**
 * Menghapus node dengan pilihan penanganan anak (reparent atau cascade)
 *
 * @param mysqli $koneksi
 * @param int $node_id
 * @param int $struktur_id
 * @param string $action 'reparent' atau 'cascade'
 * @return array ['ok' => bool, 'pesan' => string]
 */
function struktur_delete_node($koneksi, $node_id, $struktur_id, $action = 'reparent') {
    // Verifikasi node ada dan milik struktur ini
    $stmt = mysqli_prepare($koneksi, "SELECT id, parent_id, foto FROM struktur_node WHERE id = ? AND struktur_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $node_id, $struktur_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $node = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$node) {
        return ['ok' => false, 'pesan' => 'Node jabatan tidak ditemukan.'];
    }

    mysqli_begin_transaction($koneksi);

    if ($action === 'cascade') {
        // Hapus rekursif: kumpulkan seluruh ID turunan
        $idsToDelete = [$node_id];
        $queue = [$node_id];

        $childStmt = mysqli_prepare($koneksi, "SELECT id, foto FROM struktur_node WHERE parent_id = ? AND struktur_id = ?");
        while (!empty($queue)) {
            $currParent = array_shift($queue);
            mysqli_stmt_bind_param($childStmt, "ii", $currParent, $struktur_id);
            mysqli_stmt_execute($childStmt);
            $cRes = mysqli_stmt_get_result($childStmt);
            while ($cRow = mysqli_fetch_assoc($cRes)) {
                $idsToDelete[] = (int)$cRow['id'];
                $queue[] = (int)$cRow['id'];
                // Hapus file foto jika ada
                if (!empty($cRow['foto']) && file_exists(__DIR__ . '/../uploads/struktur/' . $cRow['foto'])) {
                    @unlink(__DIR__ . '/../uploads/struktur/' . $cRow['foto']);
                }
            }
        }
        mysqli_stmt_close($childStmt);

        // Hapus seluruh node dalam daftar
        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $delSql = "DELETE FROM struktur_node WHERE id IN ($placeholders) AND struktur_id = ?";
        $delStmt = mysqli_prepare($koneksi, $delSql);
        $types = str_repeat('i', count($idsToDelete)) . 'i';
        $params = array_merge($idsToDelete, [$struktur_id]);
        mysqli_stmt_bind_param($delStmt, $types, ...$params);
        mysqli_stmt_execute($delStmt);
        mysqli_stmt_close($delStmt);

    } else {
        // Default: 'reparent' -> Pindahkan anak ke atasan node ini
        $grandParent = !empty($node['parent_id']) ? intval($node['parent_id']) : null;
        if ($grandParent) {
            $repSql = "UPDATE struktur_node SET parent_id = ? WHERE parent_id = ? AND struktur_id = ?";
            $repStmt = mysqli_prepare($koneksi, $repSql);
            mysqli_stmt_bind_param($repStmt, "iii", $grandParent, $node_id, $struktur_id);
        } else {
            $repSql = "UPDATE struktur_node SET parent_id = NULL WHERE parent_id = ? AND struktur_id = ?";
            $repStmt = mysqli_prepare($koneksi, $repSql);
            mysqli_stmt_bind_param($repStmt, "ii", $node_id, $struktur_id);
        }
        mysqli_stmt_execute($repStmt);
        mysqli_stmt_close($repStmt);

        // Hapus node ini
        $delStmt = mysqli_prepare($koneksi, "DELETE FROM struktur_node WHERE id = ? AND struktur_id = ?");
        mysqli_stmt_bind_param($delStmt, "ii", $node_id, $struktur_id);
        mysqli_stmt_execute($delStmt);
        mysqli_stmt_close($delStmt);
    }

    // Hapus foto node utama
    if (!empty($node['foto']) && file_exists(__DIR__ . '/../uploads/struktur/' . $node['foto'])) {
        @unlink(__DIR__ . '/../uploads/struktur/' . $node['foto']);
    }

    mysqli_commit($koneksi);

    return ['ok' => true, 'pesan' => 'Node jabatan berhasil dihapus.'];
}
