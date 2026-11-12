-- Hak akses per modul; role pengguna yang ada tetap memakai users.role.
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    system_locked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(160) NOT NULL UNIQUE,
    module_key VARCHAR(100) NOT NULL,
    action_name ENUM('view','manage') NOT NULL,
    label VARCHAR(180) NOT NULL,
    UNIQUE KEY uq_permission_module_action (module_key, action_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_role_permissions_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (slug,nama,system_locked) VALUES
('admin','Super Admin',1),('operator','Operator',0),('kepala_sekolah','Kepala Sekolah',0),('guru','Guru',0),('siswa','Siswa',0);

INSERT IGNORE INTO permissions (permission_key,module_key,action_name,label) VALUES
('module.siswa.view','siswa','view','Lihat Data Siswa'),('module.siswa.manage','siswa','manage','Kelola Data Siswa'),
('module.guru.view','guru','view','Lihat Data Guru'),('module.guru.manage','guru','manage','Kelola Data Guru'),
('module.staf.view','staf','view','Lihat Tenaga Kependidikan'),('module.staf.manage','staf','manage','Kelola Tenaga Kependidikan'),
('module.kelas.view','kelas','view','Lihat Data Kelas'),('module.kelas.manage','kelas','manage','Kelola Data Kelas'),
('module.mapel.view','mapel','view','Lihat Mata Pelajaran'),('module.mapel.manage','mapel','manage','Kelola Mata Pelajaran'),
('module.tahun_ajaran.view','tahun_ajaran','view','Lihat Tahun Ajaran'),('module.tahun_ajaran.manage','tahun_ajaran','manage','Kelola Tahun Ajaran'),
('module.nilai.view','nilai','view','Lihat Nilai'),('module.nilai.manage','nilai','manage','Kelola Nilai'),
('module.absensi.view','absensi','view','Lihat Absensi Siswa'),('module.absensi.manage','absensi','manage','Kelola Absensi Siswa'),
('module.absensi_guru.view','absensi_guru','view','Lihat Absensi Guru'),('module.absensi_guru.manage','absensi_guru','manage','Kelola Absensi Guru'),
('module.pengumuman.view','pengumuman','view','Lihat Pengumuman'),('module.pengumuman.manage','pengumuman','manage','Kelola Pengumuman'),
('module.berita_sekolah.view','berita_sekolah','view','Lihat Berita'),('module.berita_sekolah.manage','berita_sekolah','manage','Kelola Berita'),
('module.galeri_sekolah.view','galeri_sekolah','view','Lihat Galeri'),('module.galeri_sekolah.manage','galeri_sekolah','manage','Kelola Galeri'),
('module.jadwal.view','jadwal','view','Lihat Jadwal'),('module.jadwal.manage','jadwal','manage','Kelola Jadwal'),
('module.kalender_akademik.view','kalender_akademik','view','Lihat Kalender Akademik'),('module.kalender_akademik.manage','kalender_akademik','manage','Kelola Kalender Akademik'),
('module.kelas_berlangsung.view','kelas_berlangsung','view','Lihat Kelas Berlangsung'),('module.kelas_berlangsung.manage','kelas_berlangsung','manage','Kelola Kelas Berlangsung'),
('module.kelas_binaan.view','kelas_binaan','view','Lihat Kelas Binaan'),('module.kelas_binaan.manage','kelas_binaan','manage','Kelola Kelas Binaan'),
('module.rencana_ajar.view','rencana_ajar','view','Lihat Rencana Ajar'),('module.rencana_ajar.manage','rencana_ajar','manage','Kelola Rencana Ajar'),
('module.kokurikuler.view','kokurikuler','view','Lihat Kokurikuler'),('module.kokurikuler.manage','kokurikuler','manage','Kelola Kokurikuler'),
('module.profil_sekolah.view','profil_sekolah','view','Lihat Pengaturan Sekolah'),('module.profil_sekolah.manage','profil_sekolah','manage','Kelola Pengaturan Sekolah'),
('module.prestasi.view','prestasi','view','Lihat Prestasi'),('module.prestasi.manage','prestasi','manage','Kelola Prestasi'),
('module.notifikasi.view','notifikasi','view','Lihat Notifikasi'),('module.notifikasi.manage','notifikasi','manage','Kelola Notifikasi'),
('module.portal_ekstrakurikuler.view','portal_ekstrakurikuler','view','Lihat Ekstrakurikuler Publik'),('module.portal_ekstrakurikuler.manage','portal_ekstrakurikuler','manage','Kelola Ekstrakurikuler Publik'),
('module.portal_fasilitas.view','portal_fasilitas','view','Lihat Fasilitas Publik'),('module.portal_fasilitas.manage','portal_fasilitas','manage','Kelola Fasilitas Publik'),
('module.portal_struktur_sekolah.view','portal_struktur_sekolah','view','Lihat Struktur Publik'),('module.portal_struktur_sekolah.manage','portal_struktur_sekolah','manage','Kelola Struktur Publik'),
('module.portal_prestasi_publik.view','portal_prestasi_publik','view','Lihat Prestasi Publik'),('module.portal_prestasi_publik.manage','portal_prestasi_publik','manage','Kelola Prestasi Publik'),
('module.portal_jadwal_publik.view','portal_jadwal_publik','view','Lihat Jadwal Publik'),('module.portal_jadwal_publik.manage','portal_jadwal_publik','manage','Kelola Jadwal Publik'),
('module.portal_dokumen_publik.view','portal_dokumen_publik','view','Lihat Dokumen Publik'),('module.portal_dokumen_publik.manage','portal_dokumen_publik','manage','Kelola Dokumen Publik'),
('module.portal_tautan_sosial.view','portal_tautan_sosial','view','Lihat Tautan Sosial'),('module.portal_tautan_sosial.manage','portal_tautan_sosial','manage','Kelola Tautan Sosial'),
('module.pengguna.view','pengguna','view','Lihat Pengguna'),('module.pengguna.manage','pengguna','manage','Kelola Pengguna'),
('module.log_aktivitas.view','log_aktivitas','view','Lihat Log Aktivitas'),('module.log_aktivitas.manage','log_aktivitas','manage','Kelola Log Aktivitas'),
('module.profil.view','profil','view','Lihat Profil'),('module.profil.manage','profil','manage','Kelola Profil'),
('module.moderasi_konten.view','moderasi_konten','view','Lihat Moderasi Konten'),('module.moderasi_konten.manage','moderasi_konten','manage','Kelola Moderasi Konten'),
('module.portal_admin.view','portal_admin','view','Lihat Ringkasan Portal'),('module.portal_admin.manage','portal_admin','manage','Kelola Ringkasan Portal'),
('module.ppdb_admin.view','ppdb_admin','view','Lihat PPDB'),('module.ppdb_admin.manage','ppdb_admin','manage','Kelola PPDB'),
('module.pesan_masuk.view','pesan_masuk','view','Lihat Pesan Masuk'),('module.pesan_masuk.manage','pesan_masuk','manage','Kelola Pesan Masuk'),
('module.permissions.view','permissions','view','Lihat Pengaturan Hak Akses'),('module.permissions.manage','permissions','manage','Kelola Pengaturan Hak Akses');

-- Pertahankan perilaku izin lama untuk semua peran non-admin sebagai baseline awal.
INSERT IGNORE INTO role_permissions (role_id,permission_id,granted)
SELECT r.id,p.id,1 FROM roles r CROSS JOIN permissions p
WHERE r.slug='admin'
   OR (r.slug='operator' AND (
      (p.action_name='view' AND p.module_key NOT IN ('moderasi_konten','ppdb_admin','portal_admin','portal_ekstrakurikuler','portal_fasilitas','portal_struktur_sekolah','portal_prestasi_publik','portal_jadwal_publik','portal_dokumen_publik','portal_tautan_sosial','pengguna','log_aktivitas','profil_sekolah','pesan_masuk','permissions'))
      OR (p.action_name='manage' AND p.module_key IN ('siswa','guru','staf','kelas','mapel','tahun_ajaran','nilai','absensi','absensi_guru','jadwal','pengumuman','berita_sekolah','galeri_sekolah','kalender_akademik','kelas_berlangsung','kelas_binaan','rencana_ajar','kokurikuler','prestasi','notifikasi'))
   ))
   OR (r.slug='guru' AND p.action_name='view' AND p.module_key IN ('notifikasi','rencana_ajar','profil'))
   OR (r.slug='siswa' AND p.action_name='view' AND p.module_key IN ('notifikasi','profil'))
   OR (r.slug='kepala_sekolah' AND p.action_name='view' AND p.module_key IN ('kelas','mapel','notifikasi','prestasi','rencana_ajar','profil'));
