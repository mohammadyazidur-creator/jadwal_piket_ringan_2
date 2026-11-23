<?php
require 'koneksi.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Get kelompok data
    $kelompok = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM kelompok WHERE id=$id"));
    
    if ($kelompok) {
        // Get kelompok members
        $anggota = [];
        $members_result = mysqli_query($koneksi, "SELECT siswa_id FROM kelompok_siswa WHERE kelompok_id=$id");
        while ($member = mysqli_fetch_assoc($members_result)) {
            $anggota[] = $member['siswa_id'];
        }
        
        $kelompok['anggota'] = $anggota;
        
        header('Content-Type: application/json');
        echo json_encode($kelompok);
    }
}
?>