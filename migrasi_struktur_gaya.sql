-- ========================================================
-- MIGRASI FITUR GAYA STRUKTUR ORGANISASI (TAHAP 3)
-- File: migrasi_struktur_gaya.sql
-- Keterangan: Menambahkan kolom gaya_json dan keterangan.
--             Aman dijalankan berulang kali (idempotent di MariaDB).
-- ========================================================

USE jadwal_piket_ringan;

-- 1. Tambah kolom konfigurasi gaya bagan global (gaya_json) pada tabel struktur
ALTER TABLE struktur 
ADD COLUMN IF NOT EXISTS gaya_json TEXT NULL DEFAULT NULL;

-- 2. Tambah kolom keterangan (mis. NIP/kontak/catatan tambahan) pada tabel struktur_node
ALTER TABLE struktur_node 
ADD COLUMN IF NOT EXISTS keterangan VARCHAR(255) NULL DEFAULT NULL;

-- 3. Tambah kolom konfigurasi gaya kustom per node (gaya_json) pada tabel struktur_node
ALTER TABLE struktur_node 
ADD COLUMN IF NOT EXISTS gaya_json TEXT NULL DEFAULT NULL;
