<?php
/**
 * lib/versi_aset.php - Reusable Asset Cache Buster
 * Memberikan versi filemtime pada aset JS/CSS agar tidak tertahan cache browser
 * Contoh: versi_aset('assets/js/struktur.js') -> assets/js/struktur.js?v=1730000000
 */
function versi_aset($relative_path) {
    $real_path = dirname(__DIR__) . '/' . ltrim($relative_path, '/');
    if (file_exists($real_path)) {
        return $relative_path . '?v=' . filemtime($real_path);
    }
    return $relative_path;
}