/**
 * assets/js/tabel.js - Interaktivitas Komponen Tabel Reusable
 * Fitur:
 * - Pencarian teks berjeda (debounce 400ms) yang otomatis mengirimkan form
 * - Dropdown filter & per_halaman langsung menerapkan perubahan (onchange auto-submit)
 * - Tanpa library eksternal, sepenuhnya vanilla JavaScript
 * - Tetap berfungsi penuh tanpa JavaScript (progressive enhancement via standard GET form)
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Cari seluruh form filter tabel pada halaman
    const filterForms = document.querySelectorAll('.tabel-filter-form');

    filterForms.forEach(function(form) {
        const searchInput = form.querySelector('input[name="q"]');
        let debounceTimer = null;
        let lastSearchValue = searchInput ? searchInput.value.trim() : '';

        // 1. Pencarian berjeda (Debounce 400ms)
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    const currentVal = searchInput.value.trim();
                    // Hanya submit jika nilai pencarian benar-benar berubah
                    if (currentVal !== lastSearchValue) {
                        lastSearchValue = currentVal;
                        // Reset hal ke 1 saat pencarian baru
                        const halInput = form.querySelector('input[name="hal"]');
                        if (halInput) halInput.value = '1';
                        form.submit();
                    }
                }, 400);
            });

            // Tekan Enter langsung submit tanpa menunggu debounce
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(debounceTimer);
                    const halInput = form.querySelector('input[name="hal"]');
                    if (halInput) halInput.value = '1';
                }
            });
        }

        // 2. Dropdown Filter & Per Halaman langsung menerapkan (onchange)
        const selectInputs = form.querySelectorAll('select');
        selectInputs.forEach(function(sel) {
            sel.addEventListener('change', function() {
                // Reset hal ke 1 saat filter atau jumlah per halaman berubah
                const halInput = form.querySelector('input[name="hal"]');
                if (halInput) halInput.value = '1';
                form.submit();
            });
        });
    });
});
