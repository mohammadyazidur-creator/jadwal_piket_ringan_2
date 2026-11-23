/**
 * assets/js/struktur_cetak.js
 * Logika halaman cetak struktur organisasi.
 * Bergantung pada: StrukturRender (struktur_render.js)
 */
(function () {
    'use strict';

    /* ====== Konstanta Ukuran Kertas (mm) ====== */
    var PAPER = {
        A4: { w: 210, h: 297 },
        A3: { w: 297, h: 420 }
    };
    var MARGIN_MM = 10; // margin kertas kiri/kanan/bawah untuk area bagan
    var HEADER_MM = 30; // estimasi tinggi header cetak (judul+nama sekolah+tanggal)
    var TTD_MM    = 45; // estimasi tinggi blok tanda tangan

    /* ====== State ====== */
    var nodesData    = [];
    var gayaBagan    = {};
    var currentScale = 1;

    /* ====== Elemen DOM (di-cache setelah DOMContentLoaded) ====== */
    var elPaper, elBaganArea, elViewport, elSvg, elPaperHeader;
    var elOptKertas, elOptOrientasi, elOptMuat, elOptFoto, elOptKeterangan;
    var elOptGrid, elOptJudul, elOptSekolah, elOptTanggal, elOptTtd;
    var elTtdArea, elTtdNama, elTtdJabatan;
    var elBtnCetak;

    /* ====== Helper Tanggal Indonesia ====== */
    var BULAN_ID = [
        '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    function tanggalIndonesia(d) {
        d = d || new Date();
        return d.getDate() + ' ' + BULAN_ID[d.getMonth() + 1] + ' ' + d.getFullYear();
    }

    /* ====== LocalStorage Helper (try/catch) ====== */
    var LS_KEY = 'sc_opsi';

    function opsiLoad() {
        try {
            var raw = localStorage.getItem(LS_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    }

    function opsiSave(obj) {
        try {
            localStorage.setItem(LS_KEY, JSON.stringify(obj));
        } catch (_) { /* abaikan */ }
    }

    /* ====== Mengumpulkan Nilai Opsi dari Form ====== */
    function getOpsi() {
        return {
            kertas:     elOptKertas.value,
            orientasi:  elOptOrientasi.value,
            muat:       elOptMuat.checked,
            foto:       elOptFoto.checked,
            keterangan: elOptKeterangan.checked,
            grid:       elOptGrid.checked,
            judul:      elOptJudul.value.trim(),
            sekolah:    elOptSekolah.value.trim(),
            ttd:        elOptTtd.checked,
            ttdNama:    elTtdNama.value.trim(),
            ttdJabatan: elTtdJabatan.value.trim()
        };
    }

    /* ====== Menerapkan Opsi ke Form (dari localStorage) ====== */
    function setOpsi(o) {
        if (!o) return;
        if (o.kertas)     elOptKertas.value     = o.kertas;
        if (o.orientasi)  elOptOrientasi.value  = o.orientasi;
        if (typeof o.muat === 'boolean')       elOptMuat.checked       = o.muat;
        if (typeof o.foto === 'boolean')       elOptFoto.checked       = o.foto;
        if (typeof o.keterangan === 'boolean') elOptKeterangan.checked = o.keterangan;
        if (typeof o.grid === 'boolean')       elOptGrid.checked       = o.grid;
        if (o.judul)      elOptJudul.value      = o.judul;
        if (o.sekolah)    elOptSekolah.value    = o.sekolah;
        if (typeof o.ttd === 'boolean')        elOptTtd.checked        = o.ttd;
        if (o.ttdNama)    elTtdNama.value       = o.ttdNama;
        if (o.ttdJabatan) elTtdJabatan.value    = o.ttdJabatan;
    }

    /* ====== Hitung Ukuran Kertas dalam Piksel (1mm ≈ 3.7795px) ====== */
    var MM_TO_PX = 3.7795;

    function paperSizePx(opsi) {
        var size = PAPER[opsi.kertas] || PAPER.A4;
        var w, h;
        if (opsi.orientasi === 'landscape') {
            w = size.h;
            h = size.w;
        } else {
            w = size.w;
            h = size.h;
        }
        return { w: Math.round(w * MM_TO_PX), h: Math.round(h * MM_TO_PX) };
    }

    /* ====== Menyisipkan @page Dinamis ====== */
    var pageStyleEl = null;

    function updatePageStyle(opsi) {
        var size = opsi.kertas === 'A3' ? 'A3' : 'A4';
        var orient = opsi.orientasi === 'landscape' ? 'landscape' : 'portrait';
        var css = '@page { size: ' + size + ' ' + orient + '; margin: 8mm; }';

        if (!pageStyleEl) {
            pageStyleEl = document.createElement('style');
            pageStyleEl.setAttribute('data-sc-page', '1');
            document.head.appendChild(pageStyleEl);
        }
        pageStyleEl.textContent = css;
    }

    /* ====== Render Bagan ====== */
    function render() {
        if (!window.StrukturRender || nodesData.length === 0) return;

        var opsi = getOpsi();

        // Simpan opsi ke localStorage
        opsiSave(opsi);

        // Update @page
        updatePageStyle(opsi);

        // Ukuran kertas px
        var pxSize = paperSizePx(opsi);
        elPaper.style.width  = pxSize.w + 'px';
        elPaper.style.height = pxSize.h + 'px';

        // Update header cetak
        var headerH2 = elPaperHeader.querySelector('h2');
        var headerP  = elPaperHeader.querySelector('.sc-sekolah-text');
        var headerD  = elPaperHeader.querySelector('.sc-paper-date');
        if (headerH2) headerH2.textContent = opsi.judul || 'Struktur Organisasi';
        if (headerP)  headerP.textContent  = opsi.sekolah || '';
        if (headerD)  headerD.textContent  = elOptTanggal.textContent;

        // Toggle tanda tangan
        elTtdArea.style.display = opsi.ttd ? 'flex' : 'none';
        if (opsi.ttd) {
            var ttdNamaEl = elTtdArea.querySelector('.sc-ttd-nama');
            var ttdJabEl  = elTtdArea.querySelector('.sc-ttd-jabatan');
            if (ttdNamaEl)  ttdNamaEl.textContent = opsi.ttdNama || '___________________';
            if (ttdJabEl)   ttdJabEl.textContent   = opsi.ttdJabatan || '';
        }

        // Toggle grid
        if (opsi.grid) {
            elViewport.classList.add('sc-show-grid');
        } else {
            elViewport.classList.remove('sc-show-grid');
        }

        // Bersihkan viewport
        elViewport.innerHTML = '';
        elSvg.innerHTML = '';
        elViewport.appendChild(elSvg);

        // Render node
        var renderOpts = {
            isInteractive: false,
            showPhoto: opsi.foto,
            showKeterangan: opsi.keterangan,
            showSub: true
        };

        nodesData.forEach(function (n) {
            var el = StrukturRender.createNodeElement(n, renderOpts);
            elViewport.appendChild(el);
        });

        // Render garis penghubung
        StrukturRender.renderConnections(elSvg, nodesData, gayaBagan);

        // Hitung bounding box
        var bb = StrukturRender.calculateBoundingBox(nodesData, 20);

        // Geser semua node agar mulai dari (0,0) relatif
        var offsetX = bb.minX;
        var offsetY = bb.minY;
        var nodeEls = elViewport.querySelectorAll('.struktur-node');
        nodeEls.forEach(function (el) {
            var curLeft = parseFloat(el.style.left) || 0;
            var curTop  = parseFloat(el.style.top)  || 0;
            el.style.left = (curLeft - offsetX) + 'px';
            el.style.top  = (curTop  - offsetY) + 'px';
        });

        // Set ukuran viewport ke bounding box
        elViewport.style.width  = bb.width + 'px';
        elViewport.style.height = bb.height + 'px';

        // Re-render SVG dengan posisi terkoreksi
        elSvg.innerHTML = '';
        var shiftedNodes = nodesData.map(function (n) {
            return Object.assign({}, n, {
                pos_x: (parseFloat(n.pos_x) || 0) - offsetX,
                pos_y: (parseFloat(n.pos_y) || 0) - offsetY
            });
        });
        StrukturRender.renderConnections(elSvg, shiftedNodes, gayaBagan);

        // SVG ukuran penuh
        elSvg.setAttribute('width', bb.width);
        elSvg.setAttribute('height', bb.height);
        elSvg.style.width  = bb.width + 'px';
        elSvg.style.height = bb.height + 'px';

        // Hitung area yang tersedia untuk bagan di dalam kertas (px)
        var headerReserve = HEADER_MM * MM_TO_PX;
        var ttdReserve    = opsi.ttd ? (TTD_MM * MM_TO_PX) : 0;
        var marginPx      = MARGIN_MM * MM_TO_PX * 2; // kiri + kanan atau atas + bawah
        var availW = pxSize.w - marginPx;
        var availH = pxSize.h - headerReserve - ttdReserve - marginPx;

        // Skala
        if (opsi.muat && bb.width > 0 && bb.height > 0) {
            var sx = availW / bb.width;
            var sy = availH / bb.height;
            currentScale = Math.min(sx, sy, 1); // jangan perbesar lebih dari 1
        } else {
            currentScale = 1;
        }

        elViewport.style.transform = 'scale(' + currentScale + ')';
        elBaganArea.style.width  = Math.ceil(bb.width  * currentScale) + 'px';
        elBaganArea.style.height = Math.ceil(bb.height * currentScale) + 'px';

        // Toggle fields tanda tangan
        toggleTtdFields(opsi.ttd);
    }

    function toggleTtdFields(show) {
        var fields = document.getElementById('scTtdFields');
        if (fields) fields.style.display = show ? 'block' : 'none';
    }

    /* ====== Inisialisasi ====== */
    function init() {
        // Cache elemen DOM
        elPaper        = document.getElementById('scPaper');
        elBaganArea    = document.getElementById('scBaganArea');
        elViewport     = document.getElementById('scViewport');
        elSvg          = document.getElementById('scSvg');
        elPaperHeader  = document.getElementById('scPaperHeader');
        elOptKertas    = document.getElementById('scKertas');
        elOptOrientasi = document.getElementById('scOrientasi');
        elOptMuat      = document.getElementById('scMuat');
        elOptFoto      = document.getElementById('scFoto');
        elOptKeterangan= document.getElementById('scKeterangan');
        elOptGrid      = document.getElementById('scGrid');
        elOptJudul     = document.getElementById('scJudul');
        elOptSekolah   = document.getElementById('scSekolah');
        elOptTanggal   = document.getElementById('scTanggal');
        elOptTtd       = document.getElementById('scTtd');
        elTtdArea      = document.getElementById('scTtdArea');
        elTtdNama      = document.getElementById('scTtdNama');
        elTtdJabatan   = document.getElementById('scTtdJabatan');
        elBtnCetak     = document.getElementById('scBtnCetak');

        if (!elPaper || !elViewport) return; // halaman tanpa bagan (pesan kosong)

        // Ambil data node dari elemen data tersembunyi (#scData)
        var dataEl = document.getElementById('scData');
        if (dataEl) {
            try { nodesData = JSON.parse(dataEl.getAttribute('data-nodes') || '[]'); } catch (_) { nodesData = []; }
            try { gayaBagan = JSON.parse(dataEl.getAttribute('data-gaya')  || '{}'); } catch (_) { gayaBagan = {}; }
        }

        // Tanggal Indonesia
        if (elOptTanggal) {
            elOptTanggal.textContent = tanggalIndonesia(new Date());
        }

        // Muat opsi tersimpan
        setOpsi(opsiLoad());

        // Event listener opsi
        var controls = [
            elOptKertas, elOptOrientasi, elOptMuat, elOptFoto,
            elOptKeterangan, elOptGrid, elOptJudul, elOptSekolah,
            elOptTtd, elTtdNama, elTtdJabatan
        ];
        controls.forEach(function (el) {
            if (!el) return;
            var ev = (el.type === 'checkbox' || el.tagName === 'SELECT') ? 'change' : 'input';
            el.addEventListener(ev, function () { render(); });
        });

        // Tombol cetak
        if (elBtnCetak) {
            elBtnCetak.addEventListener('click', function () {
                window.print();
            });
        }

        // Render awal
        render();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
