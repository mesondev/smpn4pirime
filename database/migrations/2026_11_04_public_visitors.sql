-- Catatan agregat jumlah pengunjung portal publik per hari.
-- Tidak menyimpan alamat IP, nama, atau pengenal browser.
USE sims_db;

CREATE TABLE IF NOT EXISTS portal_pengunjung_harian (
    tanggal DATE NOT NULL PRIMARY KEY,
    pengunjung INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
