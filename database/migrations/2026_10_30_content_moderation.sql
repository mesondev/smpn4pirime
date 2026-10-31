-- Alur persetujuan konten portal. Aditif; status Terbit yang sudah ada
-- ditandai Disetujui agar publikasi lama tetap tampil.
USE sims_db;

CREATE TABLE IF NOT EXISTS konten_review_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entitas VARCHAR(40) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    status_awal VARCHAR(40) NULL,
    status_akhir VARCHAR(40) NOT NULL,
    catatan VARCHAR(500) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_review_record (entitas, record_id, created_at),
    KEY idx_review_user (user_id, created_at)
) ENGINE=InnoDB;

SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pengumuman' AND column_name='review_status')=0, 'ALTER TABLE pengumuman ADD COLUMN review_status ENUM(''Draft'',''Menunggu Persetujuan'',''Disetujui'',''Perlu Revisi'',''Ditolak'') NOT NULL DEFAULT ''Draft''', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='berita_sekolah' AND column_name='review_status')=0, 'ALTER TABLE berita_sekolah ADD COLUMN review_status ENUM(''Draft'',''Menunggu Persetujuan'',''Disetujui'',''Perlu Revisi'',''Ditolak'') NOT NULL DEFAULT ''Draft''', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='galeri_sekolah' AND column_name='review_status')=0, 'ALTER TABLE galeri_sekolah ADD COLUMN review_status ENUM(''Draft'',''Menunggu Persetujuan'',''Disetujui'',''Perlu Revisi'',''Ditolak'') NOT NULL DEFAULT ''Draft''', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;

SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pengumuman' AND column_name='submitted_at')=0, 'ALTER TABLE pengumuman ADD COLUMN submitted_at DATETIME NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='berita_sekolah' AND column_name='submitted_at')=0, 'ALTER TABLE berita_sekolah ADD COLUMN submitted_at DATETIME NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='galeri_sekolah' AND column_name='submitted_at')=0, 'ALTER TABLE galeri_sekolah ADD COLUMN submitted_at DATETIME NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;

SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pengumuman' AND column_name='reviewed_at')=0, 'ALTER TABLE pengumuman ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN reviewed_by INT UNSIGNED NULL, ADD COLUMN review_note VARCHAR(500) NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='berita_sekolah' AND column_name='reviewed_at')=0, 'ALTER TABLE berita_sekolah ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN reviewed_by INT UNSIGNED NULL, ADD COLUMN review_note VARCHAR(500) NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='galeri_sekolah' AND column_name='reviewed_at')=0, 'ALTER TABLE galeri_sekolah ADD COLUMN reviewed_at DATETIME NULL, ADD COLUMN reviewed_by INT UNSIGNED NULL, ADD COLUMN review_note VARCHAR(500) NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;

UPDATE pengumuman SET review_status='Disetujui' WHERE status='Terbit' AND review_status='Draft';
UPDATE berita_sekolah SET review_status='Disetujui' WHERE status='Terbit' AND review_status='Draft';
UPDATE galeri_sekolah SET review_status='Disetujui' WHERE status='Terbit' AND review_status='Draft';
