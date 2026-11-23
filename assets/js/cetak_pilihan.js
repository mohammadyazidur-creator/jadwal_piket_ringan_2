/**
 * assets/js/cetak_pilihan.js - Logika Navigasi Pilihan Cetakan
 * Menangani perpindahan halaman cetak berdasarkan pilihan dropdown data-cetak-selector
 */
document.addEventListener('DOMContentLoaded', function () {
    var selector = document.querySelector('[data-cetak-selector]');
    if (selector) {
        selector.addEventListener('change', function () {
            if (this.value) {
                window.location.href = this.value;
            }
        });
    }
});
