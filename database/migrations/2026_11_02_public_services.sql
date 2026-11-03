-- Layanan dan informasi tambahan untuk portal publik.
-- Jalankan setelah migrasi 2026_11_01_* pada database sims_db.
USE sims_db;

CREATE TABLE IF NOT EXISTS portal_ekstrakurikuler (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(150) NOT NULL,
 kategori VARCHAR(80) NULL,
 deskripsi TEXT NULL,
 pembina VARCHAR(150) NULL,
 jadwal VARCHAR(150) NULL,
 persyaratan TEXT NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 urutan SMALLINT NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_portal_ekstra_status (status,urutan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_fasilitas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(150) NOT NULL,
 deskripsi TEXT NULL,
 gambar_url VARCHAR(500) NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 urutan SMALLINT NOT NULL DEFAULT 0,
 KEY idx_portal_fasilitas_status (status,urutan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_struktur_sekolah (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(150) NOT NULL,
 jabatan VARCHAR(120) NOT NULL,
 foto_url VARCHAR(500) NULL,
 urutan SMALLINT NOT NULL DEFAULT 0,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 KEY idx_portal_struktur_status (status,urutan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_prestasi_publik (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 judul VARCHAR(180) NOT NULL,
 penerima VARCHAR(160) NOT NULL,
 kategori VARCHAR(80) NULL,
 tingkat VARCHAR(80) NULL,
 peringkat VARCHAR(80) NULL,
 penyelenggara VARCHAR(180) NULL,
 tanggal DATE NULL,
 deskripsi TEXT NULL,
 bukti_terverifikasi TINYINT(1) NOT NULL DEFAULT 0,
 izin_publikasi TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 KEY idx_portal_prestasi_status (status,tanggal)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_dokumen_publik (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 judul VARCHAR(180) NOT NULL,
 kategori VARCHAR(80) NOT NULL DEFAULT 'Umum',
 deskripsi VARCHAR(500) NULL,
 nama_file VARCHAR(255) NOT NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 published_at DATETIME NULL,
 KEY idx_portal_dokumen_status (status,published_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_jadwal_publik (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tahun_ajaran VARCHAR(20) NOT NULL,
 tingkat ENUM('7','8','9') NOT NULL,
 hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
 jam_mulai TIME NOT NULL,
 jam_selesai TIME NOT NULL,
 mata_pelajaran VARCHAR(120) NOT NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 KEY idx_portal_jadwal_status (status,tahun_ajaran,tingkat,hari,jam_mulai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_tautan_sosial (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 platform ENUM('facebook','instagram','youtube','tiktok','twitter-x') NOT NULL,
 url VARCHAR(500) NOT NULL,
 status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'nonaktif',
 UNIQUE KEY uq_portal_social_platform (platform)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ppdb_periode (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tahun_ajaran VARCHAR(20) NOT NULL,
 judul VARCHAR(180) NOT NULL,
 informasi TEXT NULL,
 persyaratan TEXT NULL,
 mulai DATE NOT NULL,
 selesai DATE NOT NULL,
 status ENUM('Draft','Terbit','Ditutup') NOT NULL DEFAULT 'Draft',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_ppdb_periode_aktif (status,mulai,selesai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ppdb_pendaftar (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 periode_id INT UNSIGNED NOT NULL,
 nomor_pendaftaran VARCHAR(24) NOT NULL UNIQUE,
 kunci_status_hash VARCHAR(255) NOT NULL,
 nama_calon VARCHAR(150) NOT NULL,
 nisn VARCHAR(20) NULL,
 nama_wali VARCHAR(150) NOT NULL,
 telepon_wali VARCHAR(30) NOT NULL,
 status ENUM('Diterima','Dalam verifikasi','Perlu perbaikan','Tidak diterima') NOT NULL DEFAULT 'Dalam verifikasi',
 catatan_publik VARCHAR(500) NULL,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ppdb_pendaftar_periode FOREIGN KEY (periode_id) REFERENCES ppdb_periode(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 KEY idx_ppdb_pendaftar_periode (periode_id,dibuat_pada)
) ENGINE=InnoDB;

-- Data anak, NISN, dan nomor telepon tidak disimpan dalam folder publik.
