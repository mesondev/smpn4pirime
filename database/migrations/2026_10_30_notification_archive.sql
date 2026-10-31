-- Status arsip notifikasi per pengguna. Aman dijalankan ulang.
USE sims_db;

SET @notice_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notifikasi' AND column_name='archived_at')=0, 'ALTER TABLE notifikasi ADD COLUMN archived_at DATETIME NULL AFTER dibaca', 'SELECT 1');
PREPARE notice_stmt FROM @notice_ddl; EXECUTE notice_stmt; DEALLOCATE PREPARE notice_stmt;
SET @notice_ddl = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notifikasi' AND index_name='idx_notifikasi_archived')=0, 'ALTER TABLE notifikasi ADD KEY idx_notifikasi_archived (archived_at,created_at)', 'SELECT 1');
PREPARE notice_stmt FROM @notice_ddl; EXECUTE notice_stmt; DEALLOCATE PREPARE notice_stmt;
