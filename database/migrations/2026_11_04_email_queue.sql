-- Antrean pengiriman balasan kontak agar request web tidak menunggu layanan mail.
CREATE TABLE IF NOT EXISTS outbound_email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reply_id BIGINT UNSIGNED NOT NULL UNIQUE,
    recipient VARCHAR(180) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    CONSTRAINT fk_email_queue_reply FOREIGN KEY (reply_id) REFERENCES pesan_kontak_balasan(id) ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_email_queue_due (status,next_attempt_at),
    KEY idx_email_queue_lock (status,locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
