-- Add per-user notification ownership while preserving existing records.
USE sims_db;

SET @notice_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notifikasi' AND column_name='recipient_user_id')=0, 'ALTER TABLE notifikasi ADD COLUMN recipient_user_id INT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE notice_stmt FROM @notice_ddl; EXECUTE notice_stmt; DEALLOCATE PREPARE notice_stmt;
SET @notice_ddl = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notifikasi' AND index_name='idx_notifikasi_recipient_read')=0, 'ALTER TABLE notifikasi ADD KEY idx_notifikasi_recipient_read (recipient_user_id,dibaca,created_at)', 'SELECT 1');
PREPARE notice_stmt FROM @notice_ddl; EXECUTE notice_stmt; DEALLOCATE PREPARE notice_stmt;
SET @notice_ddl = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='notifikasi' AND constraint_name='fk_notifikasi_recipient')=0, 'ALTER TABLE notifikasi ADD CONSTRAINT fk_notifikasi_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE', 'SELECT 1');
PREPARE notice_stmt FROM @notice_ddl; EXECUTE notice_stmt; DEALLOCATE PREPARE notice_stmt;
