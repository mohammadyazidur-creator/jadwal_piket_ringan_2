<?php
require 'koneksi.php';


require_once 'lib/SimpleXLSX.php';

// =============================================
// INISIALISASI VARIABEL UNTUK MENCEGAH WARNING
// =============================================
 $tab = isset($_GET['tab']) ? $_GET['tab'] : 'siswa';
 $action = isset($_GET['action']) ? $_GET['action'] : '';

// =============================================
// DOWNLOAD TEMPLATE
// =============================================
if (isset($_GET['download_template']) && $_GET['download_template'] == 1) {
    // Redirect ke file template yang sudah disiapkan
    $template_file = 'template_import_kelompok.xlsx';
    
    // Cek apakah file template ada
    if (file_exists($template_file)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_import_kelompok.xlsx"');
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Pragma: public');
        
        readfile($template_file);
        exit;
    } else {
        // Jika file tidak ada, buat file CSV sebagai alternatif
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_import_kelompok.csv"');
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // Header CSV
        fputcsv($output, ['Nama Kelompok', 'Deskripsi', 'Nama Siswa', 'Kelas']);
        
        // Contoh data
        fputcsv($output, ['Kelompok A', 'Kelompok untuk membersihkan ruang kelas', 'Ahmad Rizki', 'VII A']);
        fputcsv($output, ['Kelompok A', 'Kelompok untuk membersihkan ruang kelas', 'Siti Nurhaliza', 'VII A']);
        fputcsv($output, ['Kelompok B', 'Kelompok untuk membersihkan halaman', 'Budi Santoso', 'VII B']);
        
        fclose($output);
        exit;
    }
}

// Handle form submissions for students and kelompok
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if mode is set
    $mode = isset($_POST['mode']) ? $_POST['mode'] : '';

    if ($mode === 'add_siswa') {
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
        $nis = mysqli_real_escape_string($koneksi, $_POST['nis']);
        $kelas_id = intval($_POST['kelas_id']);
        mysqli_query($koneksi, "INSERT INTO siswa (nama, nis, kelas_id) VALUES ('$nama', '$nis', $kelas_id)");
        $_SESSION['success_message'] = "Data siswa berhasil ditambahkan";
    } elseif ($mode === 'edit_siswa') {
        $id = intval($_POST['id']);
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
        $nis = mysqli_real_escape_string($koneksi, $_POST['nis']);
        $kelas_id = intval($_POST['kelas_id']);
        mysqli_query($koneksi, "UPDATE siswa SET nama='$nama', nis='$nis', kelas_id=$kelas_id WHERE id=$id");
        $_SESSION['success_message'] = "Data siswa berhasil diperbarui";
    } elseif ($mode === 'delete_siswa') {
        $id = intval($_POST['id']);
        mysqli_query($koneksi, "DELETE FROM siswa WHERE id=$id");
        $_SESSION['success_message'] = "Data siswa berhasil dihapus";
    } elseif ($mode === 'add_kelompok') {
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
        $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
        mysqli_query($koneksi, "INSERT INTO kelompok (nama, deskripsi) VALUES ('$nama', '$deskripsi')");

        // Get the ID of the newly inserted group
        $kelompok_id = mysqli_insert_id($koneksi);

        // Add members if any were selected
        if (isset($_POST['anggota']) && is_array($_POST['anggota'])) {
            foreach ($_POST['anggota'] as $siswa_id) {
                $siswa_id = intval($siswa_id);
                // Verify that student exists before adding to group
                $check_student = mysqli_query($koneksi, "SELECT id FROM siswa WHERE id=$siswa_id");
                if (mysqli_num_rows($check_student) > 0) {
                    // Avoid duplicate members
                    $cek = mysqli_query($koneksi, "SELECT * FROM kelompok_siswa WHERE kelompok_id=$kelompok_id AND siswa_id=$siswa_id");
                    if (mysqli_num_rows($cek) == 0) {
                        mysqli_query($koneksi, "INSERT INTO kelompok_siswa (kelompok_id, siswa_id) VALUES ($kelompok_id, $siswa_id)");
                    }
                }
            }
        }
        $_SESSION['success_message'] = "Data kelompok berhasil ditambahkan";
    } elseif ($mode === 'edit_kelompok') {
        $id = intval($_POST['id']);
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
        $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);

        mysqli_query($koneksi, "UPDATE kelompok SET nama='$nama', deskripsi='$deskripsi' WHERE id=$id");

        // Remove all existing members
        mysqli_query($koneksi, "DELETE FROM kelompok_siswa WHERE kelompok_id=$id");

        // Add members if any were selected
        if (isset($_POST['anggota']) && is_array($_POST['anggota'])) {
            foreach ($_POST['anggota'] as $siswa_id) {
                $siswa_id = intval($siswa_id);
                // Verify that student exists before adding to group
                $check_student = mysqli_query($koneksi, "SELECT id FROM siswa WHERE id=$siswa_id");
                if (mysqli_num_rows($check_student) > 0) {
                    mysqli_query($koneksi, "INSERT INTO kelompok_siswa (kelompok_id, siswa_id) VALUES ($id, $siswa_id)");
                }
            }
        }
        $_SESSION['success_message'] = "Data kelompok berhasil diperbarui";
    } elseif ($mode === 'delete_kelompok') {
        $id = intval($_POST['id']);

        // Delete group members first
        mysqli_query($koneksi, "DELETE FROM kelompok_siswa WHERE kelompok_id=$id");

        // Then delete the group
        mysqli_query($koneksi, "DELETE FROM kelompok WHERE id=$id");

        $_SESSION['success_message'] = "Data kelompok berhasil dihapus";

    } elseif ($mode === 'import_kelompok') {
        // Validasi file upload
        if (!isset($_FILES['excel_file']['tmp_name']) || $_FILES['excel_file']['tmp_name'] == '') {
            $_SESSION['error_message'] = "Tidak ada file yang diupload!";
            header("Location: siswa.php?tab=kelompok");
            exit;
        }

        // Validasi ukuran file (maksimal 5MB)
        if ($_FILES['excel_file']['size'] > 5242880) {
            $_SESSION['error_message'] = "Ukuran file terlalu besar! Maksimal 5MB.";
            header("Location: siswa.php?tab=kelompok");
            exit;
        }

        // Validasi tipe file
        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        if (!in_array($_FILES['excel_file']['type'], $allowedTypes)) {
            $_SESSION['error_message'] = "Tipe file tidak valid! Harap upload file Excel (.xls, .xlsx) atau CSV.";
            header("Location: siswa.php?tab=kelompok");
            exit;
        }

        // Cek apakah file CSV
        if ($_FILES['excel_file']['type'] == 'text/csv' || pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION) == 'csv') {
            // Proses CSV
            $csv_file = $_FILES['excel_file']['tmp_name'];
            $rows = [];
            
            if (($handle = fopen($csv_file, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } else {
            // Proses Excel dengan SimpleXLSX
            $xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name']);

            if (!$xlsx) {
                $_SESSION['error_message'] = "File tidak dapat dibaca: " . SimpleXLSX::parseError();
                header("Location: siswa.php?tab=kelompok");
                exit;
            }

            $rows = $xlsx->rows();
        }

        $row_number = 0;
        $imported_count = 0;
        $error_count = 0;
        $error_messages = [];

        // Validasi struktur file
        if (count($rows) < 2) {
            $_SESSION['error_message'] = "File tidak memiliki data yang cukup! Minimal harus ada header dan 1 baris data.";
            header("Location: siswa.php?tab=kelompok");
            exit;
        }

        foreach ($rows as $r) {
            $row_number++;
            if ($row_number == 1) continue; // Skip header

            // Validasi minimal data yang diperlukan
            if (count($r) < 3) {
                $error_messages[] = "Baris $row_number: Data tidak lengkap, minimal harus ada nama kelompok dan nama siswa";
                $error_count++;
                continue;
            }

            $kelompok = trim($r[0] ?? '');
            $deskripsi = trim($r[1] ?? '');
            $nama = trim($r[2] ?? '');
            $kelas = trim($r[3] ?? '');

            if ($kelompok === '' || $nama === '') {
                $error_messages[] = "Baris $row_number: Nama kelompok dan nama siswa tidak boleh kosong";
                $error_count++;
                continue;
            }

            // INSERT KE DATABASE
            $kelompokEsc = mysqli_real_escape_string($koneksi, $kelompok);
            $deskripsiEsc = mysqli_real_escape_string($koneksi, $deskripsi);
            $namaEsc = mysqli_real_escape_string($koneksi, $nama);
            $kelasEsc = mysqli_real_escape_string($koneksi, $kelas);

            // Cek kelompok
            $qk = mysqli_query($koneksi, "SELECT id FROM kelompok WHERE nama='$kelompokEsc'");
            if (mysqli_num_rows($qk)) {
                $id_kelompok = mysqli_fetch_assoc($qk)['id'];
            } else {
                mysqli_query($koneksi, "INSERT INTO kelompok (nama, deskripsi) VALUES ('$kelompokEsc', '$deskripsiEsc')");
                $id_kelompok = mysqli_insert_id($koneksi);
            }

            // Cek kelas
            $id_kelas = 0;
            if ($kelas !== '') {
                $qkls = mysqli_query($koneksi, "SELECT id FROM kelas WHERE nama='$kelasEsc'");
                if (mysqli_num_rows($qkls)) {
                    $id_kelas = mysqli_fetch_assoc($qkls)['id'];
                } else {
                    // Buat kelas baru jika belum ada
                    mysqli_query($koneksi, "INSERT INTO kelas (nama) VALUES ('$kelasEsc')");
                    $id_kelas = mysqli_insert_id($koneksi);
                }
            }

            // Cek siswa
            $qs = mysqli_query($koneksi, "SELECT id FROM siswa WHERE nama='$namaEsc'");
            if (mysqli_num_rows($qs)) {
                $id_siswa = mysqli_fetch_assoc($qs)['id'];
            } else {
                mysqli_query($koneksi, "INSERT INTO siswa (nama, kelas_id) VALUES ('$namaEsc', $id_kelas)");
                $id_siswa = mysqli_insert_id($koneksi);
            }

            // Tambah ke kelompok_siswa jika belum ada
            $cek = mysqli_query($koneksi, "SELECT * FROM kelompok_siswa WHERE kelompok_id=$id_kelompok AND siswa_id=$id_siswa");
            if (!mysqli_num_rows($cek)) {
                mysqli_query($koneksi, "INSERT INTO kelompok_siswa (kelompok_id, siswa_id) VALUES ($id_kelompok, $id_siswa)");
                $imported_count++;
            }
        }

        // Buat pesan hasil import
        $result_message = "Import selesai! Berhasil mengimport $imported_count data.";
        if ($error_count > 0) {
            $result_message .= " Terdapat $error_count kesalahan. Lihat detail error di bawah.";
            $_SESSION['error_details'] = $error_messages;
        }

        $_SESSION['success_message'] = $result_message;
        header("Location: siswa.php?tab=kelompok");
        exit;
    }
}

// Handle delete actions via GET (hanya untuk remove_member yang memang pakai link)
if ($action === 'remove_member') {
    $kelompok_id = intval($_GET['id']);
    $siswa_id = intval($_GET['siswa_id']);
    mysqli_query($koneksi, "DELETE FROM kelompok_siswa WHERE kelompok_id=$kelompok_id AND siswa_id=$siswa_id");
    $_SESSION['success_message'] = "Anggota berhasil dihapus dari kelompok";
    header("Location: siswa.php?tab=kelompok&action=members&id=$kelompok_id");
    exit;
}

// Fetch data
 $result = mysqli_query($koneksi, "SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id=k.id ORDER BY s.id DESC");
 $kelas_res = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama");
 $kelompok_result = mysqli_query($koneksi, "SELECT k.*, COUNT(ks.siswa_id) AS jumlah_anggota FROM kelompok k LEFT JOIN kelompok_siswa ks ON k.id = ks.kelompok_id GROUP BY k.id ORDER BY k.id DESC");
 $siswa_res = mysqli_query($koneksi, "SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id=k.id ORDER BY s.nama");

// Fetch statistics for dashboard cards
 $total_siswa = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM siswa"));
 $total_kelas = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM kelas"));
 $total_kelompok = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM kelompok"));
 $total_jadwal = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM jadwal_piket"));

$page_title = 'Data Siswa';
require 'header.php';
?>
<div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h1 class="page-title">Manajemen Data Siswa</h1>
                </div>
            </div>

            <!-- Display success/error messages if any -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>

            <!-- Display error details if any -->
            <?php if (isset($_SESSION['error_details']) && is_array($_SESSION['error_details'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Detail Error:</strong>
                <ul>
                    <?php foreach ($_SESSION['error_details'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_details']); endif; ?>

            <!-- Tab Navigation -->
            <div class="dashboard-tabs">
                <ul>
                    <li>
                        <a class="<?php echo ($tab === 'siswa') ? 'active' : ''; ?>" href="siswa.php?tab=siswa">
                            <i class="fas fa-user-graduate me-2"></i> Data Siswa
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($tab === 'kelompok') ? 'active' : ''; ?>" href="siswa.php?tab=kelompok">
                            <i class="fas fa-users me-2"></i> Data Kelompok
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Siswa Tab Content -->
            <?php if ($tab === 'siswa'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="fas fa-user-graduate me-2"></i> Data Siswa</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSiswaModal">
                            <i class="fas fa-plus me-2"></i> Tambah Siswa
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>NIS</th>
                                    <th>Kelas</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($result, 0);
                                $i=1; 
                                while($row=mysqli_fetch_assoc($result)): 
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['nis'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['kelas'] ?? ''); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSiswaModal" onclick="editSiswa(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama'] ?? ''); ?>', '<?php echo htmlspecialchars($row['nis'] ?? ''); ?>', <?php echo $row['kelas_id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteSiswaModal" onclick="deleteSiswa(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama'] ?? ''); ?>')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Kelompok Tab Content -->
            <?php if ($tab === 'kelompok'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="fas fa-users me-2"></i> Data Kelompok</h3>
                        <div>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addKelompokModal">
                                <i class="fas fa-plus me-2"></i> Tambah Kelompok
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="fas fa-file-import me-2"></i> Import Excel
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($action === 'members'): 
                        $kelompok_id = intval($_GET['id']);
                        $kelompok = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM kelompok WHERE id=$kelompok_id"));
                        $members_result = mysqli_query($koneksi, "SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id=k.id INNER JOIN kelompok_siswa ks ON s.id = ks.siswa_id WHERE ks.kelompok_id=$kelompok_id ORDER BY s.nama");
                        
                        // Get students not in this group
                        $non_members_result = mysqli_query($koneksi, "SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id=k.id WHERE s.id NOT IN (SELECT siswa_id FROM kelompok_siswa WHERE kelompok_id=$kelompok_id) ORDER BY s.nama");
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fas fa-users me-2"></i> Kelompok: <?php echo htmlspecialchars($kelompok['nama'] ?? ''); ?></h4>
                            <a href="siswa.php?tab=kelompok" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i> Tambah Anggota</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="add_member" value="1">
                                    <input type="hidden" name="kelompok_id" value="<?php echo $kelompok_id; ?>">
                                    <div class="col-md-8">
                                        <select name="siswa_id" class="form-select" required>
                                            <option value="">-- Pilih Siswa --</option>
                                            <?php while($s = mysqli_fetch_assoc($non_members_result)): ?>
                                                <option value="<?php echo $s['id']; ?>">
                                                    <?php echo htmlspecialchars(($s['nama'] ?? '') . ' - ' . ($s['kelas'] ?? '')); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-plus me-2"></i> Tambah
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Daftar Anggota</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Nama</th>
                                                <th>NIS</th>
                                                <th>Kelas</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $i=1; while($row=mysqli_fetch_assoc($members_result)): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars($row['nama'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nis'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['kelas'] ?? ''); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-danger" onclick="removeMember(<?php echo $kelompok_id; ?>, <?php echo $row['id']; ?>)">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                            <?php if (mysqli_num_rows($members_result) == 0): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Belum ada anggota dalam kelompok ini</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Kelompok</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah Anggota</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($kelompok_result, 0);
                                $i=1; 
                                while($row=mysqli_fetch_assoc($kelompok_result)): 
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['deskripsi'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-primary badge-siswa">
                                                <i class="fas fa-users"></i> <?php echo $row['jumlah_anggota']; ?> anggota
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="siswa.php?tab=kelompok&action=members&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-users"></i> Anggota
                                                </a>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editKelompokModal" onclick="editKelompok(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteKelompokModal" onclick="deleteKelompok(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama'] ?? ''); ?>')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if (mysqli_num_rows($kelompok_result) == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Belum ada data kelompok</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Import Section -->
            <div class="import-section">
                <h4><i class="fas fa-file-import me-2"></i> Impor Data Kelompok</h4>
                <p>Import data kelompok melalui file Excel atau CSV yang telah diformat sesuai template untuk mempercepat proses input data.</p>
                
                <div class="template-preview">
                    <h5><i class="fas fa-table me-2"></i> Contoh Format Template</h5>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Kelompok</th>
                                <th>Deskripsi</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Kelompok A</td>
                                <td>Kelompok untuk membersihkan ruang kelas</td>
                                <td>Ahmad Rizki</td>
                                <td>VII A</td>
                            </tr>
                            <tr>
                                <td>Kelompok A</td>
                                <td>Kelompok untuk membersihkan ruang kelas</td>
                                <td>Siti Nurhaliza</td>
                                <td>VII A</td>
                            </tr>
                            <tr>
                                <td>Kelompok B</td>
                                <td>Kelompok untuk membersihkan halaman</td>
                                <td>Budi Santoso</td>
                                <td>VII B</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 small text-muted">
                        <strong>Catatan:</strong> Setiap baris mewakili satu anggota kelompok. Nama kelompok yang sama dapat diulang untuk beberapa anggota.
                    </p>
                </div>
                
                <div class="import-buttons">
                   <a href="siswa.php?download_template=1" class="btn btn-success">
                       <i class="fas fa-download me-2"></i> Unduh Template Excel
                   </a>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-upload me-2"></i> Upload Data
                    </button>
                </div>
            </div>

    <!-- Add Siswa Modal -->
    <div class="modal fade" id="addSiswaModal" tabindex="-1" aria-labelledby="addSiswaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSiswaModalLabel">
                        <i class="fas fa-user-plus me-2"></i> Tambah Siswa Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="add_siswa">
                        <div class="form-group">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="form-group">
                            <label for="nis" class="form-label">NIS</label>
                            <input type="text" class="form-control" id="nis" name="nis">
                        </div>
                        <div class="form-group">
                            <label for="kelas_id" class="form-label">Kelas</label>
                            <select name="kelas_id" class="form-select" id="kelas_id">
                                <option value="0">--Pilih Kelas--</option>
                                <?php 
                                mysqli_data_seek($kelas_res, 0);
                                while($k = mysqli_fetch_assoc($kelas_res)): 
                                ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama'] ?? ''); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Siswa Modal -->
    <div class="modal fade" id="editSiswaModal" tabindex="-1" aria-labelledby="editSiswaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSiswaModalLabel">
                        <i class="fas fa-user-edit me-2"></i> Edit Data Siswa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="editSiswaForm">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="edit_siswa">
                        <input type="hidden" name="id" id="editSiswaId">
                        <div class="form-group">
                            <label for="editSiswaNama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="editSiswaNama" name="nama" required>
                        </div>
                        <div class="form-group">
                            <label for="editSiswaNis" class="form-label">NIS</label>
                            <input type="text" class="form-control" id="editSiswaNis" name="nis">
                        </div>
                        <div class="form-group">
                            <label for="editSiswaKelas" class="form-label">Kelas</label>
                            <select name="kelas_id" class="form-select" id="editSiswaKelas">
                                <option value="0">--Pilih Kelas--</option>
                                <?php 
                                mysqli_data_seek($kelas_res, 0);
                                while($k = mysqli_fetch_assoc($kelas_res)): 
                                ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama'] ?? ''); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Siswa Modal -->
    <div class="modal fade" id="deleteSiswaModal" tabindex="-1" aria-labelledby="deleteSiswaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSiswaModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="deleteSiswaForm">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="delete_siswa">
                        <input type="hidden" name="id" id="deleteSiswaId">
                        <p>Apakah Anda yakin ingin menghapus data siswa <strong id="deleteSiswaNama"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> Tindakan ini tidak dapat dibatalkan!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Kelompok Modal -->
    <div class="modal fade" id="addKelompokModal" tabindex="-1" aria-labelledby="addKelompokModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addKelompokModalLabel">
                        <i class="fas fa-users me-2"></i> Tambah Kelompok Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="add_kelompok">
                        <div class="form-group">
                            <label for="nama_kelompok" class="form-label">Nama Kelompok</label>
                            <input type="text" class="form-control" id="nama_kelompok" name="nama" required>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="anggota" class="form-label">Anggota Kelompok</label>
                            <select name="anggota[]" class="form-select" id="anggota" multiple size="6">
                                <?php 
                                mysqli_data_seek($siswa_res, 0);
                                while($s = mysqli_fetch_assoc($siswa_res)): 
                                ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars(($s['nama'] ?? '') . ' - ' . ($s['kelas'] ?? '')); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="form-text text-muted">Tahan Ctrl untuk memilih lebih dari satu siswa</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Kelompok Modal -->
    <div class="modal fade" id="editKelompokModal" tabindex="-1" aria-labelledby="editKelompokModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editKelompokModalLabel">
                        <i class="fas fa-users me-2"></i> Edit Data Kelompok
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="editKelompokForm">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="edit_kelompok">
                        <input type="hidden" name="id" id="editKelompokId">
                        <div class="form-group">
                            <label for="editKelompokNama" class="form-label">Nama Kelompok</label>
                            <input type="text" class="form-control" id="editKelompokNama" name="nama" required>
                        </div>
                        <div class="form-group">
                            <label for="editKelompokDeskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="editKelompokDeskripsi" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editKelompokAnggota" class="form-label">Anggota Kelompok</label>
                            <select name="anggota[]" class="form-select" id="editKelompokAnggota" multiple size="6">
                                <?php 
                                mysqli_data_seek($siswa_res, 0);
                                while($s = mysqli_fetch_assoc($siswa_res)): 
                                ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars(($s['nama'] ?? '') . ' - ' . ($s['kelas'] ?? '')); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="form-text text-muted">Tahan Ctrl untuk memilih lebih dari satu siswa</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Kelompok Modal -->
    <div class="modal fade" id="deleteKelompokModal" tabindex="-1" aria-labelledby="deleteKelompokModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteKelompokModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="deleteKelompokForm">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="delete_kelompok">
                        <input type="hidden" name="id" id="deleteKelompokId">
                        <p>Apakah Anda yakin ingin menghapus data kelompok <strong id="deleteKelompokNama"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> Tindakan ini tidak dapat dibatalkan!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-import me-2"></i> Import Data dari Excel atau CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="mode" value="import_kelompok">
                        <div class="form-group">
                            <label for="excel_file" class="form-label">Pilih File (.xlsx / .xls / .csv)</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                            <small class="form-text text-muted">Format: Kolom A = Kelompok, B = Deskripsi, C = Nama Anggota, D = Kelas</small>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i> Panduan singkat:</h6>
                            <ul class="mb-0">
                                <li>Kolom wajib: Kelompok (A) dan Nama Anggota (C)</li>
                                <li>Jika kelompok belum ada, sistem akan membuatnya otomatis</li>
                                <li>Jika siswa belum ada, sistem akan membuat siswa baru</li>
                                <li>Duplikasi anggota akan otomatis diabaikan</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i> Penting:</h6>
                            <ul class="mb-0">
                                <li>Pastikan file Anda sesuai dengan format template</li>
                                <li>Ukuran file maksimal 5MB</li>
                                <li>Setiap baris mewakili satu anggota kelompok</li>
                                <li>Nama kelompok yang sama dapat diulang untuk beberapa anggota</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="siswa.php?download_template=1" class="btn btn-success">
                            <i class="fas fa-download me-2"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-import me-2"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
    // Function to edit student
    function editSiswa(id, nama, nis, kelas_id) {
        document.getElementById('editSiswaId').value = id;
        document.getElementById('editSiswaNama').value = nama;
        document.getElementById('editSiswaNis').value = nis;
        document.getElementById('editSiswaKelas').value = kelas_id;
    }

    // Function to delete student
    function deleteSiswa(id, nama) {
        document.getElementById('deleteSiswaId').value = id;
        document.getElementById('deleteSiswaNama').textContent = nama;
    }

    // Function to edit kelompok
    function editKelompok(id) {
        // Fetch kelompok data via AJAX
        fetch('get_kelompok_data.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editKelompokId').value = data.id;
                document.getElementById('editKelompokNama').value = data.nama;
                document.getElementById('editKelompokDeskripsi').value = data.deskripsi;
                
                // Clear all selections first
                var select = document.getElementById('editKelompokAnggota');
                var options = select.options;
                for (var i = 0; i < options.length; i++) {
                    options[i].selected = false;
                }
                
                // Select members
                if (data.anggota && data.anggota.length > 0) {
                    for (var i = 0; i < data.anggota.length; i++) {
                        for (var j = 0; j < options.length; j++) {
                            if (options[j].value == data.anggota[i]) {
                                options[j].selected = true;
                                break;
                            }
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Function to delete kelompok
    function deleteKelompok(id, nama) {
        document.getElementById('deleteKelompokId').value = id;
        document.getElementById('deleteKelompokNama').textContent = nama;
    }

    // Function to remove member from kelompok
    function removeMember(kelompok_id, siswa_id) {
        if (confirm('Apakah Anda yakin ingin menghapus anggota ini dari kelompok?')) {
            window.location.href = 'siswa.php?tab=kelompok&action=remove_member&id=' + kelompok_id + '&siswa_id=' + siswa_id;
        }
    }
    </script>

