-- Audit biodata dan reset password tanpa menyimpan kata sandi.
USE sims_db;

CREATE TABLE IF NOT EXISTS biodata_review_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NULL,
    status ENUM('belum_lengkap','menunggu_verifikasi','terverifikasi','perlu_perbaikan','ditolak') NOT NULL,
    catatan VARCHAR(500) NULL,
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_biodata_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_biodata_history_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_biodata_history_user (user_id,dibuat_pada),
    KEY idx_biodata_history_status (status,dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @biodata_status_ddl = IF(
    COALESCE((SELECT LOCATE('ditolak', column_type) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='biodata_pengguna' AND column_name='status_verifikasi'),0)=0,
    "ALTER TABLE biodata_pengguna MODIFY status_verifikasi ENUM('belum_lengkap','menunggu_verifikasi','terverifikasi','perlu_perbaikan','ditolak') NOT NULL DEFAULT 'belum_lengkap'",
    'SELECT 1'
);
PREPARE biodata_status_stmt FROM @biodata_status_ddl; EXECUTE biodata_status_stmt; DEALLOCATE PREPARE biodata_status_stmt;

CREATE TABLE IF NOT EXISTS password_reset_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    target_user_id INT UNSIGNED NOT NULL,
    changed_by INT UNSIGNED NULL,
    target_role VARCHAR(32) NOT NULL,
    reason VARCHAR(255) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_password_reset_actor FOREIGN KEY (changed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_password_reset_target (target_user_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
