<?php
/**
 * lib/tabel.php - Komponen Reusable Tabel: Pencarian, Filter, Pengurutan, dan Pagination
 * Mematuhi AGENTS.md:
 * - 100% Prepared Statements (tidak ada variabel luar disambung langsung ke SQL)
 * - Whitelist kolom pengurutan dan filter
 * - Output selalu di-escape dengan htmlspecialchars
 * - Ringan, tanpa framework/dependensi
 */

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Membaca dan memvalidasi parameter tabel dari $_GET berdasarkan konfigurasi
 *
 * @param array $konfig Konfigurasi tabel per halaman
 * @return array Parameter yang sudah tervalidasi dan aman
 */
function tabel_params(array $konfig) {
    // 1. Pencarian teks (q)
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // 2. Kolom pengurutan (urut) & Arah (arah)
    $kolomUrutWhitelist = array_keys($konfig['kolom_urut'] ?? []);
    $defaultUrut = $konfig['default_urut'] ?? ($kolomUrutWhitelist[0] ?? 'id');
    $urut = isset($_GET['urut']) && in_array($_GET['urut'], $kolomUrutWhitelist, true)
        ? $_GET['urut']
        : $defaultUrut;

    $defaultArah = strtolower($konfig['default_arah'] ?? 'asc');
    if (!in_array($defaultArah, ['asc', 'desc'], true)) {
        $defaultArah = 'asc';
    }
    $arah = isset($_GET['arah']) && in_array(strtolower($_GET['arah']), ['asc', 'desc'], true)
        ? strtolower($_GET['arah'])
        : $defaultArah;

    // 3. Jumlah per halaman (per_halaman)
    $perHalamanPilihan = $konfig['per_halaman_pilihan'] ?? [10, 25, 50];
    $defaultPerHalaman = $konfig['default_per_halaman'] ?? 10;
    $perHalaman = isset($_GET['per_halaman']) ? intval($_GET['per_halaman']) : $defaultPerHalaman;
    if (!in_array($perHalaman, $perHalamanPilihan, true)) {
        $perHalaman = $defaultPerHalaman;
    }

    // 4. Nomor halaman (hal)
    $hal = isset($_GET['hal']) ? max(1, intval($_GET['hal'])) : 1;

    // 5. Filter dinamis sesuai konfigurasi
    $filterValues = [];
    if (!empty($konfig['filters']) && is_array($konfig['filters'])) {
        foreach ($konfig['filters'] as $fKey => $fDef) {
            if (isset($_GET[$fKey]) && $_GET[$fKey] !== '') {
                $val = trim($_GET[$fKey]);
                // Validasi tipe jika didefinisikan
                if (($fDef['tipe'] ?? '') === 'int') {
                    $val = intval($val);
                    if ($val > 0) {
                        $filterValues[$fKey] = $val;
                    }
                } else {
                    $filterValues[$fKey] = $val;
                }
            }
        }
    }

    // 6. Parameter ekstra untuk dipertahankan di URL (mis. tab=kelompok)
    $extra = [];
    if (!empty($konfig['extra_params']) && is_array($konfig['extra_params'])) {
        foreach ($konfig['extra_params'] as $exKey) {
            if (isset($_GET[$exKey])) {
                $extra[$exKey] = $_GET[$exKey];
            }
        }
    }

    return [
        'q' => $q,
        'urut' => $urut,
        'arah' => $arah,
        'per_halaman' => $perHalaman,
        'hal' => $hal,
        'filters' => $filterValues,
        'extra' => $extra
    ];
}

/**
 * Membangun klausa WHERE, ORDER BY, dan LIMIT/OFFSET untuk prepared statement
 *
 * @param array $konfig
 * @param array $params
 * @return array ['where' => string, 'order' => string, 'limit' => string, 'types' => string, 'bind_params' => array, 'offset' => int]
 */
function tabel_bangun_query(array $konfig, array $params) {
    $whereClauses = [];
    $types = '';
    $bindParams = [];

    // Filter pencarian teks (q)
    if ($params['q'] !== '' && !empty($konfig['kolom_cari'])) {
        $searchClauses = [];
        $searchTerm = '%' . $params['q'] . '%';
        foreach ($konfig['kolom_cari'] as $colSql) {
            $searchClauses[] = "$colSql LIKE ?";
            $types .= 's';
            $bindParams[] = $searchTerm;
        }
        $whereClauses[] = '(' . implode(' OR ', $searchClauses) . ')';
    }

    // Filter dropdown spesifik
    if (!empty($konfig['filters']) && !empty($params['filters'])) {
        foreach ($konfig['filters'] as $fKey => $fDef) {
            if (isset($params['filters'][$fKey])) {
                $colSql = $fDef['sql_kolom'];
                $val = $params['filters'][$fKey];
                $tipe = $fDef['tipe'] ?? 'string';

                $whereClauses[] = "$colSql = ?";
                if ($tipe === 'int') {
                    $types .= 'i';
                    $bindParams[] = intval($val);
                } else {
                    $types .= 's';
                    $bindParams[] = (string)$val;
                }
            }
        }
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    // Klausa ORDER BY dari whitelist
    $orderSqlCol = $konfig['kolom_urut'][$params['urut']] ?? array_values($konfig['kolom_urut'])[0];
    $arahSql = strtoupper($params['arah']) === 'DESC' ? 'DESC' : 'ASC';
    $orderSql = " ORDER BY $orderSqlCol $arahSql";

    // Klausa LIMIT dan OFFSET
    $offset = ($params['hal'] - 1) * $params['per_halaman'];
    $limitSql = " LIMIT ?, ?";
    $limitTypes = 'ii';
    $limitParams = [$offset, $params['per_halaman']];

    return [
        'where' => $whereSql,
        'order' => $orderSql,
        'limit' => $limitSql,
        'types' => $types,
        'bind_params' => $bindParams,
        'limit_types' => $limitTypes,
        'limit_params' => $limitParams,
        'offset' => $offset
    ];
}

/**
 * Menghitung total baris yang cocok sesudah filter dan menyesuaikan nomor halaman jika out of bounds
 *
 * @param mysqli $koneksi
 * @param string $countQuerySql Query SELECT COUNT(*) ... tanpa ORDER BY dan LIMIT
 * @param string $types Tipe parameter WHERE
 * @param array $bindParams Parameter WHERE
 * @param array &$params Parameter tabel (nomor halaman akan disesuaikan jika perlu)
 * @return int Total data
 */
function tabel_hitung_total($koneksi, $countQuerySql, $types, array $bindParams, array &$params) {
    $stmt = mysqli_prepare($koneksi, $countQuerySql);
    if (!$stmt) {
        return 0;
    }

    if (!empty($types) && !empty($bindParams)) {
        mysqli_stmt_bind_param($stmt, $types, ...$bindParams);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_row($res);
    $total = $row ? intval($row[0]) : 0;
    mysqli_stmt_close($stmt);

    // Validasi rentang halaman: jika hal melebihi total halaman, arahkan ke halaman terakhir
    $totalHalaman = max(1, intval(ceil($total / max(1, $params['per_halaman']))));
    if ($params['hal'] > $totalHalaman) {
        $params['hal'] = $totalHalaman;
    }

    return $total;
}

/**
 * Menghasilkan URL dengan query string yang diperbarui
 *
 * @param array $params
 * @param array $override
 * @return string
 */
function tabel_url(array $params, array $override = []) {
    $merged = [];

    // Sertakan extra params (mis. tab=kelompok)
    if (!empty($params['extra'])) {
        foreach ($params['extra'] as $k => $v) {
            $merged[$k] = $v;
        }
    }

    // Sertakan filter
    if (!empty($params['filters'])) {
        foreach ($params['filters'] as $k => $v) {
            $merged[$k] = $v;
        }
    }

    if ($params['q'] !== '') {
        $merged['q'] = $params['q'];
    }

    $merged['urut'] = $params['urut'];
    $merged['arah'] = $params['arah'];
    $merged['per_halaman'] = $params['per_halaman'];
    $merged['hal'] = $params['hal'];

    foreach ($override as $k => $v) {
        if ($v === null || $v === '') {
            unset($merged[$k]);
        } else {
            $merged[$k] = $v;
        }
    }

    $page = basename($_SERVER['PHP_SELF']);
    return $page . (!empty($merged) ? '?' . http_build_query($merged) : '');
}

/**
 * Render Header Kolom dengan Tautan Pengurutan Naik/Turun
 *
 * @param string $kolom Key kolom urut sesuai whitelist
 * @param string $label Label judul kolom
 * @param array $params
 * @return string HTML <th> dengan tautan urut dan ikon
 */
function tabel_th_urut($kolom, $label, array $params) {
    $isAktif = ($params['urut'] === $kolom);
    $arahSaatIni = strtolower($params['arah']);
    $arahBerikutnya = ($isAktif && $arahSaatIni === 'asc') ? 'desc' : 'asc';

    $url = tabel_url($params, [
        'urut' => $kolom,
        'arah' => $arahBerikutnya,
        'hal' => 1 // Reset ke halaman 1 saat pengurutan diubah
    ]);

    $ikon = 'fa-sort text-muted opacity-50';
    if ($isAktif) {
        $ikon = ($arahSaatIni === 'desc') ? 'fa-sort-down text-primary' : 'fa-sort-up text-primary';
    }

    return '<th class="th-urut ' . ($isAktif ? 'th-urut-aktif' : '') . '">' .
           '<a href="' . e($url) . '" class="th-urut-link d-flex align-items-center justify-content-between text-decoration-none text-reset">' .
           '<span>' . e($label) . '</span>' .
           '<i class="fas ' . $ikon . ' ms-1"></i>' .
           '</a></th>';
}

/**
 * Render Komponen Filter: Kotak Pencarian, Dropdown Filter, Pilihan Per Halaman, & Tombol Reset
 *
 * @param array $konfig
 * @param array $params
 * @param string $placeholder
 * @return string HTML baris filter
 */
function tabel_render_filter(array $konfig, array $params, $placeholder = 'Cari data...') {
    $page = basename($_SERVER['PHP_SELF']);
    $html = '<div class="tabel-filter-container mb-3">';
    $html .= '<form method="GET" action="' . e($page) . '" class="tabel-filter-form">';

    // Input hidden untuk extra params (mis. tab=kelompok)
    if (!empty($params['extra'])) {
        foreach ($params['extra'] as $k => $v) {
            $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
    }
    // Pertahankan kolom urut & arah
    $html .= '<input type="hidden" name="urut" value="' . e($params['urut']) . '">';
    $html .= '<input type="hidden" name="arah" value="' . e($params['arah']) . '">';
    // Reset halaman ke 1 saat submit filter
    $html .= '<input type="hidden" name="hal" value="1">';

    $html .= '<div class="tabel-filter-row d-flex flex-wrap align-items-center gap-2">';

    // 1. Input Pencarian Teks
    $html .= '<div class="tabel-filter-col tabel-filter-search flex-grow-1">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>';
    $html .= '<input type="text" name="q" class="form-control form-control-sm border-start-0 ps-0" placeholder="' . e($placeholder) . '" value="' . e($params['q']) . '" autocomplete="off">';
    if ($params['q'] !== '') {
        $resetSearchUrl = tabel_url($params, ['q' => null, 'hal' => 1]);
        $html .= '<a href="' . e($resetSearchUrl) . '" class="input-group-text bg-white text-muted text-decoration-none" title="Hapus pencarian"><i class="fas fa-times"></i></a>';
    }
    $html .= '</div>';
    $html .= '</div>';

    // 2. Dropdown Filter Dinamis (mis. Kelas, dsb.)
    if (!empty($konfig['filters'])) {
        foreach ($konfig['filters'] as $fKey => $fDef) {
            $valAktif = $params['filters'][$fKey] ?? '';
            $html .= '<div class="tabel-filter-col tabel-filter-select">';
            $html .= '<select name="' . e($fKey) . '" class="form-select form-select-sm">';
            $html .= '<option value="">' . e($fDef['label'] ?? 'Semua') . '</option>';
            if (!empty($fDef['options'])) {
                foreach ($fDef['options'] as $opt) {
                    $selected = (string)$opt['value'] === (string)$valAktif ? ' selected' : '';
                    $html .= '<option value="' . e($opt['value']) . '"' . $selected . '>' . e($opt['label']) . '</option>';
                }
            }
            $html .= '</select>';
            $html .= '</div>';
        }
    }

    // 3. Pilihan Per Halaman (10/25/50)
    $perHalamanPilihan = $konfig['per_halaman_pilihan'] ?? [10, 25, 50];
    $html .= '<div class="tabel-filter-col tabel-filter-perpage">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<span class="input-group-text bg-light text-muted small"><i class="fas fa-list me-1"></i> Baris:</span>';
    $html .= '<select name="per_halaman" class="form-select form-select-sm">';
    foreach ($perHalamanPilihan as $optPer) {
        $sel = ($params['per_halaman'] == $optPer) ? ' selected' : '';
        $html .= '<option value="' . $optPer . '"' . $sel . '>' . $optPer . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';

    // 4. Tombol Terapkan & Reset Filter
    $adaFilterAktif = ($params['q'] !== '') || !empty($params['filters']);
    $html .= '<div class="tabel-filter-col tabel-filter-actions d-flex gap-1">';
    $html .= '<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> Terapkan</button>';

    if ($adaFilterAktif) {
        $resetUrl = tabel_url($params, array_merge(
            ['q' => null, 'hal' => 1],
            array_fill_keys(array_keys($konfig['filters'] ?? []), null)
        ));
        $html .= '<a href="' . e($resetUrl) . '" class="btn btn-outline-secondary btn-sm" title="Hapus semua filter"><i class="fas fa-undo me-1"></i> Reset</a>';
    }
    $html .= '</div>';

    $html .= '</div>'; // .tabel-filter-row
    $html .= '</form>';
    $html .= '</div>'; // .tabel-filter-container

    return $html;
}

/**
 * Render Pagination & Keterangan Jumlah Data
 *
 * @param array $params
 * @param int $total Total baris data sesudah filter
 * @return string HTML pagination
 */
function tabel_render_pagination(array $params, $total) {
    $perHalaman = max(1, $params['per_halaman']);
    $totalHalaman = max(1, intval(ceil($total / $perHalaman)));
    $hal = max(1, min($totalHalaman, $params['hal']));

    // Hitung range tampilan baris (mis. "Menampilkan 11-20 dari 87 data")
    if ($total === 0) {
        $infoTeks = 'Menampilkan 0 data';
    } else {
        $awal = ($hal - 1) * $perHalaman + 1;
        $akhir = min($total, $hal * $perHalaman);
        $infoTeks = "Menampilkan <strong>$awal-$akhir</strong> dari <strong>$total</strong> data";
    }

    $html = '<div class="tabel-pagination-container d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">';
    $html .= '<div class="tabel-pagination-info text-muted small">' . $infoTeks . '</div>';

    // Sembunyikan tombol navigasi jika hanya 1 halaman atau 0 data
    if ($totalHalaman <= 1) {
        $html .= '</div>';
        return $html;
    }

    $html .= '<nav aria-label="Navigasi Halaman Data">';
    $html .= '<ul class="pagination pagination-sm mb-0">';

    // Tombol Sebelumnya
    $prevDisabled = ($hal <= 1);
    $prevUrl = $prevDisabled ? '#' : tabel_url($params, ['hal' => $hal - 1]);
    $html .= '<li class="page-item ' . ($prevDisabled ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . e($prevUrl) . '" tabindex="' . ($prevDisabled ? '-1' : '0') . '" aria-label="Sebelumnya">';
    $html .= '<i class="fas fa-chevron-left"></i> <span class="d-none d-sm-inline ms-1">Sebelumnya</span>';
    $html .= '</a></li>';

    // Nomor Halaman dengan Elipsis Cerdas
    // Tampilkan selalu halaman 1, halaman terakhir, dan 1-2 halaman di sekitar halaman aktif
    $delta = 1; // rentang kiri dan kanan
    $range = [];
    for ($i = max(2, $hal - $delta); $i <= min($totalHalaman - 1, $hal + $delta); $i++) {
        $range[] = $i;
    }

    // Halaman 1
    $aktif1 = ($hal === 1) ? ' active' : '';
    $html .= '<li class="page-item' . $aktif1 . '"><a class="page-link" href="' . e(tabel_url($params, ['hal' => 1])) . '">1</a></li>';

    // Elipsis kiri
    if (!empty($range) && $range[0] > 2) {
        $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
    }

    // Nomor rentang tengah
    foreach ($range as $pageNumber) {
        $isAktif = ($hal === $pageNumber) ? ' active' : '';
        $html .= '<li class="page-item' . $isAktif . '"><a class="page-link" href="' . e(tabel_url($params, ['hal' => $pageNumber])) . '">' . $pageNumber . '</a></li>';
    }

    // Elipsis kanan
    if (!empty($range) && end($range) < $totalHalaman - 1) {
        $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
    }

    // Halaman Terakhir (jika total halaman > 1)
    if ($totalHalaman > 1) {
        $aktifAkhir = ($hal === $totalHalaman) ? ' active' : '';
        $html .= '<li class="page-item' . $aktifAkhir . '"><a class="page-link" href="' . e(tabel_url($params, ['hal' => $totalHalaman])) . '">' . $totalHalaman . '</a></li>';
    }

    // Tombol Berikutnya
    $nextDisabled = ($hal >= $totalHalaman);
    $nextUrl = $nextDisabled ? '#' : tabel_url($params, ['hal' => $hal + 1]);
    $html .= '<li class="page-item ' . ($nextDisabled ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . e($nextUrl) . '" tabindex="' . ($nextDisabled ? '-1' : '0') . '" aria-label="Berikutnya">';
    $html .= '<span class="d-none d-sm-inline me-1">Berikutnya</span> <i class="fas fa-chevron-right"></i>';
    $html .= '</a></li>';

    $html .= '</ul>';
    $html .= '</nav>';
    $html .= '</div>'; // .tabel-pagination-container

    return $html;
}

/**
 * Render Pesan Kosong Bila Tidak Ada Data yang Cocok
 *
 * @param string $pesan Pesan utama
 * @param string|null $resetUrl Tautan reset filter
 * @param int $colspan Lebar colspan tabel
 * @return string HTML <tr> baris kosong
 */
function tabel_render_kosong($pesan = 'Tidak ada data yang cocok dengan kriteria pencarian.', $resetUrl = null, $colspan = 5) {
    $html = '<tr><td colspan="' . intval($colspan) . '" class="text-center py-4">';
    $html .= '<div class="tabel-kosong-box d-flex flex-column align-items-center justify-content-center">';
    $html .= '<i class="fas fa-search fa-2x text-muted mb-2 opacity-50"></i>';
    $html .= '<p class="text-muted mb-2">' . e($pesan) . '</p>';
    if ($resetUrl) {
        $html .= '<a href="' . e($resetUrl) . '" class="btn btn-outline-primary btn-sm"><i class="fas fa-undo me-1"></i> Reset Filter & Pencarian</a>';
    }
    $html .= '</div>';
    $html .= '</td></tr>';
    return $html;
}
