<?php
require 'koneksi.php';

// Query untuk menghitung total siswa
$result_siswa = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM siswa");
$data_siswa = mysqli_fetch_assoc($result_siswa);
$total_siswa = $data_siswa['total'] ?? 0;

// Query untuk menghitung total kelas
$result_kelas = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kelas");
$data_kelas = mysqli_fetch_assoc($result_kelas);
$total_kelas = $data_kelas['total'] ?? 0;

// Query untuk menghitung total jadwal
$result_jadwal = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_piket");
$data_jadwal = mysqli_fetch_assoc($result_jadwal);
$total_jadwal = $data_jadwal['total'] ?? 0;

// Judul halaman & include template
$page_title = 'Dashboard';
require 'header.php';
?>

<div class="page-header">
    <h1 class="page-title">Dashboard Sistem Piket</h1>
    <p class="page-subtitle">Kelola jadwal piket sekolah dengan antarmuka yang intuitif dan efisien</p>
</div>

<!-- Statistik Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
            <h3 class="stat-number"><?php echo $total_siswa; ?></h3>
            <p>Total Siswa Terdaftar</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-door-open"></i>
        </div>
        <div class="stat-content">
            <h3 class="stat-number"><?php echo $total_kelas; ?></h3>
            <p>Kelas Aktif</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <h3 class="stat-number"><?php echo $total_jadwal; ?></h3>
            <p>Jadwal Piket Aktif</p>
        </div>
    </div>
</div>

<script>
    // Animasi counter untuk statistik
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.stat-number');
        const speed = 200;
        
        counters.forEach(counter => {
            const animate = () => {
                const value = +counter.getAttribute('data-count');
                const data = +counter.innerText;
                const time = value / speed;
                
                if (data < value) {
                    counter.innerText = Math.ceil(data + time);
                    setTimeout(animate, 1);
                } else {
                    counter.innerText = value;
                }
            }
            
            counter.setAttribute('data-count', counter.innerText);
            counter.innerText = 0;
            setTimeout(animate, 300);
        });
    });
</script>

