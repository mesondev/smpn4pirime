-- Preferensi tampilan disimpan per akun agar perangkat dan pengguna tidak saling berbagi pengaturan.
CREATE TABLE IF NOT EXISTS preferensi_pengguna (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    tema ENUM('sistem', 'terang', 'gelap') NOT NULL DEFAULT 'sistem',
    ukuran_teks ENUM('kecil', 'normal', 'besar') NOT NULL DEFAULT 'normal',
    kerapatan_tabel ENUM('ringkas', 'normal', 'nyaman') NOT NULL DEFAULT 'normal',
    sidebar_ciut TINYINT(1) NOT NULL DEFAULT 0,
    periode_grafik ENUM('month', '3months', '6months', '12months') NOT NULL DEFAULT 'month',
    diperbarui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_preferensi_pengguna_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
