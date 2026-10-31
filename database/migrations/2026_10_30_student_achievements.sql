-- Data prestasi siswa untuk administrasi operator dan pemantauan Kepala Sekolah.
USE sims_db;

CREATE TABLE IF NOT EXISTS prestasi_siswa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NULL,
    tahun_ajaran_id INT UNSIGNED NULL,
    nama_prestasi VARCHAR(180) NOT NULL,
    bidang VARCHAR(100) NULL,
    tingkat ENUM('Sekolah','Kecamatan','Kabupaten','Provinsi','Nasional','Internasional','Lainnya') NOT NULL DEFAULT 'Sekolah',
    penyelenggara VARCHAR(180) NULL,
    tanggal DATE NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prestasi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_prestasi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_prestasi_tanggal (tanggal),
    KEY idx_prestasi_tingkat (tingkat)
) ENGINE=InnoDB;
