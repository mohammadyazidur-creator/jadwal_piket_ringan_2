/**
 * assets/js/struktur_render.js - Modul Bersama Renderer Bagan Struktur Organisasi
 * Sesuai AGENTS.md:
 * - Sumber tunggal untuk render kartu node, kalkulasi dimensi, resolusi warna, dan kurva SVG
 * - Dipakai bersama oleh struktur_editor.php (mode interaktif) dan struktur_cetak.php (mode cetak/hanya-lihat)
 * - Tanpa hex baru; mengambil nilai warna dari CSS variables via getComputedStyle
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.StrukturRender = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * Mengambil nilai warna dari CSS variable di style.css
     */
    function getCssColor(varName, fallbackVarName) {
        if (typeof document === 'undefined') return '';
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
        if (!colorVal) {
            return getCssColor(`--node-${defaultColorKey || 'blue'}`, '--primary-blue') || '#2563eb';
        }
        if (colorVal.startsWith('#')) {
            return colorVal;
        }
        return getCssColor(`--node-${colorVal}`, `--${colorVal}`) || getCssColor('--primary-blue') || '#2563eb';
    }

    /**
     * Menghitung lebar kartu node berdasarkan konfigurasinya
     */
    function getNodeWidth(node) {
        let style = parseNodeStyle(node.gaya_json);
        if (style && style.width === 'small') return 180;
        if (style && style.width === 'large') return 280;
        return 230; // Medium / Default
    }

    /**
     * Parse string / objek JSON gaya node dengan aman
     */
    function parseNodeStyle(gaya) {
        if (!gaya) return null;
        if (typeof gaya === 'object') return gaya;
        try {
            return JSON.parse(gaya);
        } catch (e) {
            return null;
        }
    }

    /**
     * Menghitung kotak pembatas (bounding box) seluruh node
     */
    function calculateBoundingBox(nodes, padding) {
        padding = typeof padding === 'number' ? padding : 40;
        if (!nodes || nodes.length === 0) {
            return { minX: 0, minY: 0, maxX: 400, maxY: 300, width: 400, height: 300 };
        }

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        nodes.forEach(function (n) {
            const x = parseFloat(n.pos_x) || 0;
            const y = parseFloat(n.pos_y) || 0;
            const w = getNodeWidth(n);
            const h = 100; // estimasi tinggi rata-rata node

            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x + w > maxX) maxX = x + w;
            if (y + h > maxY) maxY = y + h;
        });

        return {
            minX: minX - padding,
            minY: minY - padding,
            maxX: maxX + padding,
            maxY: maxY + padding,
            width: (maxX - minX) + (padding * 2),
            height: (maxY - minY) + (padding * 2)
        };
    }

    /**
     * Menghasilkan path SVG D antara dua titik koordinat
     */
    function calculateConnectionPath(x1, y1, x2, y2, lineStyle) {
        if (lineStyle === 'straight') {
            return `M ${x1} ${y1} L ${x2} ${y2}`;
        }
        if (lineStyle === 'step') {
            const midY = y1 + ((y2 - y1) / 2);
            return `M ${x1} ${y1} L ${x1} ${midY} L ${x2} ${midY} L ${x2} ${y2}`;
        }
        // Default: Garis Lengkung Halus (Cubic Bezier)
        const deltaY = Math.max(Math.abs(y2 - y1) * 0.5, 30);
        const cp1x = x1;
        const cp1y = y1 + deltaY;
        const cp2x = x2;
        const cp2y = y2 - deltaY;
        return `M ${x1} ${y1} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x2} ${y2}`;
    }

    /**
     * Merender seluruh garis penghubung kurva/siku/lurus ke dalam elemen SVG
     */
    function renderConnections(svgEl, nodes, gayaBagan) {
        if (!svgEl || !nodes) return;
        svgEl.innerHTML = '';

        gayaBagan = gayaBagan || {};
        const lineStyle = gayaBagan.lineStyle || 'curve';
        const lineWidth = gayaBagan.lineWidth || 2;
        const lineColorRaw = gayaBagan.lineColor || 'primary-blue';
        const resolvedLineColor = resolveColor(lineColorRaw, 'primary-blue');

        nodes.forEach(function (node) {
            if (!node.parent_id) {
                return;
            }

            const parentNode = nodes.find(function (n) {
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

            const pathD = calculateConnectionPath(x1, y1, x2, y2, lineStyle);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathD);
            path.setAttribute('class', 'struktur-line');
            path.style.stroke = resolvedLineColor;
            path.style.strokeWidth = `${lineWidth}px`;

            svgEl.appendChild(path);
        });
    }

    /**
     * Membuat elemen DOM kartu node tunggal
     *
     * @param {Object} node Data node
     * @param {Object} options Opsi render:
     *   - isInteractive: true (editor) / false (hanya-lihat/cetak)
     *   - onEdit: callback function(nodeId)
     *   - onDelete: callback function(nodeId)
     *   - onDrag: callback function(nodeEl, node)
     *   - showPhoto: boolean (default true)
     *   - showKeterangan: boolean (default true)
     *   - showSub: boolean (default true)
     * @returns {HTMLElement} Elemen DOM kartu node
     */
    function createNodeElement(node, options) {
        options = Object.assign({
            isInteractive: true,
            showPhoto: true,
            showKeterangan: true,
            showSub: true
        }, options || {});

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
            if (typeof options.onEdit === 'function') {
                btnEdit.addEventListener('click', function (e) {
                    e.stopPropagation();
                    options.onEdit(node.id);
                });
            }

            const btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.className = 'btn-node-action btn-node-delete';
            btnDelete.title = 'Hapus Node Jabatan';
            btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
            if (typeof options.onDelete === 'function') {
                btnDelete.addEventListener('click', function (e) {
                    e.stopPropagation();
                    options.onDelete(node.id);
                });
            }

            actionsEl.appendChild(btnEdit);
            actionsEl.appendChild(btnDelete);
            nodeEl.appendChild(actionsEl);
        } else if (typeof node.pos_x !== 'undefined' && typeof node.pos_y !== 'undefined') {
            // Mode hanya-lihat / cetak dengan posisi absolut
            nodeEl.style.position = 'absolute';
            nodeEl.style.left = `${node.pos_x}px`;
            nodeEl.style.top = `${node.pos_y}px`;
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

        // Avatar foto / ikon (sembunyikan jika showIcon == false atau options.showPhoto == false)
        const showIcon = (!gaya || gaya.showIcon !== false) && (options.showPhoto !== false);
        if (showIcon) {
            const avatarEl = document.createElement('div');
            avatarEl.className = 'struktur-node-avatar';

            if (node.foto && node.foto.trim() !== '') {
                const img = document.createElement('img');
                img.src = `uploads/struktur/${node.foto}`;
                img.alt = node.nama || 'Foto';
                img.onerror = function () {
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
        if (options.showKeterangan !== false && node.keterangan && node.keterangan.trim() !== '') {
            const ketEl = document.createElement('div');
            ketEl.className = 'struktur-node-keterangan';
            ketEl.textContent = node.keterangan;
            ketEl.title = node.keterangan;
            infoEl.appendChild(ketEl);
        }

        // Subtitle (NIS atau status keanggotaan)
        if (options.showSub !== false) {
            const subEl = document.createElement('div');
            subEl.className = 'struktur-node-sub';
            if (node.nis) {
                subEl.textContent = `NIS: ${node.nis}`;
            } else if (node.siswa_id) {
                subEl.textContent = 'Siswa Terdaftar';
            } else if (node.keterangan) {
                subEl.textContent = 'Anggota';
            } else {
                subEl.textContent = 'Anggota Organisasi';
            }
            infoEl.appendChild(subEl);
        }

        bodyEl.appendChild(infoEl);
        nodeEl.appendChild(bodyEl);

        // Pasang event geser jika interaktif
        if (options.isInteractive && typeof options.onDrag === 'function') {
            options.onDrag(nodeEl, node);
        }

        return nodeEl;
    }

    return {
        getCssColor: getCssColor,
        resolveColor: resolveColor,
        getNodeWidth: getNodeWidth,
        parseNodeStyle: parseNodeStyle,
        calculateBoundingBox: calculateBoundingBox,
        calculateConnectionPath: calculateConnectionPath,
        renderConnections: renderConnections,
        createNodeElement: createNodeElement
    };
}));