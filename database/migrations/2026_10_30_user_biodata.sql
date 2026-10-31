-- Biodata tambahan akun dan status pemeriksaan tanpa mengganti data induk.
USE sims_db;

CREATE TABLE IF NOT EXISTS biodata_pengguna (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    telepon VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    tempat_lahir VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    alamat TEXT NULL,
    pendidikan VARCHAR(180) NULL,
    nama_kontak_darurat VARCHAR(120) NULL,
    telepon_kontak_darurat VARCHAR(30) NULL,
    status_verifikasi ENUM('belum_lengkap','menunggu_verifikasi','terverifikasi','perlu_perbaikan') NOT NULL DEFAULT 'belum_lengkap',
    catatan_verifikasi VARCHAR(500) NULL,
    diverifikasi_oleh INT UNSIGNED NULL,
    diverifikasi_pada DATETIME NULL,
    diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_biodata_pengguna_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_biodata_pengguna_pemeriksa FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_biodata_status (status_verifikasi,diperbarui_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
