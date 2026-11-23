<?php
// Common header untuk seluruh halaman proyek
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Jadwal Piket' : 'Jadwal Piket'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
  </head>
  <body>
<!-- Header/Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-school"></i>
            Jadwal Piket
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'siswa.php') ? 'active' : ''; ?>" href="siswa.php">
                        <i class="fas fa-users me-1"></i> Siswa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'kelas.php') ? 'active' : ''; ?>" href="kelas.php">
                        <i class="fas fa-door-open me-1"></i> Kelas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'jadwal.php') ? 'active' : ''; ?>" href="jadwal.php">
                        <i class="fas fa-calendar-alt me-1"></i> Jadwal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'struktur.php' || $current_page === 'struktur_editor.php') ? 'active' : ''; ?>" href="struktur.php">
                        <i class="fas fa-sitemap me-1"></i> Struktur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'cetak_jadwal.php') ? 'active' : ''; ?>" href="cetak_jadwal.php">
                        <i class="fas fa-print me-1"></i> Cetak Jadwal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-home" href="index.php" title="Kembali ke Beranda" aria-label="Kembali ke Beranda">
                        <i class="fas fa-house"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="content-wrapper">
    <div class="main-container">
