-- Fondasi portal publik. Perubahan bersifat aditif dan aman dijalankan ulang.
USE sims_db;

CREATE TABLE IF NOT EXISTS berita_sekolah (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 judul VARCHAR(180) NOT NULL,
 ringkasan VARCHAR(500) NULL,
 isi MEDIUMTEXT NOT NULL,
 kategori VARCHAR(80) NOT NULL DEFAULT 'Kegiatan',
 gambar_url VARCHAR(500) NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 published_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_berita_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 KEY idx_berita_publikasi (status,published_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS galeri_sekolah (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 judul VARCHAR(180) NOT NULL,
 keterangan VARCHAR(500) NULL,
 gambar_url VARCHAR(500) NOT NULL,
 tanggal_kegiatan DATE NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_galeri_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 KEY idx_galeri_publikasi (status,tanggal_kegiatan)
) ENGINE=InnoDB;

SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='tampil_publik')=0, 'ALTER TABLE kalender_akademik ADD COLUMN tampil_publik TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;

SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='sejarah')=0, 'ALTER TABLE profil_sekolah ADD COLUMN sejarah TEXT NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='visi')=0, 'ALTER TABLE profil_sekolah ADD COLUMN visi TEXT NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='misi')=0, 'ALTER TABLE profil_sekolah ADD COLUMN misi TEXT NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='sambutan')=0, 'ALTER TABLE profil_sekolah ADD COLUMN sambutan TEXT NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='jam_layanan')=0, 'ALTER TABLE profil_sekolah ADD COLUMN jam_layanan VARCHAR(180) NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
SET @portal_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='peta_url')=0, 'ALTER TABLE profil_sekolah ADD COLUMN peta_url VARCHAR(500) NULL', 'SELECT 1');
PREPARE portal_stmt FROM @portal_ddl; EXECUTE portal_stmt; DEALLOCATE PREPARE portal_stmt;
