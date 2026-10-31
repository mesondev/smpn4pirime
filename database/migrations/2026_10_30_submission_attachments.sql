-- Lampiran privat untuk pengumpulan tugas siswa.
USE sims_db;

SET @submission_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pengumpulan_tugas' AND column_name='file_path')=0, 'ALTER TABLE pengumpulan_tugas ADD COLUMN file_path VARCHAR(255) NULL AFTER jawaban', 'SELECT 1');
PREPARE submission_stmt FROM @submission_ddl; EXECUTE submission_stmt; DEALLOCATE PREPARE submission_stmt;
SET @submission_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pengumpulan_tugas' AND column_name='file_name')=0, 'ALTER TABLE pengumpulan_tugas ADD COLUMN file_name VARCHAR(180) NULL AFTER file_path', 'SELECT 1');
PREPARE submission_stmt FROM @submission_ddl; EXECUTE submission_stmt; DEALLOCATE PREPARE submission_stmt;
