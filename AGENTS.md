# AGENTS.md - Aturan Wajib untuk AI (Proyek Jadwal Piket Ringan)

Baca file ini SEBELUM mengubah apa pun. Aturan ini mengalahkan kebiasaan
atau preferensi gaya AI. Jika permintaan bertentangan dengan aturan ini,
berhenti dan tanyakan dulu.

## 1. Stack (tidak boleh diganti)
- PHP native + MySQL (mysqli lewat `koneksi.php`), dijalankan di XAMPP.
- Tanpa framework (bukan Laravel). Jangan menambah framework.
- Library baru (JS/PHP) hanya boleh dipasang setelah dijelaskan dan disetujui
  pengguna. Utamakan kode sendiri yang ringan dan tanpa dependensi.
- Folder `vendor/` dan `uploads/` jangan diedit manual.

## 2. Desain (dilarang keluar dari desain yang ada)
Sumber kebenaran desain: `style.css`, `header.php`, `footer.php`,
dan halaman `dashboard.php`. Baca ketiganya sebelum membuat halaman baru.

Ciri desain yang HARUS dipertahankan:
- Navbar biru (warna utama `#2563eb`), brand "Jadwal Piket", menu dengan ikon.
- Latar halaman terang (`#f8fafc` / gradien biru muda), footer biru tua.
- Kartu putih, sudut membulat, bayangan halus, garis biru di sisi atas.
- Ikon Font Awesome dalam kotak membulat berlatar biru muda.
- Judul halaman di tengah dengan garis bawah biru pendek.
- Font dan ukuran mengikuti `style.css`.

Aturan penerapan:
1. Semua warna, radius, bayangan, dan jarak diambil dari `style.css`.
   Jika belum berupa variabel CSS (`:root { --primary-blue: ... }`),
   pakai variabel yang sudah ada; jangan menulis hex baru di tempat lain.
2. Gaya baru ditulis di `style.css` (atau file CSS khusus fitur yang
   di-include dari header) memakai kelas yang bisa dipakai ulang.
   DILARANG `style="..."` inline dan blok `<style>` di halaman, kecuali
   nilai dinamis yang tidak mungkin lewat kelas (misalnya posisi x/y).
3. Pakai ulang kelas yang sudah ada (`.card`, `.btn`, tabel, form, modal)
   sebelum membuat kelas baru. Nama kelas baru berawalan fitur,
   contoh `.struktur-node`.
4. Dilarang mengubah tampilan halaman yang tidak diminta.
5. Dilarang menambah tema gelap, gradien baru, font baru, atau
   gaya "modern" ala AI yang tidak ada di desain.
6. Semua halaman wajib responsif (nyaman di HP).

## 3. Kode harus konsisten dan reusable
1. Setiap halaman memakai `header.php` di atas dan `footer.php` di bawah.
   Jangan menulis ulang navbar/footer.
2. Koneksi database hanya lewat `koneksi.php` (satu sumber).
3. Logika yang dipakai lebih dari satu tempat dipindah ke `lib/`
   (contoh `lib/fungsi.php`, `lib/<fitur>_functions.php`).
   Dilarang copy-paste blok kode yang sama ke beberapa file.
4. Bagian tampilan berulang (kartu, tabel, modal, form, badge) dibuat
   sebagai fungsi/partial (contoh `lib/ui.php`: `render_card()`,
   `render_modal()`), lalu dipakai ulang.
5. Pisahkan: query/logika di atas file, HTML di bawah, JS di
   `assets/js/<fitur>.js`. Jangan campur banyak SQL di tengah HTML.
6. Penamaan: nama file huruf kecil dengan underscore, bahasa Indonesia
   sesuai proyek (`siswa.php`, `kelas.php`). Fungsi `snake_case`.
7. Komentar singkat berbahasa Indonesia pada bagian penting.
8. Jika file sudah ada yang fungsinya mirip, perluas file itu;
   jangan buat versi kedua.

## 4. Keamanan (wajib di semua kode baru)
- Semua query pakai prepared statement. Jangan menyambung variabel
  ke string SQL.
- Semua output ke HTML lewat `htmlspecialchars()` (buat helper `e()`).
- Semua aksi mengubah data (tambah/ubah/hapus) memakai POST + token CSRF.
  Hapus lewat GET dilarang.
- Upload file: batasi tipe dan ukuran, ganti nama file, simpan di `uploads/`.
- Endpoint JSON: validasi input, kembalikan `{ "ok": true/false, "pesan": "" }`.

## 5. Database
- Perubahan tabel HARUS ikut diperbarui di `jadwal_piket_ringan.sql`
  (struktur + data contoh) supaya SQL dan kode selalu sinkron.
  Kode yang memakai tabel yang tidak ada di SQL = bug.
- Tambah tabel dengan `CREATE TABLE IF NOT EXISTS`, beri foreign key
  dan index yang perlu.
- Jangan menghapus atau mengubah nama kolom yang sudah dipakai
  tanpa persetujuan.

## 6. Cara kerja
1. Mulai dengan RENCANA singkat: file yang akan dibuat/diubah, tabel baru,
   dan pilihan library jika ada. Tunggu persetujuan untuk fitur besar.
2. Kerjakan bertahap (kecil). Setelah tiap tahap, laporkan:
   file yang dibuat/diubah, perintah yang harus dijalankan, dan cara
   mengujinya di `http://localhost/jadwal_piket_ringan_2/`.
3. Sentuh hanya file yang perlu. Jangan merapikan/merombak file lain.
4. Jangan menghapus file lama; pindahkan ke `backup_lama/` bila perlu.
5. Jika ada yang ambigu, tanya. Jangan menebak.

## 7. Checklist sebelum menyatakan selesai
- [ ] Tampilan sama gaya dengan halaman lain (navbar, kartu, warna, ikon)
- [ ] Tidak ada `style=""` inline atau warna hex baru di luar `style.css`
- [ ] Tidak ada kode duplikat; logika berulang ada di `lib/`
- [ ] Semua query prepared, output di-escape, aksi POST + CSRF
- [ ] `jadwal_piket_ringan.sql` sudah diperbarui dan bisa diimpor bersih
- [ ] Halaman diuji tanpa error PHP, dan tampil baik di lebar HP
- [ ] Menu di `header.php` diperbarui bila ada halaman baru

9. Navbar dan footer hanya ada di header.php dan footer.php.
10. Dilarang menulis ulang seluruh file yang sudah ada. Ubah dengan edit
    kecil dan laporkan jika sebuah file harus dibuat ulang. Dilarang
    merombak file lewat skrip regex tanpa persetujuan.
11. Warna/font yang dipilih pengguna dan disimpan di database adalah
    data, bukan hex di kode. Default tetap dari CSS variable.
12. Satu komponen UI (kartu, modal, tabel) = satu kelas di style.css.
    Dilarang membuat varian kartu baru di halaman.

    14. Pengecualian landing page: index.php boleh memiliki tampilan sendiri
    (navbar mini sendiri, animasi, 3D ringan). Semua halaman lain
    (dashboard, siswa, kelas, jadwal, struktur) TIDAK boleh berubah.
    Gaya landing berada di assets/css/landing.css (jangan menyentuh
    style.css), memakai variabel warna biru yang sudah ada. Tanpa library
    kecuali disetujui. Hormati prefers-reduced-motion dan sediakan
    cadangan statis bila animasi tidak didukung