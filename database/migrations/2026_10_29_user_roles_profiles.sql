-- Fondasi peran siswa, tautan profil, dan pasfoto akun. Idempoten dan aditif.
USE sims_db;

ALTER TABLE users MODIFY role ENUM('admin','kepala_sekolah','guru','operator','siswa') NOT NULL DEFAULT 'admin';

SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='guru_id')=0, 'ALTER TABLE users ADD COLUMN guru_id INT UNSIGNED NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='siswa_id')=0, 'ALTER TABLE users ADD COLUMN siswa_id INT UNSIGNED NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='foto_path')=0, 'ALTER TABLE users ADD COLUMN foto_path VARCHAR(255) NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='password_must_change')=0, 'ALTER TABLE users ADD COLUMN password_must_change TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='last_login_at')=0, 'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;

SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='uq_users_guru')=0, 'ALTER TABLE users ADD UNIQUE KEY uq_users_guru (guru_id)', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='uq_users_siswa')=0, 'ALTER TABLE users ADD UNIQUE KEY uq_users_siswa (siswa_id)', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='users' AND constraint_name='fk_users_guru')=0, 'ALTER TABLE users ADD CONSTRAINT fk_users_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
SET @user_ddl = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='users' AND constraint_name='fk_users_siswa')=0, 'ALTER TABLE users ADD CONSTRAINT fk_users_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE user_stmt FROM @user_ddl; EXECUTE user_stmt; DEALLOCATE PREPARE user_stmt;
