-- Jejak perubahan yang terstruktur; nilai pribadi/rahasia difilter di lapisan aplikasi.
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    action_name VARCHAR(80) NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_audit_logs_created (created_at),
    KEY idx_audit_logs_module_record (module_key,record_id),
    KEY idx_audit_logs_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
