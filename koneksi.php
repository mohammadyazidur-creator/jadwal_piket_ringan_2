<?php
$koneksi = mysqli_connect("localhost", "root", "", "jadwal_piket_ringan");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>