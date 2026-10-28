-- Modul materi, tugas, dan jawaban tugas. Aditif dan aman dijalankan ulang.
CREATE TABLE IF NOT EXISTS materi_pelajaran (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 guru_id INT UNSIGNED NOT NULL,
 kelas_id INT UNSIGNED NOT NULL,
 mapel_id INT UNSIGNED NOT NULL,
 tahun_ajaran_id INT UNSIGNED NOT NULL,
 judul VARCHAR(180) NOT NULL,
 deskripsi TEXT NULL,
 file_path VARCHAR(255) NULL,
 file_name VARCHAR(180) NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_materi_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_materi_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_materi_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_materi_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 KEY idx_materi_kelas_status (kelas_id,status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tugas_pelajaran (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 guru_id INT UNSIGNED NOT NULL,
 kelas_id INT UNSIGNED NOT NULL,
 mapel_id INT UNSIGNED NOT NULL,
 tahun_ajaran_id INT UNSIGNED NOT NULL,
 judul VARCHAR(180) NOT NULL,
 instruksi TEXT NOT NULL,
 tenggat DATETIME NULL,
 status ENUM('Draft','Terbit','Arsip') NOT NULL DEFAULT 'Draft',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_tugas_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_tugas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_tugas_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_tugas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 KEY idx_tugas_kelas_status (kelas_id,status,tenggat)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengumpulan_tugas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tugas_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NOT NULL,
 jawaban TEXT NOT NULL,
 dikumpulkan_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 nilai DECIMAL(5,2) NULL,
 umpan_balik TEXT NULL,
 dinilai_guru_id INT UNSIGNED NULL,
 dinilai_at DATETIME NULL,
 UNIQUE KEY uq_pengumpulan_tugas_siswa (tugas_id,siswa_id),
 CONSTRAINT fk_pengumpulan_tugas FOREIGN KEY (tugas_id) REFERENCES tugas_pelajaran(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_pengumpulan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_pengumpulan_penilai FOREIGN KEY (dinilai_guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT chk_pengumpulan_nilai CHECK (nilai IS NULL OR nilai BETWEEN 0 AND 100),
 KEY idx_pengumpulan_siswa (siswa_id,dikumpulkan_at)
) ENGINE=InnoDB;
