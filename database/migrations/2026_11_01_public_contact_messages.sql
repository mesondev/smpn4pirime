-- Percakapan dari formulir kontak publik dan balasan Super Admin.
CREATE TABLE IF NOT EXISTS pesan_kontak (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_pengirim VARCHAR(120) NOT NULL,
    email_pengirim VARCHAR(180) NOT NULL,
    telepon_pengirim VARCHAR(30) NULL,
    kategori VARCHAR(60) NOT NULL,
    subjek VARCHAR(180) NOT NULL,
    status ENUM('baru','dibalas','ditutup') NOT NULL DEFAULT 'baru',
    dibaca_admin TINYINT(1) NOT NULL DEFAULT 0,
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pesan_kontak_status (status,diperbarui_pada),
    KEY idx_pesan_kontak_unread (dibaca_admin,diperbarui_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pesan_kontak_balasan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pesan_id BIGINT UNSIGNED NOT NULL,
    pengirim ENUM('pengunjung','admin') NOT NULL,
    admin_user_id INT UNSIGNED NULL,
    isi MEDIUMTEXT NOT NULL,
    status_email ENUM('tidak_berlaku','menunggu','terkirim','gagal') NOT NULL DEFAULT 'tidak_berlaku',
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pesan_balasan_thread (pesan_id,dibuat_pada),
    CONSTRAINT fk_pesan_balasan_thread FOREIGN KEY (pesan_id) REFERENCES pesan_kontak(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pesan_balasan_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
