-- Persetujuan publikasi agenda kalender akademik.
USE sims_db;

SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='created_by')=0, 'ALTER TABLE kalender_akademik ADD COLUMN created_by INT UNSIGNED NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='review_status')=0, 'ALTER TABLE kalender_akademik ADD COLUMN review_status ENUM(''Draft'',''Menunggu Persetujuan'',''Disetujui'',''Perlu Revisi'',''Ditolak'') NOT NULL DEFAULT ''Draft''', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='submitted_at')=0, 'ALTER TABLE kalender_akademik ADD COLUMN submitted_at DATETIME NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='reviewed_at')=0, 'ALTER TABLE kalender_akademik ADD COLUMN reviewed_at DATETIME NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='reviewed_by')=0, 'ALTER TABLE kalender_akademik ADD COLUMN reviewed_by INT UNSIGNED NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;
SET @review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='review_note')=0, 'ALTER TABLE kalender_akademik ADD COLUMN review_note VARCHAR(500) NULL', 'SELECT 1');
PREPARE review_stmt FROM @review_ddl; EXECUTE review_stmt; DEALLOCATE PREPARE review_stmt;

UPDATE kalender_akademik SET review_status='Disetujui' WHERE tampil_publik=1 AND review_status='Draft';
