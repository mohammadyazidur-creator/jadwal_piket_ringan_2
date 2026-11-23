/**
 * assets/js/landing.js - Logika Interaksi Landing Page
 * Fitur:
 * 1. Smooth scroll untuk tautan anchor (#fitur, dll.)
 * 2. 3D Drag to Rotate & Smooth Floating Animation (Pointer Events)
 *    - Mendukung mouse dan sentuhan (touch-action: pan-y)
 *    - Clamp rotasi: rotateY -35 s.d 35 derajat, rotateX 0 s.d 25 derajat
 *    - Pegas halus (spring lerp) kembali ke posisi awal saat dilepas
 *    - Performa tinggi: satu loop rAF, berhenti saat tab tersembunyi (visibilitychange)
 *      atau papan di luar layar (IntersectionObserver)
 *    - Hanya animasikan transform
 *    - Menghormati prefers-reduced-motion
 * 3. Scroll Reveal via IntersectionObserver
 */

document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------
    // 1. SMOOTH SCROLL ANCHOR (Syarat 5: Tombol "Lihat Fitur")
    // ----------------------------------------------------
    const anchorLinks = document.querySelectorAll('.landing-page a[href^="#"]');
    anchorLinks.forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId && targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // ----------------------------------------------------
    // 2. DETEKSI PREFERS-REDUCED-MOTION
    // ----------------------------------------------------
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let prefersReducedMotion = motionQuery.matches;

    // ----------------------------------------------------
    // 3. EFEK 3D: SERET UNTUK MEMUTAR & MELAYANG HALUS
    // ----------------------------------------------------
    const scene = document.getElementById('landingHeroScene');
    const stage = document.getElementById('landingHeroStage');
    const card1 = document.querySelector('.landing-3d-float-card1');
    const card2 = document.querySelector('.landing-3d-float-card2');

    if (scene && stage) {
        // Konfigurasi Sudut Dasar
        const BASE_ROT_X = 12; // derajat
        const BASE_ROT_Y = -15; // derajat
        const BASE_ROT_Z = 2;  // derajat

        // Batas Rotasi Sesuai Syarat:
        // rotateY: -35 s.d 35 derajat
        // rotateX: 0 s.d 25 derajat
        const MIN_ROT_X = 0;
        const MAX_ROT_X = 25;
        const MIN_ROT_Y = -35;
        const MAX_ROT_Y = 35;

        // State Interaksi
        let currentRotX = BASE_ROT_X;
        let currentRotY = BASE_ROT_Y;
        let targetRotX = BASE_ROT_X;
        let targetRotY = BASE_ROT_Y;

        let isDragging = false;
        let isTouch = false;
        let startPointerX = 0;
        let startPointerY = 0;
        let startAngleX = BASE_ROT_X;
        let startAngleY = BASE_ROT_Y;

        // Loop Control & Performance Lifecycle
        let rafId = null;
        let isVisible = true;
        let isTabActive = !document.hidden;

        // POINTER DOWN
        scene.addEventListener('pointerdown', function (e) {
            if (prefersReducedMotion) return;
            isDragging = true;
            isTouch = (e.pointerType === 'touch');
            startPointerX = e.clientX;
            startPointerY = e.clientY;
            startAngleX = currentRotX;
            startAngleY = currentRotY;
            scene.classList.add('landing-dragging');

            try {
                scene.setPointerCapture(e.pointerId);
            } catch (_) {}
        });

        // POINTER MOVE
        scene.addEventListener('pointermove', function (e) {
            if (!isDragging || prefersReducedMotion) return;

            const deltaX = e.clientX - startPointerX;
            const deltaY = e.clientY - startPointerY;

            // Sensitivitas drag
            const sensitivityX = 0.32;
            const sensitivityY = 0.22;

            // Geser horizontal memutar sumbu Y
            let newRotY = startAngleY + deltaX * sensitivityX;
            targetRotY = Math.max(MIN_ROT_Y, Math.min(MAX_ROT_Y, newRotY));

            // Di layar sentuh: hanya geser horizontal yang memutar,
            // scroll vertikal tetap berfungsi normal via touch-action: pan-y
            if (!isTouch) {
                // Pada mouse: geser vertikal memutar sumbu X
                let newRotX = startAngleX - deltaY * sensitivityY;
                targetRotX = Math.max(MIN_ROT_X, Math.min(MAX_ROT_X, newRotX));
            } else {
                targetRotX = BASE_ROT_X;
            }
        });

        // POINTER UP & CANCEL
        function handlePointerEnd(e) {
            if (!isDragging) return;
            isDragging = false;
            scene.classList.remove('landing-dragging');

            try {
                scene.releasePointerCapture(e.pointerId);
            } catch (_) {}

            // Saat dilepas, kembali halus ke posisi dasar
            targetRotX = BASE_ROT_X;
            targetRotY = BASE_ROT_Y;
        }

        scene.addEventListener('pointerup', handlePointerEnd);
        scene.addEventListener('pointercancel', handlePointerEnd);

        // SATU LOOP RAF UNTUK MEMUTAR & MELAYANG
        function renderLoop(time) {
            const sec = time * 0.001;

            // Hitung osilasi melayang pelan saat tidak disentuh
            let floatOffsetY = 0;
            let floatRotX = 0;
            let floatRotY = 0;

            if (!isDragging) {
                // Melayang pelan naik-turun ~7px dan osilasi sudut ~1.2 derajat
                floatOffsetY = Math.sin(sec * 1.4) * 7;
                floatRotX = Math.sin(sec * 1.1) * 1.2;
                floatRotY = Math.cos(sec * 0.9) * 1.2;

                // Terapkan osilasi ke target
                targetRotX = BASE_ROT_X + floatRotX;
                targetRotY = BASE_ROT_Y + floatRotY;
            }

            // Lerp (Linear Interpolation) untuk kehalusan gerak & spring return
            const lerpFactor = isDragging ? 0.2 : 0.08;
            currentRotX += (targetRotX - currentRotX) * lerpFactor;
            currentRotY += (targetRotY - currentRotY) * lerpFactor;

            // Terapkan transform murni (hanya transform, tanpa modifikasi blur atau bayangan)
            stage.style.transform = `rotateX(${currentRotX.toFixed(2)}deg) rotateY(${currentRotY.toFixed(2)}deg) rotateZ(${BASE_ROT_Z}deg) translateY(${floatOffsetY.toFixed(2)}px)`;

            // Kartu melayang mengikuti dengan kedalaman (translateZ) berbeda
            if (card1) {
                const c1Float = Math.sin(sec * 1.8) * 3;
                card1.style.transform = `translateZ(65px) translateY(${c1Float.toFixed(2)}px)`;
            }
            if (card2) {
                const c2Float = Math.cos(sec * 1.6) * 3;
                card2.style.transform = `translateZ(45px) translateY(${c2Float.toFixed(2)}px)`;
            }

            rafId = requestAnimationFrame(renderLoop);
        }

        function startLoop() {
            if (!rafId && isVisible && isTabActive && !prefersReducedMotion) {
                rafId = requestAnimationFrame(renderLoop);
            }
        }

        function stopLoop() {
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
        }

        // IntersectionObserver: Berhenti saat papan di luar layar
        const sceneObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                isVisible = entry.isIntersecting;
                if (isVisible) {
                    startLoop();
                } else {
                    stopLoop();
                }
            });
        }, { threshold: 0.05 });

        sceneObserver.observe(scene);

        // VisibilityChange: Berhenti saat tab browser tidak aktif / tersembunyi
        document.addEventListener('visibilitychange', function () {
            isTabActive = !document.hidden;
            if (isTabActive) {
                startLoop();
            } else {
                stopLoop();
            }
        });

        // Listen perubahan prefers-reduced-motion dinamis
        motionQuery.addEventListener('change', function (e) {
            prefersReducedMotion = e.matches;
            if (prefersReducedMotion) {
                stopLoop();
                stage.style.transform = 'none';
                if (card1) card1.style.transform = 'none';
                if (card2) card2.style.transform = 'none';
            } else {
                startLoop();
            }
        });

        // Jalankan loop jika tidak dalam mode reduced-motion
        if (!prefersReducedMotion) {
            startLoop();
        } else {
            stage.style.transform = 'none';
        }
    }

    // ----------------------------------------------------
    // 4. SCROLL REVEAL (INTERSECTION OBSERVER)
    // ----------------------------------------------------
    const revealElements = document.querySelectorAll('.landing-reveal');
    if ('IntersectionObserver' in window && !prefersReducedMotion) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('landing-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px'
        });

        revealElements.forEach(function (el) {
            observer.observe(el);
        });
    } else {
        revealElements.forEach(function (el) {
            el.classList.add('landing-visible');
        });
    }
});