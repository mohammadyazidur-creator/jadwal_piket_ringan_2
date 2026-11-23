<?php
/**
 * index.php - Halaman Depan (Landing Page) Sistem Jadwal Piket
 * Memenuhi AGENTS.md (Aturan 14) dan Persyaratan Pengguna:
 * 1. Angka riil dari database (Siswa, Kelas, Jadwal)
 * 2. Kartu fitur hanya menyebut fitur yang benar-benar ada
 * 3. Tanpa klaim Lighthouse; ringan dan cepat
 * 4. landing.css terisolasi (.landing-*), style.css dimuat untuk variabel & footer
 * 5. Tombol "Lihat Fitur" scroll halus ke #fitur
 * 6. Brand di header.php mengarah ke index.php
 */

require_once 'koneksi.php';

// Ambil data statistik riil dari database (Syarat 1: Tidak ada angka karangan)
$total_siswa = 0;
$total_kelas = 0;
$total_jadwal = 0;

if (isset($koneksi) && $koneksi) {
    // 1. Total Siswa
    $res_siswa = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM siswa");
    if ($res_siswa && $row = mysqli_fetch_assoc($res_siswa)) {
        $total_siswa = (int)$row['total'];
    }

    // 2. Total Kelas
    $res_kelas = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kelas");
    if ($res_kelas && $row = mysqli_fetch_assoc($res_kelas)) {
        $total_kelas = (int)$row['total'];
    }

    // 3. Total Jadwal Piket
    $res_jadwal = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_piket");
    if ($res_jadwal && $row = mysqli_fetch_assoc($res_jadwal)) {
        $total_jadwal = (int)$row['total'];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jadwal Piket Sekolah - Bersih, Praktis, & Terorganisir</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Style Global (Variabel & Footer) -->
    <link href="style.css" rel="stylesheet">
    <!-- Style Khusus Landing Page -->
    <link href="assets/css/landing.css" rel="stylesheet">
</head>
<body class="landing-page">
<div class="content-wrapper">
    <div class="landing-body-inner">

        <!-- NAVIGASI MINI LANDING -->
        <header class="landing-nav">
            <div class="container">
                <div class="landing-nav-inner">
                    <a href="index.php" class="landing-brand">
                        <i class="fas fa-school"></i>
                        <span>Jadwal Piket</span>
                    </a>
                    <div>
                        <a href="dashboard.php" class="landing-btn-dashboard">
                            <span>Masuk ke Dashboard</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="landing-hero">
            <div class="container">
                <div class="landing-hero-row">
                    <!-- Sisi Kiri: Teks & Aksi -->
                    <div class="landing-hero-content landing-reveal">
                        <span class="landing-badge">
                            <i class="fas fa-sparkles me-1"></i> Sistem Piket Sekolah Praktis
                        </span>
                        <h1 class="landing-hero-title">
                            Kelola Jadwal Piket & Bagan Kelas Secara <span class="landing-hero-title-highlight">Terorganisir</span>
                        </h1>
                        <p class="landing-hero-desc">
                            Solusi lengkap pembagian piket harian, impor data kelompok siswa, manajemen kelas, dan penyusunan bagan struktur organisasi sekolah yang rapi dan mudah digunakan.
                        </p>
                        
                        <div class="landing-hero-actions">
                            <a href="dashboard.php" class="landing-btn-primary">
                                <i class="fas fa-rocket"></i>
                                <span>Buka Dashboard</span>
                            </a>
                            <a href="#fitur" class="landing-btn-secondary">
                                <i class="fas fa-compass"></i>
                                <span>Lihat Fitur</span>
                            </a>
                        </div>

                        <!-- Data Riil dari Database (Syarat 1) -->
                        <div class="landing-hero-stats">
                            <div class="landing-stat-item">
                                <span class="landing-stat-number"><?php echo number_format($total_siswa); ?></span>
                                <span class="landing-stat-label">Siswa Terdaftar</span>
                            </div>
                            <div class="landing-stat-divider"></div>
                            <div class="landing-stat-item">
                                <span class="landing-stat-number"><?php echo number_format($total_kelas); ?></span>
                                <span class="landing-stat-label">Kelas Aktif</span>
                            </div>
                            <div class="landing-stat-divider"></div>
                            <div class="landing-stat-item">
                                <span class="landing-stat-number"><?php echo number_format($total_jadwal); ?></span>
                                <span class="landing-stat-label">Jadwal Piket</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Papan Jadwal Isometrik 3D CSS -->
                    <div class="landing-3d-scene landing-reveal" id="landingHeroScene">
                        <div class="landing-3d-stage" id="landingHeroStage">
                            <div class="landing-3d-board">
                                <div class="landing-3d-board-header">
                                    <div class="landing-3d-board-title">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Piket Kelas VII A</span>
                                        <span class="landing-3d-tag ms-1">Contoh</span>
                                    </div>
                                    <small><i class="fas fa-clock me-1"></i>07.00 WIB</small>
                                </div>
                                <div class="landing-3d-board-body">
                                    <div class="landing-3d-schedule-grid">
                                        <!-- Kolom Senin -->
                                        <div class="landing-3d-day-col">
                                            <div class="landing-3d-day-head">Senin</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Ahmad R.</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Budi S.</div>
                                        </div>
                                        <!-- Kolom Selasa -->
                                        <div class="landing-3d-day-col">
                                            <div class="landing-3d-day-head">Selasa</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Citra D.</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Dimas P.</div>
                                        </div>
                                        <!-- Kolom Rabu -->
                                        <div class="landing-3d-day-col">
                                            <div class="landing-3d-day-head">Rabu</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Eka W.</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Farhan K.</div>
                                        </div>
                                        <!-- Kolom Kamis -->
                                        <div class="landing-3d-day-col">
                                            <div class="landing-3d-day-head">Kamis</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Gita N.</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Hadi S.</div>
                                        </div>
                                        <!-- Kolom Jumat -->
                                        <div class="landing-3d-day-col">
                                            <div class="landing-3d-day-head">Jumat</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Intan P.</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-check-circle"></i> Joko W.</div>
                                        </div>
                                        <!-- Kolom Cadangan / Catatan -->
                                        <div class="landing-3d-day-col landing-3d-day-col-extra">
                                            <div class="landing-3d-day-head">Rotasi</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-sync-alt"></i> Tiap Pekan</div>
                                            <div class="landing-3d-day-item"><i class="fas fa-shield-alt"></i> Teratur</div>
                                        </div>
                                    </div>
                                    <div class="landing-3d-board-footer">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span>*Data nama jadwal adalah ilustrasi contoh tata letak</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating Card 1: Status Piket (Depth translateZ 65px) -->
                            <div class="landing-3d-float-card1">
                                <div class="landing-float-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="landing-float-text">
                                    <span class="landing-float-label">Status Harian</span>
                                    <span class="landing-float-val">Tugas Terjadwal</span>
                                </div>
                            </div>

                            <!-- Floating Card 2: Struktur Kelas (Depth translateZ 45px) -->
                            <div class="landing-3d-float-card2">
                                <div class="landing-float-icon">
                                    <i class="fas fa-sitemap"></i>
                                </div>
                                <div class="landing-float-text">
                                    <span class="landing-float-label">Bagan Kelas</span>
                                    <span class="landing-float-val">Hierarki Interaktif</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FITUR UTAMA (Syarat 2: Hanya fitur riil yang ada) -->
        <section id="fitur" class="landing-features">
            <div class="container">
                <div class="landing-section-header landing-reveal">
                    <h2 class="landing-section-title">Fitur Pengelolaan Lengkap</h2>
                    <p class="landing-section-subtitle">
                        Dirancang khusus untuk mendukung ketertiban kelas dengan antarmuka yang bersih, cepat, dan mudah dipahami oleh guru serta siswa.
                    </p>
                </div>

                <div class="landing-cards-grid">
                    <!-- Kartu 1: Jadwal Piket Harian (jadwal.php) -->
                    <a href="jadwal.php" class="landing-feature-card landing-reveal">
                        <div class="landing-feature-card-inner">
                            <div class="landing-feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="landing-feature-title">Jadwal Piket Mingguan</h3>
                            <p class="landing-feature-desc">
                                Atur pembagian piket siswa dari Senin hingga Minggu secara rapi dengan tampilan kartu harian dan pembagian kelompok tugas.
                            </p>
                            <span class="landing-feature-link">
                                Buka Jadwal Piket <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>

                    <!-- Kartu 2: Data Siswa & Impor (siswa.php) -->
                    <a href="siswa.php" class="landing-feature-card landing-reveal">
                        <div class="landing-feature-card-inner">
                            <div class="landing-feature-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h3 class="landing-feature-title">Data Siswa & Impor Excel</h3>
                            <p class="landing-feature-desc">
                                Kelola data siswa lengkap dengan NIS dan kelas, pembagian kelompok piket, serta fasilitas unduh template dan impor Excel/CSV.
                            </p>
                            <span class="landing-feature-link">
                                Kelola Data Siswa <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>

                    <!-- Kartu 3: Manajemen Kelas (kelas.php) -->
                    <a href="kelas.php" class="landing-feature-card landing-reveal">
                        <div class="landing-feature-card-inner">
                            <div class="landing-feature-icon">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <h3 class="landing-feature-title">Manajemen Ruang Kelas</h3>
                            <p class="landing-feature-desc">
                                Kelola seluruh data ruang kelas di sekolah, pantau rekap total siswa per kelas, dan lihat rincian siswa lewat modal interaktif.
                            </p>
                            <span class="landing-feature-link">
                                Lihat Daftar Kelas <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>

                    <!-- Kartu 4: Bagan Struktur Organisasi (struktur.php) -->
                    <a href="struktur.php" class="landing-feature-card landing-reveal">
                        <div class="landing-feature-card-inner">
                            <div class="landing-feature-icon">
                                <i class="fas fa-sitemap"></i>
                            </div>
                            <h3 class="landing-feature-title">Bagan Struktur Organisasi</h3>
                            <p class="landing-feature-desc">
                                Susun bagan struktur organisasi kelas atau sekolah secara visual dengan editor geser bebas, garis penghubung otomatis, dan hierarki jabatan.
                            </p>
                            <span class="landing-feature-link">
                                Kelola Struktur Bagan <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>

                    <!-- Kartu 5: Cetak Jadwal (cetak_jadwal.php) -->
                    <a href="cetak_jadwal.php" class="landing-feature-card landing-reveal">
                        <div class="landing-feature-card-inner">
                            <div class="landing-feature-icon">
                                <i class="fas fa-print"></i>
                            </div>
                            <h3 class="landing-feature-title">Cetak Jadwal Siap Pajang</h3>
                            <p class="landing-feature-desc">
                                Cetak lembar jadwal piket kelas berformat rapi dan profesional yang siap ditempel di dinding ruang kelas untuk kemudahan bersama.
                            </p>
                            <span class="landing-feature-link">
                                Cetak Jadwal Kelas <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- CARA KERJA (3 LANGKAH) -->
        <section class="landing-steps">
            <div class="container">
                <div class="landing-section-header landing-reveal">
                    <h2 class="landing-section-title">Alur Penggunaan Sederhana</h2>
                    <p class="landing-section-subtitle">
                        Tiga langkah praktis untuk memulai operasional jadwal piket dan kepengurusan kelas.
                    </p>
                </div>

                <div class="landing-steps-grid">
                    <!-- Langkah 1 -->
                    <div class="landing-step-card landing-reveal">
                        <div class="landing-step-number">1</div>
                        <h4 class="landing-step-title">Daftarkan Siswa & Kelas</h4>
                        <p class="landing-step-desc">
                            Tambahkan data ruang kelas dan masukkan siswa secara manual atau cepat melalui fitur impor Excel kelompok.
                        </p>
                    </div>

                    <!-- Langkah 2 -->
                    <div class="landing-step-card landing-reveal">
                        <div class="landing-step-number">2</div>
                        <h4 class="landing-step-title">Bagi Jadwal & Struktur</h4>
                        <p class="landing-step-desc">
                            Tetapkan kelompok piket harian untuk hari aktif sekolah dan buat bagan pengurus kelas di editor interaktif.
                        </p>
                    </div>

                    <!-- Langkah 3 -->
                    <div class="landing-step-card landing-reveal">
                        <div class="landing-step-number">3</div>
                        <h4 class="landing-step-title">Pantau & Cetak Lembar</h4>
                        <p class="landing-step-desc">
                            Lihat rekap jadwal piket langsung dari dashboard atau cetak lembar tugas piket untuk ditempel di kelas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA BANNER MENUJU DASHBOARD -->
        <section class="landing-cta">
            <div class="container">
                <div class="landing-cta-box landing-reveal">
                    <h2>Mulai Kelola Jadwal Piket Sekolah Anda</h2>
                    <p>
                        Akses seluruh data kelas, siswa, pembagian jadwal piket, dan editor bagan struktur organisasi langsung dari panel kontrol.
                    </p>
                    <a href="dashboard.php" class="landing-btn-dashboard" style="font-size: 1.05rem; padding: 0.85rem 2.2rem;">
                        <span>Buka Dashboard Sekarang</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Script Interaktif Khusus Landing -->
        <script src="assets/js/landing.js"></script>

<?php
// Footer Bersama (AGENTS.md: footer sama dengan halaman lain)
// footer.php menutup 2 pembungkus div (.content-wrapper dan .landing-body-inner)
require_once 'footer.php';
?>