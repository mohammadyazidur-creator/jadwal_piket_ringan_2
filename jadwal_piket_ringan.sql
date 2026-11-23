-- Database: jadwal_piket_ringan
CREATE DATABASE IF NOT EXISTS jadwal_piket_ringan DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jadwal_piket_ringan;

-- Hapus tabel lama jika ada (urutan drop memperhatikan foreign key)
DROP TABLE IF EXISTS struktur_node;
DROP TABLE IF EXISTS struktur;
DROP TABLE IF EXISTS kelompok_siswa;
DROP TABLE IF EXISTS kelompok;
DROP TABLE IF EXISTS siswa;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS jadwal_piket;

-- 1. Tabel kelas
CREATE TABLE IF NOT EXISTS kelas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel siswa
CREATE TABLE IF NOT EXISTS siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  nis VARCHAR(50),
  kelas_id INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel kelompok
CREATE TABLE IF NOT EXISTS kelompok (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  deskripsi TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel kelompok_siswa (Relasi many-to-many kelompok dan siswa)
CREATE TABLE IF NOT EXISTS kelompok_siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kelompok_id INT NOT NULL,
  siswa_id INT NOT NULL,
  UNIQUE KEY unique_kelompok_siswa (kelompok_id, siswa_id),
  CONSTRAINT fk_kelompok_siswa_kelompok FOREIGN KEY (kelompok_id) REFERENCES kelompok (id) ON DELETE CASCADE,
  CONSTRAINT fk_kelompok_siswa_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel jadwal_piket
CREATE TABLE IF NOT EXISTS jadwal_piket (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hari VARCHAR(50) NOT NULL,
  siswa_list TEXT NULL,
  nama_siswa1 VARCHAR(255) NULL,
  nama_siswa2 VARCHAR(255) NULL,
  nama_siswa3 VARCHAR(255) NULL,
  nama_siswa4 VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel struktur (Master bagan struktur organisasi)
CREATE TABLE IF NOT EXISTS struktur (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255) NOT NULL,
  kelas_id INT NULL DEFAULT NULL,
  gaya_json TEXT NULL DEFAULT NULL,
  dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_struktur_kelas (kelas_id),
  CONSTRAINT fk_struktur_kelas FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel struktur_node (Kotak jabatan / node hierarki dalam bagan)
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
-- DATA CONTOH (SAMPLE DATA)
-- ========================================================

-- Contoh data kelas
INSERT INTO kelas (id, nama) VALUES 
(1, 'VII A'),
(2, 'VII B'),
(3, 'VIII A'),
(4, 'VIII B'),
(5, 'IX A');

-- Contoh data siswa
INSERT INTO siswa (id, nama, nis, kelas_id) VALUES 
(1, 'Ahmad Rizki', '1001', 1),
(2, 'Siti Nurhaliza', '1002', 1),
(3, 'Budi Santoso', '1003', 2),
(4, 'Dewi Lestari', '1004', 2),
(5, 'Eko Prasetyo', '1005', 3),
(6, 'Fani Rahmawati', '1006', 3),
(7, 'Gilang Ramadhan', '1007', 4),
(8, 'Hana Maulida', '1008', 5);

-- Contoh data kelompok
INSERT INTO kelompok (id, nama, deskripsi) VALUES 
(1, 'Kelompok Melati', 'Kelompok piket pembersih area dalam kelas dan papan tulis'),
(2, 'Kelompok Mawar', 'Kelompok piket pembersih teras, koridor, dan kaca jendela'),
(3, 'Kelompok Anggrek', 'Kelompok piket penataan meja kursi dan pembuangan tempat sampah');

-- Contoh data relasi kelompok_siswa
INSERT INTO kelompok_siswa (kelompok_id, siswa_id) VALUES 
(1, 1),
(1, 2),
(1, 3),
(2, 4),
(2, 5),
(2, 6),
(3, 7),
(3, 8);

-- Contoh data jadwal_piket
INSERT INTO jadwal_piket (hari, siswa_list, nama_siswa1, nama_siswa2, nama_siswa3, nama_siswa4) VALUES
('Senin', '["Ahmad Rizki","Siti Nurhaliza","Budi Santoso"]', 'Ahmad Rizki', 'Siti Nurhaliza', 'Budi Santoso', ''),
('Selasa', '["Dewi Lestari","Eko Prasetyo","Fani Rahmawati"]', 'Dewi Lestari', 'Eko Prasetyo', 'Fani Rahmawati', ''),
('Rabu', '["Gilang Ramadhan","Hana Maulida","Ahmad Rizki"]', 'Gilang Ramadhan', 'Hana Maulida', 'Ahmad Rizki', ''),
('Kamis', '["Budi Santoso","Dewi Lestari","Eko Prasetyo"]', 'Budi Santoso', 'Dewi Lestari', 'Eko Prasetyo', ''),
('Jumat', '["Siti Nurhaliza","Fani Rahmawati","Hana Maulida"]', 'Siti Nurhaliza', 'Fani Rahmawati', 'Hana Maulida', '');

-- Contoh data struktur (Struktur Organisasi Kelas VII A)
INSERT INTO struktur (id, judul, kelas_id, dibuat_pada) VALUES 
(1, 'Struktur Organisasi Kelas VII A', 1, NOW());

-- Contoh data struktur_node berjenjang
INSERT INTO struktur_node (id, struktur_id, parent_id, jabatan, nama, siswa_id, foto, warna, pos_x, pos_y, urutan) VALUES 
(1, 1, NULL, 'Wali Kelas', 'Dra. Hj. Siti Rahmah, M.Pd.', NULL, NULL, 'slate', 460, 50, 1),
(2, 1, 1, 'Ketua Kelas', 'Ahmad Rizki', 1, NULL, 'blue', 460, 190, 2),
(3, 1, 2, 'Wakil Ketua Kelas', 'Budi Santoso', 3, NULL, 'blue', 460, 330, 3),
(4, 1, 3, 'Sekretaris', 'Siti Nurhaliza', 2, NULL, 'emerald', 180, 470, 4),
(5, 1, 3, 'Bendahara', 'Dewi Lestari', 4, NULL, 'amber', 460, 470, 5),
(6, 1, 3, 'Koordinator Piket', 'Eko Prasetyo', 5, NULL, 'purple', 740, 470, 6);
