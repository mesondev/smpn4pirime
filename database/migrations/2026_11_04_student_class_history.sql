-- Simpan setiap perubahan kelas siswa tanpa menghapus kelas/tahun ajaran sebelumnya.
CREATE TABLE IF NOT EXISTS student_class_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NOT NULL,
    kelas_asal_id INT UNSIGNED NULL,
    tahun_ajaran_asal_id INT UNSIGNED NULL,
    kelas_tujuan_id INT UNSIGNED NOT NULL,
    tahun_ajaran_tujuan_id INT UNSIGNED NOT NULL,
    alasan VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_class_history_student FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_class_history_old_class FOREIGN KEY (kelas_asal_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_class_history_old_year FOREIGN KEY (tahun_ajaran_asal_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_class_history_new_class FOREIGN KEY (kelas_tujuan_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_class_history_new_year FOREIGN KEY (tahun_ajaran_tujuan_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_class_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_student_class_history_student_date (siswa_id, dibuat_pada),
    KEY idx_student_class_history_year (tahun_ajaran_tujuan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
