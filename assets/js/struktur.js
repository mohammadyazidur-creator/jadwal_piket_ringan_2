/**
 * assets/js/struktur.js - Interaktivitas Bagan Struktur Organisasi (Tahap 3)
 * Fitur:
 * - Drag & drop node (mouse & sentuhan HP)
 * - Garis SVG dinamis (lengkung, siku, lurus)
 * - Pan & Zoom kanvas, reset, fit view, dan auto-layout pohon
 * - Tambah, ubah, dan hapus node (dialog reparent / cascade)
 * - Pemilihan sumber nama (data siswa vs ketik manual) & keterangan tambahan
 * - Kustomisasi gaya per node (font, ukuran, format, warna, bentuk, lebar) + live preview
 * - Pengaturan bagan global (gaya garis, tebal, warna, grid kanvas on/off)
 * - Terapkan gaya ke semua node & reset ke gaya bawaan
 * - Deteksi siklus/loop hierarki di frontend & backend
 * - Status belum disimpan (dirty state) & peringatan beforeunload
 * - Undo / Redo sederhana
 * - Sesuai AGENTS.md: Nilai warna default dari CSS variable, single render node, modular.
 */

window.StrukturEditor = (function() {
    'use strict';

    // Konfigurasi & State
    let config = {
        viewportId: 'strukturViewport',
        contentId: 'canvasContent',
        svgId: 'svgLayer',
        nodesLayerId: 'nodesLayer',
        zoomBadgeId: 'zoomBadge',
        strukturId: 0,
        csrfToken: '',
        daftarSiswa: [],
        gayaBagan: {
            lineStyle: 'curve',
            lineWidth: 2,
            lineColor: 'primary-blue',
            grid: true
        },
        nodes: []
    };

    let viewportEl = null;
    let contentEl = null;
    let svgEl = null;
    let nodesLayerEl = null;
    let zoomBadgeEl = null;

    // State Transformasi Kanvas (Pan & Zoom)
    let panX = 0;
    let panY = 0;
    let zoom = 1.0;
    const MIN_ZOOM = 0.35;
    const MAX_ZOOM = 2.2;

    // State Interaksi Kanvas
    let isPanning = false;
    let panStart = { x: 0, y: 0, pointerId: null };
    let activeDrag = null; // Node yang sedang digeser

    // Status Belum Disimpan
    let isDirty = false;

    // History Stack untuk Undo / Redo
    let historyStack = [];
    let historyIndex = -1;
    const MAX_HISTORY = 30;

    // Modals
    let nodeFormModal = null;
    let deleteNodeModal = null;
    let chartSettingsModal = null;

    // Cache Node yang sedang diedit / dihapus
    let currentEditNodeId = 0;
    let currentDeleteNodeId = 0;

    /**
     * Mengambil nilai warna dari CSS variable di style.css
     */
    function getCssColor(varName, fallbackVarName) {
        if (typeof StrukturRender !== 'undefined') {
            return StrukturRender.getCssColor(varName, fallbackVarName);
        }
        const rootStyle = getComputedStyle(document.documentElement);
        let val = rootStyle.getPropertyValue(varName).trim();
        if (!val && fallbackVarName) {
            val = rootStyle.getPropertyValue(fallbackVarName).trim();
        }
        return val || '';
    }

    /**
     * Resolusi warna (apakah hex #RRGGBB atau nama kunci warna CSS)
     */
    function resolveColor(colorVal, defaultColorKey) {
        if (typeof StrukturRender !== 'undefined') {
            return StrukturRender.resolveColor(colorVal, defaultColorKey);
        }
        if (!colorVal) {
            return getCssColor(`--node-${defaultColorKey || 'blue'}`, '--primary-blue') || '#2563eb';
        }
        if (colorVal.startsWith('#')) {
            return colorVal;
        }
        return getCssColor(`--node-${colorVal}`, `--${colorVal}`) || getCssColor('--primary-blue') || '#2563eb';
    }

    /**
     * Inisialisasi editor struktur
     */
    function init(userConfig) {
        config = Object.assign({}, config, userConfig);

        viewportEl = document.getElementById(config.viewportId);
        contentEl = document.getElementById(config.contentId);
        svgEl = document.getElementById(config.svgId);
        nodesLayerEl = document.getElementById(config.nodesLayerId);
        zoomBadgeEl = document.getElementById(config.zoomBadgeId);

        if (!viewportEl || !contentEl || !svgEl || !nodesLayerEl) {
            console.error('StrukturEditor: Elemen kontainer kanvas tidak lengkap.');
            return;
        }

        // Ambil token CSRF dari elemen meta/input
        const csrfEl = document.getElementById('csrfToken');
        if (csrfEl && csrfEl.value) {
            config.csrfToken = csrfEl.value;
        }

        // Inisialisasi Bootstrap Modals jika tersedia
        const modalFormEl = document.getElementById('modalNodeForm');
        if (modalFormEl && window.bootstrap) {
            nodeFormModal = new bootstrap.Modal(modalFormEl);
        }

        const modalDelEl = document.getElementById('modalHapusNode');
        if (modalDelEl && window.bootstrap) {
            deleteNodeModal = new bootstrap.Modal(modalDelEl);
        }

        const modalChartEl = document.getElementById('modalPengaturanBagan');
        if (modalChartEl && window.bootstrap) {
            chartSettingsModal = new bootstrap.Modal(modalChartEl);
        }

        // Terapkan pengaturan bagan awal
        applyChartSettingsToCanvas(config.gayaBagan);

        // Pasang kontrol kanvas & toolbar
        setupCanvasControls();
        setupToolbarActions();
        setupModalEventListeners();

        // Simpan snapshot awal ke history
        pushHistory('Inisialisasi Kanvas');

        // Render seluruh node dari data awal
        renderNodes();

        // Gambar garis koneksi awal
        renderConnections();

        // Pusatkan bagan sesuai ukuran layar
        setTimeout(function() {
            fitView();
        }, 80);

        // Pasang listener resize & peringatan beforeunload
        window.addEventListener('resize', handleWindowResize);
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Pasang keyboard shortcuts (Ctrl+Z, Ctrl+Y, Ctrl+S)
        setupKeyboardShortcuts();
    }

    /**
     * Memasang kontrol pan dan zoom pada kanvas
     */
    function setupCanvasControls() {
        const btnZoomIn = document.getElementById('btnZoomIn');
        if (btnZoomIn) {
            btnZoomIn.addEventListener('click', function(e) {
                e.preventDefault();
                zoomAtCenter(1.15);
            });
        }

        const btnZoomOut = document.getElementById('btnZoomOut');
        if (btnZoomOut) {
            btnZoomOut.addEventListener('click', function(e) {
                e.preventDefault();
                zoomAtCenter(0.85);
            });
        }

        const btnZoomReset = document.getElementById('btnZoomReset');
        if (btnZoomReset) {
            btnZoomReset.addEventListener('click', function(e) {
                e.preventDefault();
                resetView();
            });
        }

        const btnZoomFit = document.getElementById('btnZoomFit');
        if (btnZoomFit) {
            btnZoomFit.addEventListener('click', function(e) {
                e.preventDefault();
                fitView();
            });
        }

        // Pan Kanvas dengan Pointer Events
        viewportEl.addEventListener('pointerdown', handleCanvasPointerDown);
        viewportEl.addEventListener('pointermove', handleCanvasPointerMove);
        viewportEl.addEventListener('pointerup', handleCanvasPointerUp);
        viewportEl.addEventListener('pointercancel', handleCanvasPointerUp);

        // Zoom kanvas dengan scroll mouse
        viewportEl.addEventListener('wheel', handleCanvasWheel, { passive: false });
    }

    /**
     * Memasang tombol aksi toolbar (Tambah Node, Simpan, Undo, Redo, dsb.)
     */
    function setupToolbarActions() {
        // Tombol Tambah Node
        const btnAdd = document.getElementById('btnAddNode');
        if (btnAdd) {
            btnAdd.classList.remove('disabled');
            btnAdd.removeAttribute('disabled');
            btnAdd.addEventListener('click', function(e) {
                e.preventDefault();
                openAddNodeModal();
            });
        }

        // Tombol Simpan Posisi & Perubahan
        const btnSave = document.getElementById('btnSaveCanvas');
        if (btnSave) {
            btnSave.classList.remove('disabled');
            btnSave.removeAttribute('disabled');
            btnSave.addEventListener('click', function(e) {
                e.preventDefault();
                saveAllChanges();
            });
        }

        // Tombol Undo
        const btnUndo = document.getElementById('btnUndo');
        if (btnUndo) {
            btnUndo.addEventListener('click', function(e) {
                e.preventDefault();
                undo();
            });
        }

        // Tombol Redo
        const btnRedo = document.getElementById('btnRedo');
        if (btnRedo) {
            btnRedo.addEventListener('click', function(e) {
                e.preventDefault();
                redo();
            });
        }

        // Tombol Rapikan Otomatis (Hierarchical Tree Auto-Layout)
        const btnAutoLayout = document.getElementById('btnAutoLayout');
        if (btnAutoLayout) {
            btnAutoLayout.addEventListener('click', function(e) {
                e.preventDefault();
                autoLayoutTree();
            });
        }

        // Tombol Pengaturan Bagan Global
        const btnChartSettings = document.getElementById('btnChartSettings');
        if (btnChartSettings) {
            btnChartSettings.addEventListener('click', function(e) {
                e.preventDefault();
                openChartSettingsModal();
            });
        }
    }

    /**
     * Memasang keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        window.addEventListener('keydown', function(e) {
            // Hindari shortcut jika kursor sedang aktif di input/textarea
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                return;
            }

            if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
                e.preventDefault();
                if (e.shiftKey) {
                    redo();
                } else {
                    undo();
                }
            } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || e.key === 'Y')) {
                e.preventDefault();
                redo();
            } else if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                saveAllChanges();
            }
        });
    }

    /**
     * Status Perubahan Belum Disimpan (Dirty State)
     */
    function setDirty(dirty) {
        isDirty = !!dirty;
        const btnSave = document.getElementById('btnSaveCanvas');
        const badgeDirty = document.getElementById('badgeUnsaved');

        if (btnSave) {
            if (isDirty) {
                btnSave.classList.add('btn-save-dirty');
                btnSave.classList.remove('btn-outline-primary');
                btnSave.classList.add('btn-primary');
                if (badgeDirty) badgeDirty.style.display = 'inline-block';
            } else {
                btnSave.classList.remove('btn-save-dirty');
                if (badgeDirty) badgeDirty.style.display = 'none';
            }
        }
    }

    function handleBeforeUnload(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'Perubahan struktur belum disimpan. Yakin ingin meninggalkan halaman?';
            return e.returnValue;
        }
    }

    // =========================================================================
    // HISTORY STACK (UNDO / REDO)
    // =========================================================================

    function pushHistory(desc) {
        // Potong history ke depan jika pernah melakukan undo lalu membuat aksi baru
        if (historyIndex < historyStack.length - 1) {
            historyStack = historyStack.slice(0, historyIndex + 1);
        }

        const snapshot = {
            desc: desc || 'Perubahan',
            nodes: JSON.parse(JSON.stringify(config.nodes)),
            gayaBagan: JSON.parse(JSON.stringify(config.gayaBagan))
        };

        historyStack.push(snapshot);
        if (historyStack.length > MAX_HISTORY) {
            historyStack.shift();
        }
        historyIndex = historyStack.length - 1;

        updateUndoRedoButtons();
    }

    function undo() {
        if (historyIndex > 0) {
            historyIndex--;
            restoreState(historyStack[historyIndex]);
            setDirty(true);
            updateUndoRedoButtons();
        }
    }

    function redo() {
        if (historyIndex < historyStack.length - 1) {
            historyIndex++;
            restoreState(historyStack[historyIndex]);
            setDirty(true);
            updateUndoRedoButtons();
        }
    }

    function restoreState(snapshot) {
        if (!snapshot) return;
        config.nodes = JSON.parse(JSON.stringify(snapshot.nodes));
        config.gayaBagan = JSON.parse(JSON.stringify(snapshot.gayaBagan));

        applyChartSettingsToCanvas(config.gayaBagan);
        renderNodes();
        renderConnections();
        updateNodeCountBadge();
    }

    function updateUndoRedoButtons() {
        const btnUndo = document.getElementById('btnUndo');
        const btnRedo = document.getElementById('btnRedo');

        if (btnUndo) {
            btnUndo.disabled = (historyIndex <= 0);
            btnUndo.classList.toggle('opacity-50', historyIndex <= 0);
        }
        if (btnRedo) {
            btnRedo.disabled = (historyIndex >= historyStack.length - 1);
            btnRedo.classList.toggle('opacity-50', historyIndex >= historyStack.length - 1);
        }
    }

    // =========================================================================
    // KANVAS TRANSFORMATION (PAN & ZOOM)
    // =========================================================================

    function handleCanvasPointerDown(e) {
        if (e.target.closest('.struktur-node') || e.target.closest('button')) {
            return;
        }

        if (e.button && e.button !== 0 && e.pointerType === 'mouse') {
            return;
        }

        isPanning = true;
        panStart = {
            x: e.clientX - panX,
            y: e.clientY - panY,
            pointerId: e.pointerId
        };

        try {
            viewportEl.setPointerCapture(e.pointerId);
        } catch (err) {}

        viewportEl.classList.add('is-panning');
    }

    function handleCanvasPointerMove(e) {
        if (!isPanning || panStart.pointerId !== e.pointerId) {
            return;
        }

        panX = Math.round(e.clientX - panStart.x);
        panY = Math.round(e.clientY - panStart.y);
        applyTransform();
    }

    function handleCanvasPointerUp(e) {
        if (isPanning && panStart.pointerId === e.pointerId) {
            isPanning = false;
            try {
                viewportEl.releasePointerCapture(e.pointerId);
            } catch (err) {}
            viewportEl.classList.remove('is-panning');
        }
    }

    function handleCanvasWheel(e) {
        e.preventDefault();

        const rect = viewportEl.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const factor = e.deltaY < 0 ? 1.12 : 0.88;
        zoomAtPoint(factor, mouseX, mouseY);
    }

    function zoomAtPoint(factor, pointX, pointY) {
        const oldZoom = zoom;
        let newZoom = oldZoom * factor;
        newZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, newZoom));

        if (newZoom === oldZoom) return;

        panX = pointX - (pointX - panX) * (newZoom / oldZoom);
        panY = pointY - (pointY - panY) * (newZoom / oldZoom);
        zoom = newZoom;

        applyTransform();
    }

    function zoomAtCenter(factor) {
        const rect = viewportEl.getBoundingClientRect();
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        zoomAtPoint(factor, centerX, centerY);
    }

    function applyTransform() {
        contentEl.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
        if (zoomBadgeEl) {
            zoomBadgeEl.textContent = Math.round(zoom * 100) + '%';
        }
    }

    function resetView() {
        zoom = 1.0;
        panX = 0;
        panY = 0;
        applyTransform();
    }

    function fitView() {
        if (!config.nodes || config.nodes.length === 0) {
            resetView();
            return;
        }

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        config.nodes.forEach(function(node) {
            const x = parseFloat(node.pos_x) || 0;
            const y = parseFloat(node.pos_y) || 0;
            const w = getNodeWidth(node);
            const h = 100;

            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x + w > maxX) maxX = x + w;
            if (y + h > maxY) maxY = y + h;
        });

        const boxWidth = maxX - minX;
        const boxHeight = maxY - minY;

        const viewportWidth = viewportEl.clientWidth || 800;
        const viewportHeight = viewportEl.clientHeight || 500;

        const padding = 60;
        const availableW = Math.max(100, viewportWidth - (padding * 2));
        const availableH = Math.max(100, viewportHeight - (padding * 2));

        const scaleX = availableW / Math.max(boxWidth, 200);
        const scaleY = availableH / Math.max(boxHeight, 200);
        let optimalZoom = Math.min(scaleX, scaleY);
        optimalZoom = Math.max(MIN_ZOOM, Math.min(1.05, optimalZoom));

        const centerX = minX + (boxWidth / 2);
        const centerY = minY + (boxHeight / 2);

        panX = Math.round((viewportWidth / 2) - (centerX * optimalZoom));
        panY = Math.round((viewportHeight / 2) - (centerY * optimalZoom));
        zoom = optimalZoom;

        applyTransform();
    }

    function getNodeWidth(node) {
        if (typeof StrukturRender !== 'undefined') {
            return StrukturRender.getNodeWidth(node);
        }
        let style = parseNodeStyle(node.gaya_json);
        if (style && style.width === 'small') return 180;
        if (style && style.width === 'large') return 280;
        return 230; // Medium
    }

    function parseNodeStyle(gaya) {
        if (typeof StrukturRender !== 'undefined') {
            return StrukturRender.parseNodeStyle(gaya);
        }
        if (!gaya) return null;
        if (typeof gaya === 'object') return gaya;
        try {
            return JSON.parse(gaya);
        } catch (e) {
            return null;
        }
    }

    // =========================================================================
    // SINGLE NODE RENDERER (DIPAKAI OLEH EDITOR, PREVIEW, DAN CETAK)
    // =========================================================================

    /**
     * Membuat elemen DOM kartu node tunggal
     * Mematuhi aturan AGENTS.md: Menggunakan kelas CSS dari style.css,
     * gaya dinamis disalurkan via CSS custom property pada elemen.
     *
     * @param {Object} node Data node
     * @param {Object} options Opsi render { isInteractive: true/false }
     * @returns {HTMLElement} Elemen DOM kartu node
     */
    function createNodeElement(node, options) {
        options = options || { isInteractive: true };
        if (typeof StrukturRender !== 'undefined') {
            return StrukturRender.createNodeElement(node, Object.assign({
                onEdit: openEditNodeModal,
                onDelete: openDeleteNodeModal,
                onDrag: setupNodeDragging
            }, options));
        }

        const nodeEl = document.createElement('div');
        const warnaKey = (node.warna || 'blue').toLowerCase();
        nodeEl.className = `struktur-node struktur-node-${warnaKey}`;
        if (node.id) {
            nodeEl.id = `node-${node.id}`;
            nodeEl.setAttribute('data-id', node.id);
        }

        // Terapkan gaya kustom dari gaya_json via CSS custom property
        const gaya = parseNodeStyle(node.gaya_json);
        if (gaya) {
            if (gaya.shape === 'square') nodeEl.classList.add('shape-square');
            if (gaya.shape === 'pill') nodeEl.classList.add('shape-pill');

            if (gaya.width === 'small') nodeEl.classList.add('width-small');
            if (gaya.width === 'large') nodeEl.classList.add('width-large');
            if (gaya.width === 'medium') nodeEl.classList.add('width-medium');

            if (gaya.fontFamily) nodeEl.style.setProperty('--node-custom-font', gaya.fontFamily);
            if (gaya.fontSize) nodeEl.style.setProperty('--node-custom-size', `${gaya.fontSize}px`);
            if (gaya.fontWeight) nodeEl.style.setProperty('--node-custom-weight', gaya.fontWeight);
            if (gaya.fontStyle) nodeEl.style.setProperty('--node-custom-style', gaya.fontStyle);
            if (gaya.textAlign) nodeEl.style.setProperty('--node-custom-align', gaya.textAlign);

            if (gaya.colorHeader) {
                const c = resolveColor(gaya.colorHeader, warnaKey);
                nodeEl.style.setProperty('--node-custom-header-bg', c);
            }
            if (gaya.colorBg) {
                const c = resolveColor(gaya.colorBg, 'white');
                nodeEl.style.setProperty('--node-custom-bg', c);
            }
            if (gaya.colorText) {
                const c = resolveColor(gaya.colorText, 'dark-blue');
                nodeEl.style.setProperty('--node-custom-text', c);
            }
            if (gaya.colorBorder) {
                const c = resolveColor(gaya.colorBorder, warnaKey);
                nodeEl.style.setProperty('--node-custom-border', c);
            }
        }

        // Posisi absolut jika interaktif / di kanvas
        if (options.isInteractive) {
            nodeEl.style.left = `${node.pos_x || 100}px`;
            nodeEl.style.top = `${node.pos_y || 100}px`;

            // Titik koneksi atas & bawah (port visual)
            const portTop = document.createElement('div');
            portTop.className = 'struktur-node-port struktur-port-top';
            const portBottom = document.createElement('div');
            portBottom.className = 'struktur-node-port struktur-port-bottom';

            nodeEl.appendChild(portTop);
            nodeEl.appendChild(portBottom);

            // Tombol Aksi di Sudut Kanan Atas (Ubah & Hapus)
            const actionsEl = document.createElement('div');
            actionsEl.className = 'struktur-node-actions';

            const btnEdit = document.createElement('button');
            btnEdit.type = 'button';
            btnEdit.className = 'btn-node-action btn-node-edit';
            btnEdit.title = 'Ubah Node Jabatan';
            btnEdit.innerHTML = '<i class="fas fa-edit"></i>';
            btnEdit.addEventListener('click', function(e) {
                e.stopPropagation();
                openEditNodeModal(node.id);
            });

            const btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.className = 'btn-node-action btn-node-delete';
            btnDelete.title = 'Hapus Node Jabatan';
            btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
            btnDelete.addEventListener('click', function(e) {
                e.stopPropagation();
                openDeleteNodeModal(node.id);
            });

            actionsEl.appendChild(btnEdit);
            actionsEl.appendChild(btnDelete);
            nodeEl.appendChild(actionsEl);
        }

        // Header Jabatan
        const headerEl = document.createElement('div');
        headerEl.className = 'struktur-node-header';
        headerEl.textContent = node.jabatan || 'Jabatan';
        headerEl.title = node.jabatan || '';
        nodeEl.appendChild(headerEl);

        // Body Info Person
        const bodyEl = document.createElement('div');
        bodyEl.className = 'struktur-node-body';

        // Avatar foto / ikon (sembunyikan jika showIcon == false)
        const showIcon = !gaya || gaya.showIcon !== false;
        if (showIcon) {
            const avatarEl = document.createElement('div');
            avatarEl.className = 'struktur-node-avatar';

            if (node.foto && node.foto.trim() !== '') {
                const img = document.createElement('img');
                img.src = `uploads/struktur/${node.foto}`;
                img.alt = node.nama || 'Foto';
                img.onerror = function() {
                    avatarEl.innerHTML = '<i class="fas fa-user"></i>';
                };
                avatarEl.appendChild(img);
            } else {
                avatarEl.innerHTML = '<i class="fas fa-user"></i>';
            }
            bodyEl.appendChild(avatarEl);
        }

        // Teks Informasi
        const infoEl = document.createElement('div');
        infoEl.className = 'struktur-node-info';

        const namaEl = document.createElement('div');
        namaEl.className = 'struktur-node-nama';
        namaEl.textContent = node.nama || '(Belum Ditentukan)';
        namaEl.title = node.nama || '';
        infoEl.appendChild(namaEl);

        // Keterangan Tambahan (NIP/kontak/dsb.) jika ada
        if (node.keterangan && node.keterangan.trim() !== '') {
            const ketEl = document.createElement('div');
            ketEl.className = 'struktur-node-keterangan';
            ketEl.textContent = node.keterangan;
            ketEl.title = node.keterangan;
            infoEl.appendChild(ketEl);
        }

        // Subtitle (NIS atau status keanggotaan)
        const subEl = document.createElement('div');
        subEl.className = 'struktur-node-sub';
        if (node.nis) {
            subEl.textContent = `NIS: ${node.nis}`;
        } else if (node.siswa_id) {
            subEl.textContent = 'Siswa Terdaftar';
        } else if (node.keterangan) {
            // Jika sudah ada keterangan, sub status tidak perlu mendominasi
            subEl.textContent = 'Anggota';
        } else {
            subEl.textContent = 'Anggota Organisasi';
        }
        infoEl.appendChild(subEl);

        bodyEl.appendChild(infoEl);
        nodeEl.appendChild(bodyEl);

        // Pasang event geser jika interaktif
        if (options.isInteractive) {
            setupNodeDragging(nodeEl, node);
        }

        return nodeEl;
    }

    /**
     * Render seluruh node dalam kanvas
     */
    function renderNodes() {
        nodesLayerEl.innerHTML = '';
        config.nodes.forEach(function(node) {
            const nodeEl = createNodeElement(node, { isInteractive: true });
            nodesLayerEl.appendChild(nodeEl);
        });
        updateNodeCountBadge();
    }

    function updateNodeCountBadge() {
        const badge = document.getElementById('nodeCountBadge');
        if (badge) {
            badge.textContent = config.nodes.length;
        }
    }

    /**
     * Memasang event pointer drag pada node
     */
    function setupNodeDragging(nodeEl, node) {
        let hasMoved = false;

        nodeEl.addEventListener('pointerdown', function(e) {
            if (e.target.closest('button')) {
                return;
            }
            if (e.button && e.button !== 0 && e.pointerType === 'mouse') {
                return;
            }

            e.stopPropagation();

            try {
                nodeEl.setPointerCapture(e.pointerId);
            } catch (err) {}

            hasMoved = false;
            activeDrag = {
                pointerId: e.pointerId,
                nodeId: node.id,
                nodeEl: nodeEl,
                nodeData: node,
                startX: e.clientX,
                startY: e.clientY,
                initialX: parseFloat(node.pos_x) || 0,
                initialY: parseFloat(node.pos_y) || 0
            };

            nodeEl.classList.add('is-dragging');
        });

        nodeEl.addEventListener('pointermove', function(e) {
            if (!activeDrag || activeDrag.pointerId !== e.pointerId) {
                return;
            }

            e.stopPropagation();

            const deltaX = (e.clientX - activeDrag.startX) / zoom;
            const deltaY = (e.clientY - activeDrag.startY) / zoom;

            if (Math.abs(deltaX) > 2 || Math.abs(deltaY) > 2) {
                hasMoved = true;
            }

            let newX = Math.round(activeDrag.initialX + deltaX);
            let newY = Math.round(activeDrag.initialY + deltaY);

            if (newX < 20) newX = 20;
            if (newY < 20) newY = 20;

            activeDrag.nodeEl.style.left = `${newX}px`;
            activeDrag.nodeEl.style.top = `${newY}px`;

            activeDrag.nodeData.pos_x = newX;
            activeDrag.nodeData.pos_y = newY;

            renderConnections();
        });

        nodeEl.addEventListener('pointerup', endNodeDrag);
        nodeEl.addEventListener('pointercancel', endNodeDrag);

        function endNodeDrag(e) {
            if (activeDrag && activeDrag.pointerId === e.pointerId) {
                try {
                    activeDrag.nodeEl.releasePointerCapture(e.pointerId);
                } catch (err) {}

                activeDrag.nodeEl.classList.remove('is-dragging');
                activeDrag = null;
                renderConnections();

                if (hasMoved) {
                    setDirty(true);
                    pushHistory('Geser Node');
                }
            }
        }
    }

    // =========================================================================
    // GARIS PENGHUBUNG KONEKSI SVG (LENGKUNG, SIKU, LURUS)
    // =========================================================================

    /**
     * Terapkan konfigurasi gaya bagan global ke kanvas
     */
    function applyChartSettingsToCanvas(gayaBagan) {
        if (!gayaBagan) return;
        config.gayaBagan = Object.assign({}, config.gayaBagan, gayaBagan);

        // Grid kanvas On / Off
        if (viewportEl) {
            if (config.gayaBagan.grid === false) {
                viewportEl.classList.add('no-grid');
                viewportEl.classList.remove('has-grid');
            } else {
                viewportEl.classList.add('has-grid');
                viewportEl.classList.remove('no-grid');
            }
        }

        renderConnections();
    }

    /**
     * Menggambar seluruh garis penghubung antar node
     */
    function renderConnections() {
        if (!svgEl) return;
        if (typeof StrukturRender !== 'undefined') {
            StrukturRender.renderConnections(svgEl, config.nodes, config.gayaBagan);
            return;
        }
        svgEl.innerHTML = '';

        const lineStyle = config.gayaBagan.lineStyle || 'curve';
        const lineWidth = config.gayaBagan.lineWidth || 2;
        const lineColorRaw = config.gayaBagan.lineColor || 'primary-blue';
        const resolvedLineColor = resolveColor(lineColorRaw, 'primary-blue');

        config.nodes.forEach(function(node) {
            if (!node.parent_id) {
                return;
            }

            const parentNode = config.nodes.find(function(n) {
                return String(n.id) === String(node.parent_id);
            });

            if (!parentNode) {
                return;
            }

            const childEl = document.getElementById(`node-${node.id}`);
            const parentEl = document.getElementById(`node-${parentNode.id}`);

            const pWidth = parentEl ? parentEl.offsetWidth : getNodeWidth(parentNode);
            const pHeight = parentEl ? parentEl.offsetHeight : 90;
            const cWidth = childEl ? childEl.offsetWidth : getNodeWidth(node);

            const pX = parseFloat(parentNode.pos_x) || 0;
            const pY = parseFloat(parentNode.pos_y) || 0;
            const cX = parseFloat(node.pos_x) || 0;
            const cY = parseFloat(node.pos_y) || 0;

            const x1 = pX + (pWidth / 2);
            const y1 = pY + pHeight;
            const x2 = cX + (cWidth / 2);
            const y2 = cY;

            let pathD = '';
            if (lineStyle === 'straight') {
                // Garis Lurus
                pathD = `M ${x1} ${y1} L ${x2} ${y2}`;
            } else if (lineStyle === 'step') {
                // Garis Siku (Orthogonal Step)
                const midY = y1 + ((y2 - y1) / 2);
                pathD = `M ${x1} ${y1} L ${x1} ${midY} L ${x2} ${midY} L ${x2} ${y2}`;
            } else {
                // Default: Garis Lengkung Halus (Cubic Bezier)
                const deltaY = Math.max(Math.abs(y2 - y1) * 0.5, 30);
                const cp1x = x1;
                const cp1y = y1 + deltaY;
                const cp2x = x2;
                const cp2y = y2 - deltaY;
                pathD = `M ${x1} ${y1} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x2} ${y2}`;
            }

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathD);
            path.setAttribute('class', 'struktur-line');
            path.style.stroke = resolvedLineColor;
            path.style.strokeWidth = `${lineWidth}px`;

            svgEl.appendChild(path);
        });
    }

    function handleWindowResize() {
        renderConnections();
    }

    // =========================================================================
    // AUTO-LAYOUT POHON (HIERARCHICAL TREE LAYOUT)
    // =========================================================================

    function autoLayoutTree() {
        if (!config.nodes || config.nodes.length === 0) return;

        // Bangun adjacency tree
        const childrenMap = {};
        const nodeMap = {};
        const roots = [];

        config.nodes.forEach(function(n) {
            nodeMap[n.id] = n;
            childrenMap[n.id] = [];
        });

        config.nodes.forEach(function(n) {
            if (n.parent_id && nodeMap[n.parent_id]) {
                childrenMap[n.parent_id].push(n);
            } else {
                roots.push(n);
            }
        });

        // Hitung lebar subtree dan tata letak secara rekursif
        const horizontalGap = 40;
        const verticalGap = 130;
        let currentLeft = 60;

        function measureSubtree(node) {
            const children = childrenMap[node.id];
            if (!children || children.length === 0) {
                node._treeWidth = getNodeWidth(node);
                return node._treeWidth;
            }
            let totalW = 0;
            children.forEach(function(c, i) {
                totalW += measureSubtree(c);
                if (i < children.length - 1) totalW += horizontalGap;
            });
            node._treeWidth = Math.max(getNodeWidth(node), totalW);
            return node._treeWidth;
        }

        function positionSubtree(node, startX, levelY) {
            const w = getNodeWidth(node);
            const subtreeW = node._treeWidth;
            const nodeX = startX + (subtreeW / 2) - (w / 2);

            node.pos_x = Math.round(nodeX);
            node.pos_y = Math.round(levelY);

            const children = childrenMap[node.id];
            let childX = startX;
            children.forEach(function(c) {
                positionSubtree(c, childX, levelY + verticalGap);
                childX += c._treeWidth + horizontalGap;
            });
        }

        roots.forEach(function(r) {
            measureSubtree(r);
            positionSubtree(r, currentLeft, 60);
            currentLeft += r._treeWidth + horizontalGap * 2;
        });

        renderNodes();
        renderConnections();
        setDirty(true);
        pushHistory('Rapikan Otomatis (Tree Layout)');
        fitView();
    }

    // =========================================================================
    // MODAL TAMBAH & UBAH NODE + LIVE PREVIEW + CEGAH LOOP
    // =========================================================================

    function openAddNodeModal(defaultParentId) {
        currentEditNodeId = 0;
        const modalTitle = document.getElementById('modalNodeFormTitle');
        if (modalTitle) modalTitle.textContent = 'Tambah Node Jabatan';

        // Reset form
        document.getElementById('nodeFormId').value = '0';
        document.getElementById('nodeJabatan').value = '';
        document.getElementById('nodeKeterangan').value = '';
        document.getElementById('nodeNamaManual').value = '';
        document.getElementById('nodeSiswaSelect').value = '';
        document.getElementById('nodeFotoInput').value = '';

        // Reset foto preview & hapus foto checkbox
        const fotoPreviewWrap = document.getElementById('nodeFotoPreviewWrap');
        if (fotoPreviewWrap) fotoPreviewWrap.style.display = 'none';
        const checkHapusFoto = document.getElementById('checkHapusFoto');
        if (checkHapusFoto) {
            checkHapusFoto.checked = false;
            checkHapusFoto.parentElement.style.display = 'none';
        }

        // Sumber nama bawaan: Siswa jika ada daftar siswa, selain itu Manual
        setSumberNama('siswa');

        // Reset warna & gaya
        selectWarna('blue');
        resetNodeFormStyleInputs();

        // Isi dropdown atasan tanpa loop
        populateParentDropdown(0, defaultParentId || 0);

        // Perbarui live preview
        updateNodeModalLivePreview();

        if (nodeFormModal) {
            nodeFormModal.show();
        }
    }

    function openEditNodeModal(nodeId) {
        const node = config.nodes.find(n => String(n.id) === String(nodeId));
        if (!node) return;

        currentEditNodeId = node.id;
        const modalTitle = document.getElementById('modalNodeFormTitle');
        if (modalTitle) modalTitle.textContent = `Ubah Node Jabatan: ${node.jabatan || ''}`;

        document.getElementById('nodeFormId').value = node.id;
        document.getElementById('nodeJabatan').value = node.jabatan || '';
        document.getElementById('nodeKeterangan').value = node.keterangan || '';
        document.getElementById('nodeFotoInput').value = '';

        // Foto preview jika sudah ada
        const fotoPreviewWrap = document.getElementById('nodeFotoPreviewWrap');
        const fotoPreviewImg = document.getElementById('nodeFotoPreviewImg');
        const checkHapusFoto = document.getElementById('checkHapusFoto');
        if (node.foto && node.foto.trim() !== '') {
            if (fotoPreviewWrap) fotoPreviewWrap.style.display = 'block';
            if (fotoPreviewImg) fotoPreviewImg.src = `uploads/struktur/${node.foto}`;
            if (checkHapusFoto) {
                checkHapusFoto.checked = false;
                checkHapusFoto.parentElement.style.display = 'block';
            }
        } else {
            if (fotoPreviewWrap) fotoPreviewWrap.style.display = 'none';
            if (checkHapusFoto) checkHapusFoto.parentElement.style.display = 'none';
        }

        // Sumber nama
        if (node.siswa_id) {
            setSumberNama('siswa');
            document.getElementById('nodeSiswaSelect').value = node.siswa_id;
            document.getElementById('nodeNamaManual').value = node.nama || '';
        } else {
            setSumberNama('manual');
            document.getElementById('nodeNamaManual').value = node.nama || '';
            document.getElementById('nodeSiswaSelect').value = '';
        }

        // Warna
        selectWarna(node.warna || 'blue');

        // Isi gaya kustom
        const gaya = parseNodeStyle(node.gaya_json);
        applyStyleToModalInputs(gaya);

        // Isi dropdown atasan dengan memfilter dirinya dan seluruh turunannya (CEGAH LOOP)
        populateParentDropdown(node.id, node.parent_id || 0);

        // Perbarui live preview
        updateNodeModalLivePreview();

        if (nodeFormModal) {
            nodeFormModal.show();
        }
    }

    function setSumberNama(tipe) {
        const radioSiswa = document.getElementById('sumberNamaSiswa');
        const radioManual = document.getElementById('sumberNamaManual');
        const boxSiswa = document.getElementById('boxPilihSiswa');
        const boxManual = document.getElementById('boxKetikManual');

        if (tipe === 'siswa') {
            if (radioSiswa) radioSiswa.checked = true;
            if (boxSiswa) boxSiswa.style.display = 'block';
            if (boxManual) boxManual.style.display = 'none';
        } else {
            if (radioManual) radioManual.checked = true;
            if (boxSiswa) boxSiswa.style.display = 'none';
            if (boxManual) boxManual.style.display = 'block';
        }
        updateNodeModalLivePreview();
    }

    /**
     * Mengisi dropdown pilihan atasan, mengecualikan node itu sendiri & seluruh turunannya
     */
    function populateParentDropdown(currentNodeId, currentParentId) {
        const select = document.getElementById('nodeParentSelect');
        if (!select) return;

        select.innerHTML = '<option value="">(Tingkat Teratas / Tidak Ada Atasan)</option>';

        // Kumpulkan ID terlarang (node saat ini + semua keturunannya)
        const forbiddenIds = new Set();
        if (currentNodeId && currentNodeId > 0) {
            forbiddenIds.add(String(currentNodeId));
            function collectDescendants(parentId) {
                config.nodes.forEach(function(n) {
                    if (String(n.parent_id) === String(parentId)) {
                        forbiddenIds.add(String(n.id));
                        collectDescendants(n.id);
                    }
                });
            }
            collectDescendants(currentNodeId);
        }

        config.nodes.forEach(function(node) {
            if (forbiddenIds.has(String(node.id))) {
                return; // Lewati untuk mencegah loop
            }
            const opt = document.createElement('option');
            opt.value = node.id;
            opt.textContent = `${node.jabatan} - ${node.nama}`;
            if (String(node.id) === String(currentParentId)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function selectWarna(warnaKey) {
        warnaKey = (warnaKey || 'blue').toLowerCase();
        const hiddenInput = document.getElementById('nodeWarna');
        if (hiddenInput) hiddenInput.value = warnaKey;

        const swatches = document.querySelectorAll('#nodeWarnaSwatches .palette-swatch');
        swatches.forEach(function(sw) {
            sw.classList.toggle('active', sw.getAttribute('data-warna') === warnaKey);
        });

        updateNodeModalLivePreview();
    }

    function resetNodeFormStyleInputs() {
        document.getElementById('nodeFontFamily').value = 'Inter';
        document.getElementById('nodeFontSize').value = '14';
        document.getElementById('nodeFontSizeBadge').textContent = '14px';
        document.getElementById('nodeFontWeightBold').classList.remove('active');
        document.getElementById('nodeFontStyleItalic').classList.remove('active');

        setTextAlignInput('center');

        document.getElementById('nodeColorHeader').value = '#2563eb';
        document.getElementById('nodeColorBg').value = '#ffffff';
        document.getElementById('nodeColorText').value = '#1e3a8a';
        document.getElementById('nodeColorBorder').value = '#93c5fd';

        document.getElementById('nodeShape').value = 'rounded';
        document.getElementById('nodeWidth').value = 'medium';
        document.getElementById('nodeShowIcon').checked = true;
    }

    function applyStyleToModalInputs(gaya) {
        if (!gaya) {
            resetNodeFormStyleInputs();
            return;
        }

        if (gaya.fontFamily) document.getElementById('nodeFontFamily').value = gaya.fontFamily;
        if (gaya.fontSize) {
            document.getElementById('nodeFontSize').value = gaya.fontSize;
            document.getElementById('nodeFontSizeBadge').textContent = `${gaya.fontSize}px`;
        }

        const isBold = (gaya.fontWeight === 'bold');
        document.getElementById('nodeFontWeightBold').classList.toggle('active', isBold);

        const isItalic = (gaya.fontStyle === 'italic');
        document.getElementById('nodeFontStyleItalic').classList.toggle('active', isItalic);

        setTextAlignInput(gaya.textAlign || 'center');

        if (gaya.colorHeader) document.getElementById('nodeColorHeader').value = gaya.colorHeader;
        if (gaya.colorBg) document.getElementById('nodeColorBg').value = gaya.colorBg;
        if (gaya.colorText) document.getElementById('nodeColorText').value = gaya.colorText;
        if (gaya.colorBorder) document.getElementById('nodeColorBorder').value = gaya.colorBorder;

        if (gaya.shape) document.getElementById('nodeShape').value = gaya.shape;
        if (gaya.width) document.getElementById('nodeWidth').value = gaya.width;
        if (typeof gaya.showIcon !== 'undefined') {
            document.getElementById('nodeShowIcon').checked = (gaya.showIcon !== false);
        }
    }

    function setTextAlignInput(align) {
        ['left', 'center', 'right'].forEach(function(a) {
            const btn = document.getElementById(`nodeTextAlign_${a}`);
            if (btn) btn.classList.toggle('active', a === align);
        });
    }

    function getSelectedTextAlign() {
        const activeBtn = document.querySelector('.btn-align-group .btn.active');
        return activeBtn ? activeBtn.getAttribute('data-align') : 'center';
    }

    function collectNodeStyleFromModal() {
        const fontFamily = document.getElementById('nodeFontFamily').value;
        const fontSize = parseInt(document.getElementById('nodeFontSize').value, 10);
        const isBold = document.getElementById('nodeFontWeightBold').classList.contains('active');
        const isItalic = document.getElementById('nodeFontStyleItalic').classList.contains('active');
        const textAlign = getSelectedTextAlign();

        const colorHeader = document.getElementById('nodeColorHeader').value;
        const colorBg = document.getElementById('nodeColorBg').value;
        const colorText = document.getElementById('nodeColorText').value;
        const colorBorder = document.getElementById('nodeColorBorder').value;

        const shape = document.getElementById('nodeShape').value;
        const width = document.getElementById('nodeWidth').value;
        const showIcon = document.getElementById('nodeShowIcon').checked;

        return {
            fontFamily: fontFamily,
            fontSize: fontSize,
            fontWeight: isBold ? 'bold' : 'normal',
            fontStyle: isItalic ? 'italic' : 'normal',
            textAlign: textAlign,
            colorHeader: colorHeader,
            colorBg: colorBg,
            colorText: colorText,
            colorBorder: colorBorder,
            shape: shape,
            width: width,
            showIcon: showIcon
        };
    }

    /**
     * Pratinjau langsung kartu node di dalam modal
     */
    function updateNodeModalLivePreview() {
        const previewBox = document.getElementById('nodeModalLivePreview');
        if (!previewBox) return;

        const isSiswa = document.getElementById('sumberNamaSiswa').checked;
        let nama = '';
        let nis = '';

        if (isSiswa) {
            const selectSiswa = document.getElementById('nodeSiswaSelect');
            const selectedOpt = selectSiswa.options[selectSiswa.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                nama = selectedOpt.getAttribute('data-nama') || selectedOpt.text;
                nis = selectedOpt.getAttribute('data-nis') || '';
            } else {
                nama = '(Pilih Siswa)';
            }
        } else {
            nama = document.getElementById('nodeNamaManual').value || '(Nama Anggota)';
        }

        const jabatan = document.getElementById('nodeJabatan').value || 'Jabatan';
        const keterangan = document.getElementById('nodeKeterangan').value || '';
        const warna = document.getElementById('nodeWarna').value || 'blue';
        const gaya = collectNodeStyleFromModal();

        const dummyNode = {
            id: currentEditNodeId || 99999,
            jabatan: jabatan,
            nama: nama,
            keterangan: keterangan,
            nis: nis,
            warna: warna,
            gaya_json: gaya
        };

        // Jika ada foto saat edit
        if (currentEditNodeId) {
            const existing = config.nodes.find(n => n.id === currentEditNodeId);
            if (existing && existing.foto && !document.getElementById('checkHapusFoto').checked) {
                dummyNode.foto = existing.foto;
            }
        }

        previewBox.innerHTML = '';
        const renderedEl = createNodeElement(dummyNode, { isInteractive: false });
        previewBox.appendChild(renderedEl);
    }

    /**
     * Memasang event listener interaksi modal
     */
    function setupModalEventListeners() {
        // Switch sumber nama
        const rSiswa = document.getElementById('sumberNamaSiswa');
        const rManual = document.getElementById('sumberNamaManual');
        if (rSiswa) rSiswa.addEventListener('change', () => setSumberNama('siswa'));
        if (rManual) rManual.addEventListener('change', () => setSumberNama('manual'));

        // Siswa dropdown change
        const selSiswa = document.getElementById('nodeSiswaSelect');
        if (selSiswa) {
            selSiswa.addEventListener('change', function() {
                const opt = selSiswa.options[selSiswa.selectedIndex];
                if (opt && opt.value) {
                    document.getElementById('nodeNamaManual').value = opt.getAttribute('data-nama') || opt.text;
                }
                updateNodeModalLivePreview();
            });
        }

        // Input teks & keterangan change
        ['nodeJabatan', 'nodeKeterangan', 'nodeNamaManual'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateNodeModalLivePreview);
        });

        // Warna swatches click
        const swatches = document.querySelectorAll('#nodeWarnaSwatches .palette-swatch');
        swatches.forEach(function(sw) {
            sw.addEventListener('click', function() {
                const w = sw.getAttribute('data-warna');
                selectWarna(w);
            });
        });

        // Gaya inputs change
        ['nodeFontFamily', 'nodeShape', 'nodeWidth', 'nodeShowIcon'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', updateNodeModalLivePreview);
        });

        const fontRange = document.getElementById('nodeFontSize');
        if (fontRange) {
            fontRange.addEventListener('input', function() {
                document.getElementById('nodeFontSizeBadge').textContent = `${fontRange.value}px`;
                updateNodeModalLivePreview();
            });
        }

        // Bold & Italic toggle buttons
        const btnBold = document.getElementById('nodeFontWeightBold');
        if (btnBold) {
            btnBold.addEventListener('click', function() {
                btnBold.classList.toggle('active');
                updateNodeModalLivePreview();
            });
        }
        const btnItalic = document.getElementById('nodeFontStyleItalic');
        if (btnItalic) {
            btnItalic.addEventListener('click', function() {
                btnItalic.classList.toggle('active');
                updateNodeModalLivePreview();
            });
        }

        // Align buttons
        ['left', 'center', 'right'].forEach(function(a) {
            const btn = document.getElementById(`nodeTextAlign_${a}`);
            if (btn) {
                btn.addEventListener('click', function() {
                    setTextAlignInput(a);
                    updateNodeModalLivePreview();
                });
            }
        });

        // Color pickers change
        ['nodeColorHeader', 'nodeColorBg', 'nodeColorText', 'nodeColorBorder'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateNodeModalLivePreview);
        });

        // Tombol Reset Gaya di Tab Tampilan Modal
        const btnResetStyle = document.getElementById('btnResetStyle');
        if (btnResetStyle) {
            btnResetStyle.addEventListener('click', function(e) {
                e.preventDefault();
                resetNodeFormStyleInputs();
                updateNodeModalLivePreview();
            });
        }

        // Tombol Terapkan ke Semua Node di Modal
        const btnApplyAll = document.getElementById('btnApplyStyleAll');
        if (btnApplyAll) {
            btnApplyAll.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Terapkan gaya ini ke seluruh kotak jabatan dalam bagan?')) {
                    applyCurrentModalStyleToAllNodes();
                }
            });
        }

        // Submit Form Node
        const btnSubmit = document.getElementById('btnSubmitNodeForm');
        if (btnSubmit) {
            btnSubmit.addEventListener('click', function(e) {
                e.preventDefault();
                submitNodeForm();
            });
        }

        // Tombol Konfirmasi Hapus Node
        const btnConfirmDel = document.getElementById('btnConfirmDeleteNode');
        if (btnConfirmDel) {
            btnConfirmDel.addEventListener('click', function(e) {
                e.preventDefault();
                submitDeleteNode();
            });
        }

        // Tombol Simpan Pengaturan Bagan Global
        const btnSaveChart = document.getElementById('btnSaveChartSettings');
        if (btnSaveChart) {
            btnSaveChart.addEventListener('click', function(e) {
                e.preventDefault();
                submitChartSettings();
            });
        }
    }

    // =========================================================================
    // SERVER AJAX INTERACTIONS (API_STRUKTUR.PHP)
    // =========================================================================

    function submitNodeForm() {
        const formEl = document.getElementById('formNode');
        const formData = new FormData(formEl);

        formData.append('action', 'save_node');
        formData.append('struktur_id', config.strukturId);
        formData.append('csrf_token', config.csrfToken);

        const sumberNama = document.getElementById('sumberNamaSiswa').checked ? 'siswa' : 'manual';
        formData.set('sumber_nama', sumberNama);

        if (sumberNama === 'siswa') {
            const selSiswa = document.getElementById('nodeSiswaSelect');
            const val = selSiswa.value;
            if (!val) {
                alert('Silakan pilih salah satu siswa dari daftar.');
                return;
            }
            formData.set('siswa_id', val);
        } else {
            const nama = document.getElementById('nodeNamaManual').value.trim();
            if (!nama) {
                alert('Silakan ketik nama anggota / pembina.');
                return;
            }
            formData.set('nama', nama);
            formData.delete('siswa_id');
        }

        // Gaya kustom JSON
        const gaya = collectNodeStyleFromModal();
        formData.set('gaya_json', JSON.stringify(gaya));

        // Submit ke backend
        const btnSubmit = document.getElementById('btnSubmitNodeForm');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';

        fetch('api_struktur.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Node';

            if (!data.ok) {
                alert('Gagal: ' + data.pesan);
                return;
            }

            if (nodeFormModal) nodeFormModal.hide();

            // Perbarui state node lokal
            const savedNode = data.node;
            const existingIdx = config.nodes.findIndex(n => n.id === savedNode.id);

            if (existingIdx >= 0) {
                // Pertahankan koordinat lokal jika ada
                savedNode.pos_x = config.nodes[existingIdx].pos_x;
                savedNode.pos_y = config.nodes[existingIdx].pos_y;
                config.nodes[existingIdx] = savedNode;
            } else {
                config.nodes.push(savedNode);
            }

            renderNodes();
            renderConnections();
            pushHistory(currentEditNodeId ? 'Ubah Node' : 'Tambah Node');
            setDirty(true);
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Node';
            console.error('Error save node:', err);
            alert('Terjadi kesalahan koneksi saat menyimpan node.');
        });
    }

    function openDeleteNodeModal(nodeId) {
        currentDeleteNodeId = nodeId;
        const node = config.nodes.find(n => n.id === nodeId);
        if (!node) return;

        document.getElementById('delNodeNama').textContent = `${node.jabatan} (${node.nama})`;

        // Cek apakah node memiliki bawahan / anak
        const children = config.nodes.filter(n => String(n.parent_id) === String(nodeId));
        const childrenAlert = document.getElementById('delNodeChildrenAlert');
        const childrenCountBadge = document.getElementById('delChildrenCount');

        if (children.length > 0) {
            if (childrenAlert) childrenAlert.style.display = 'block';
            if (childrenCountBadge) childrenCountBadge.textContent = children.length;
        } else {
            if (childrenAlert) childrenAlert.style.display = 'none';
        }

        if (deleteNodeModal) deleteNodeModal.show();
    }

    function submitDeleteNode() {
        if (!currentDeleteNodeId) return;

        const actionChoice = document.querySelector('input[name="delAction"]:checked');
        const deleteAction = actionChoice ? actionChoice.value : 'reparent';

        const formData = new FormData();
        formData.append('action', 'delete_node');
        formData.append('struktur_id', config.strukturId);
        formData.append('node_id', currentDeleteNodeId);
        formData.append('delete_action', deleteAction);
        formData.append('csrf_token', config.csrfToken);

        const btnConfirm = document.getElementById('btnConfirmDeleteNode');
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menghapus...';

        fetch('api_struktur.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus Sekarang';

            if (!data.ok) {
                alert('Gagal: ' + data.pesan);
                return;
            }

            if (deleteNodeModal) deleteNodeModal.hide();

            // Perbarui array node lokal
            if (deleteAction === 'cascade') {
                const toDelete = new Set([String(currentDeleteNodeId)]);
                function markDescendants(pId) {
                    config.nodes.forEach(function(n) {
                        if (String(n.parent_id) === String(pId)) {
                            toDelete.add(String(n.id));
                            markDescendants(n.id);
                        }
                    });
                }
                markDescendants(currentDeleteNodeId);
                config.nodes = config.nodes.filter(n => !toDelete.has(String(n.id)));
            } else {
                // Reparent anak ke atasan node ini
                const deletedNode = config.nodes.find(n => n.id === currentDeleteNodeId);
                const grandParentId = deletedNode ? deletedNode.parent_id : null;
                config.nodes.forEach(function(n) {
                    if (String(n.parent_id) === String(currentDeleteNodeId)) {
                        n.parent_id = grandParentId;
                    }
                });
                config.nodes = config.nodes.filter(n => n.id !== currentDeleteNodeId);
            }

            renderNodes();
            renderConnections();
            pushHistory('Hapus Node');
            setDirty(true);
        })
        .catch(err => {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus Sekarang';
            console.error('Error delete node:', err);
            alert('Terjadi kesalahan saat menghapus node.');
        });
    }

    function applyCurrentModalStyleToAllNodes() {
        const gaya = collectNodeStyleFromModal();

        const formData = new FormData();
        formData.append('action', 'apply_style_all');
        formData.append('struktur_id', config.strukturId);
        formData.append('gaya_json', JSON.stringify(gaya));
        formData.append('csrf_token', config.csrfToken);

        fetch('api_struktur.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                alert('Gagal: ' + data.pesan);
                return;
            }

            // Terapkan ke seluruh node dalam memori
            config.nodes.forEach(function(n) {
                n.gaya_json = JSON.parse(JSON.stringify(gaya));
            });

            renderNodes();
            renderConnections();
            pushHistory('Terapkan Gaya ke Semua Node');
            setDirty(false);
            alert(data.pesan);
        })
        .catch(err => {
            console.error('Error apply style all:', err);
            alert('Terjadi kesalahan saat menerapkan gaya ke seluruh node.');
        });
    }

    function openChartSettingsModal() {
        // Isi input modal sesuai gayaBagan saat ini
        const style = config.gayaBagan || {};
        document.getElementById('chartLineStyle').value = style.lineStyle || 'curve';
        document.getElementById('chartLineWidth').value = style.lineWidth || 2;
        document.getElementById('chartLineWidthBadge').textContent = `${style.lineWidth || 2}px`;
        document.getElementById('chartLineColor').value = style.lineColor && style.lineColor.startsWith('#') ? style.lineColor : '#2563eb';
        document.getElementById('chartGridToggle').checked = (style.grid !== false);

        if (chartSettingsModal) chartSettingsModal.show();
    }

    function submitChartSettings() {
        const lineStyle = document.getElementById('chartLineStyle').value;
        const lineWidth = parseInt(document.getElementById('chartLineWidth').value, 10);
        const lineColor = document.getElementById('chartLineColor').value;
        const grid = document.getElementById('chartGridToggle').checked;

        const newChartStyle = {
            lineStyle: lineStyle,
            lineWidth: lineWidth,
            lineColor: lineColor,
            grid: grid
        };

        const formData = new FormData();
        formData.append('action', 'save_chart_settings');
        formData.append('struktur_id', config.strukturId);
        formData.append('gaya_bagan', JSON.stringify(newChartStyle));
        formData.append('csrf_token', config.csrfToken);

        const btnSave = document.getElementById('btnSaveChartSettings');
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';

        fetch('api_struktur.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Pengaturan';

            if (!data.ok) {
                alert('Gagal: ' + data.pesan);
                return;
            }

            if (chartSettingsModal) chartSettingsModal.hide();

            config.gayaBagan = newChartStyle;
            applyChartSettingsToCanvas(newChartStyle);
            pushHistory('Ubah Pengaturan Bagan');
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Pengaturan';
            console.error('Error save chart settings:', err);
            alert('Terjadi kesalahan saat menyimpan pengaturan bagan.');
        });
    }

    function saveAllChanges(callback) {
        const positions = config.nodes.map(function(n) {
            return {
                id: n.id,
                pos_x: n.pos_x,
                pos_y: n.pos_y
            };
        });

        const formData = new FormData();
        formData.append('action', 'save_canvas');
        formData.append('struktur_id', config.strukturId);
        formData.append('positions', JSON.stringify(positions));
        formData.append('gaya_bagan', JSON.stringify(config.gayaBagan));
        formData.append('csrf_token', config.csrfToken);

        const btnSave = document.getElementById('btnSaveCanvas');
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
        }

        fetch('api_struktur.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Perubahan';
            }

            if (!data.ok) {
                alert('Gagal: ' + data.pesan);
                return;
            }

            setDirty(false);

            // Toast / Notifikasi sukses sederhana
            const notifEl = document.getElementById('saveSuccessAlert');
            if (notifEl) {
                notifEl.style.display = 'block';
                setTimeout(() => { notifEl.style.display = 'none'; }, 3000);
            }

            if (typeof callback === 'function') {
                callback(true);
            }
        })
        .catch(err => {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Perubahan';
            }
            console.error('Error save canvas:', err);
            alert('Terjadi kesalahan koneksi saat menyimpan perubahan.');
            if (typeof callback === 'function') {
                callback(false);
            }
        });
    }

    // Public API
    return {
        init: init,
        zoomIn: function() { zoomAtCenter(1.15); },
        zoomOut: function() { zoomAtCenter(0.85); },
        resetZoom: resetView,
        fitView: fitView,
        autoLayout: autoLayoutTree,
        saveCanvas: saveAllChanges,
        openAddNode: openAddNodeModal,
        openEditNode: openEditNodeModal,
        openDeleteNode: openDeleteNodeModal,
        openChartSettings: openChartSettingsModal,
        createNodeElement: createNodeElement,
        getNodes: function() { return config.nodes; },
        renderConnections: renderConnections
    };
})();
