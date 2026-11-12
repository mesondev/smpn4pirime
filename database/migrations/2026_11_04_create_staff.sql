-- Data tenaga kependidikan non-guru; tabel guru dan relasi yang ada tidak diubah.
CREATE TABLE IF NOT EXISTS staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(50) NULL UNIQUE,
    nama VARCHAR(150) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NULL,
    jabatan VARCHAR(100) NOT NULL,
    unit_kerja VARCHAR(100) NULL,
    no_hp VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    alamat TEXT NULL,
    status ENUM('aktif', 'nonaktif', 'pensiun') NOT NULL DEFAULT 'aktif',
    tanggal_masuk DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_nama (nama),
    INDEX idx_staff_jabatan (jabatan),
    INDEX idx_staff_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
