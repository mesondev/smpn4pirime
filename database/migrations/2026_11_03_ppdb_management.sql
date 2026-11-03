-- Riwayat perubahan status permohonan PPDB yang dikelola dari dashboard.
ALTER TABLE ppdb_pendaftar
    MODIFY status ENUM('Diterima','Dalam verifikasi','Perlu perbaikan','Tidak diterima','Dibatalkan')
    NOT NULL DEFAULT 'Dalam verifikasi';

CREATE TABLE IF NOT EXISTS ppdb_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pendaftar_id BIGINT UNSIGNED NOT NULL,
    status_sebelumnya VARCHAR(40) NULL,
    status_baru VARCHAR(40) NOT NULL,
    catatan VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ppdb_history_pendaftar
        FOREIGN KEY (pendaftar_id) REFERENCES ppdb_pendaftar(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ppdb_history_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_ppdb_history_record_date (pendaftar_id, dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catat status saat ini sebagai titik awal audit bagi permohonan yang sudah ada.
INSERT INTO ppdb_status_history (pendaftar_id, status_sebelumnya, status_baru, catatan)
SELECT p.id, NULL, p.status, 'Status awal saat riwayat PPDB diaktifkan.'
FROM ppdb_pendaftar p
WHERE NOT EXISTS (
    SELECT 1 FROM ppdb_status_history h WHERE h.pendaftar_id = p.id
);
