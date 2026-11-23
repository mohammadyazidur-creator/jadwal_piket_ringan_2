-- ========================================================
-- MIGRASI FITUR STRUKTUR ORGANISASI
-- File: migrasi_struktur.sql
-- Keterangan: Hanya membuat tabel baru dan menambahkan data contoh
--             tanpa menghapus data yang sudah ada di database.
-- ========================================================

-- Pastikan database aktif
USE jadwal_piket_ringan;

-- 1. Tabel master bagan struktur organisasi
CREATE TABLE IF NOT EXISTS struktur (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255) NOT NULL,
  kelas_id INT NULL DEFAULT NULL,
  gaya_json TEXT NULL DEFAULT NULL,
  dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_struktur_kelas (kelas_id),
  CONSTRAINT fk_struktur_kelas FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel kotak jabatan / node hierarki dalam bagan
CREATE TABLE IF NOT EXISTS struktur_node (
  id INT AUTO_INCREMENT PRIMARY KEY,
  struktur_id INT NOT NULL,
  parent_id INT NULL DEFAULT NULL,
  jabatan VARCHAR(100) NOT NULL,
  nama VARCHAR(255) NOT NULL,
  keterangan VARCHAR(255) NULL DEFAULT NULL,
  siswa_id INT NULL DEFAULT NULL,
  foto VARCHAR(255) NULL DEFAULT NULL,
  warna VARCHAR(50) DEFAULT 'blue',
  gaya_json TEXT NULL DEFAULT NULL,
  pos_x INT DEFAULT 100,
  pos_y INT DEFAULT 100,
  urutan INT DEFAULT 0,
  INDEX idx_node_struktur (struktur_id),
  INDEX idx_node_parent (parent_id),
  INDEX idx_node_siswa (siswa_id),
  CONSTRAINT fk_node_struktur FOREIGN KEY (struktur_id) REFERENCES struktur (id) ON DELETE CASCADE,
  CONSTRAINT fk_node_parent FOREIGN KEY (parent_id) REFERENCES struktur_node (id) ON DELETE SET NULL,
  CONSTRAINT fk_node_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- DATA CONTOH AWAL (Opsi 1: Struktur Organisasi Kelas VII A)
-- Hanya ditambahkan jika tabel struktur masih kosong
-- ========================================================

INSERT INTO struktur (id, judul, kelas_id, dibuat_pada)
SELECT 1, 'Struktur Organisasi Kelas VII A', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM struktur WHERE id = 1);

-- Data contoh node berjenjang
-- Level 1: Wali Kelas (Root)
INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 1, 1, NULL, 'Wali Kelas', 'Dra. Hj. Siti Rahmah, M.Pd.', NULL, NULL, 'slate', 460, 50, 1
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 1);

-- Level 2: Ketua Kelas
INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 2, 1, 1, 'Ketua Kelas', 'Ahmad Rizki', 1, NULL, 'blue', 460, 190, 2
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 2);

-- Level 3: Wakil Ketua Kelas
INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 3, 1, 2, 'Wakil Ketua Kelas', 'Budi Santoso', 3, NULL, 'blue', 460, 330, 3
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 3);

-- Level 4: Sekretaris, Bendahara, Koordinator Piket
INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 4, 1, 3, 'Sekretaris', 'Siti Nurhaliza', 2, NULL, 'emerald', 180, 470, 4
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 4);

INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 5, 1, 3, 'Bendahara', 'Dewi Lestari', 4, NULL, 'amber', 460, 470, 5
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 5);

INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan)
SELECT 6, 1, 3, 'Koordinator Piket', 'Eko Prasetyo', 5, NULL, 'purple', 740, 470, 6
WHERE NOT EXISTS (SELECT 1 FROM struktur_node WHERE id = 6);
