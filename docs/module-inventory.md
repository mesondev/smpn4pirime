# Inventaris Modul SIMS

Inventaris ini mengikuti `modules/config.php`, router `index.php`, halaman khusus, dan aturan dasar pada `includes/helpers.php`. Akses akhir tetap harus diperiksa di server melalui `can_view_module()` dan `can_manage_module()`; perubahan grant RBAC dapat membuat akses berbeda dari baseline di bawah.

## Modul data yang memakai CRUD umum

| Kunci modul | Nama layar | Tabel utama | Alur khusus / catatan |
|---|---|---|---|
| `siswa` | Data Siswa | `siswa` | Perubahan kelas dicatat di `student_class_history` bila migrasi tersedia. |
| `guru` | Data Guru | `guru` | Halaman khusus `modules/guru.php`; mendukung foto, akun tertaut, ekspor, dan impor CSV dua tahap dengan validasi/pratinjau. |
| `staf` | Tenaga Kependidikan | `staff` | CRUD umum; terpisah dari tabel guru. Impor CSV memiliki pratinjau/konfirmasi, filter jabatan/status, ekspor CSV, dan laporan agregat per jabatan/status. |
| `kelas` | Data Kelas | `kelas` | Memerlukan tahun ajaran. |
| `mapel` | Mata Pelajaran | `mapel` | Guru pengampu bersifat opsional. |
| `tahun_ajaran` | Tahun Ajaran | `tahun_ajaran` | Menjadi induk periode akademik. |
| `nilai` | Nilai Siswa | `nilai` | Terhubung ke siswa, mapel, dan tahun ajaran. |
| `absensi` | Absensi Siswa | `absensi` | Tanggal dan siswa wajib sesuai aturan sekolah. |
| `absensi_guru` | Absensi Guru | `absensi_guru` | Halaman ringkasan guru tersedia pada `teacher.php`. |
| `pengumuman` | Pengumuman | `pengumuman` | Memiliki alur review konten. |
| `berita_sekolah` | Berita Sekolah | `berita_sekolah` | Memiliki alur review dan publikasi. |
| `galeri_sekolah` | Galeri Sekolah | `galeri_sekolah` | Memiliki alur review dan publikasi. |
| `jadwal` | Jadwal Kelas | `jadwal_pelajaran` | Laporan jadwal tersedia di `reports.php`. |
| `kalender_akademik` | Kalender Akademik | `kalender_akademik` | Memiliki alur review/publikasi. |
| `kelas_berlangsung` | Kelas Berlangsung | `kelas_berlangsung` | Digunakan juga untuk pemantauan pembelajaran. |
| `kelas_binaan` | Kelas Binaan | `kelas_binaan` | Menghubungkan kelas, guru, dan tahun ajaran. |
| `rencana_ajar` | Rencana Ajar | `rencana_ajar` | Pengajuan dan tinjauan mengikuti status yang tersedia. |
| `kokurikuler` | Kokurikuler (P5) | `kegiatan_kokurikuler` | Kelas dan penanggung jawab opsional. |
| `profil_sekolah` | Pengaturan Sekolah | `profil_sekolah` | Profil tunggal; tidak dapat dihapus dari CRUD umum. |
| `prestasi` | Prestasi Siswa | `prestasi_siswa` | Relasi siswa dan tahun ajaran mengikuti kolom yang tersedia. |
| `notifikasi` | Notifikasi | `notifikasi` | Data dapat dibatasi per penerima. |
| `portal_ekstrakurikuler` | Ekstrakurikuler Publik | `portal_ekstrakurikuler` | Pengelolaan portal oleh Super Admin. |
| `portal_fasilitas` | Fasilitas Publik | `portal_fasilitas` | Foto memakai validasi unggahan portal. |
| `portal_struktur_sekolah` | Struktur Organisasi Publik | `portal_struktur_sekolah` | Foto dan status publikasi harus diverifikasi. |
| `portal_prestasi_publik` | Prestasi Publik | `portal_prestasi_publik` | Publikasi memerlukan verifikasi bukti dan izin. |
| `portal_jadwal_publik` | Jadwal Publik | `portal_jadwal_publik` | Jadwal yang ditayangkan harus diverifikasi sekolah. |
| `portal_dokumen_publik` | Dokumen Unduhan Publik | `portal_dokumen_publik` | PDF disimpan dan diunduh melalui pemeriksaan aplikasi. |
| `portal_tautan_sosial` | Tautan Media Sosial Resmi | `portal_tautan_sosial` | URL HTTPS akun resmi. |

## Halaman khusus di luar CRUD umum

| Halaman | Kegunaan | Akses dasar |
|---|---|---|
| `users.php` | Pembuatan, perubahan, tautan, aktivasi akun, dan pengelolaan akun | Super Admin; tindakan sensitif tetap memiliki guard tersendiri. |
| `permissions.php` | Pengaturan grant RBAC | Super Admin. |
| `audit.php`, `activity.php` | Audit terstruktur dan log aktivitas | Super Admin sesuai pemeriksaan rute. |
| `student.php`, `teacher.php`, `learning.php` | Dashboard dan alur belajar sesuai akun tertaut | Siswa/Guru sesuai kepemilikan dan relasi kelas. |
| `ppdb_admin.php` | Periode, daftar permohonan, tinjauan status, dan riwayat | Super Admin sesuai kebijakan rute. |
| `reports.php` | Laporan dan ekspor/cetak | Sesuai tipe laporan dan guard masing-masing. |
| `student_class_history.php` | Riwayat kelas per siswa, filter, cetak, ekspor CSV | Memerlukan izin lihat modul siswa. |

## Standar penerimaan setiap modul

- Daftar, pencarian/filter yang relevan, pagination, jumlah data, empty state.
- Detail menampilkan label manusia dan relasi, bukan hanya ID.
- Tambah/edit memvalidasi di server, memeriksa izin dan CSRF.
- Hapus atau arsip mengikuti kebijakan retensi dan relasi database.
- Perubahan penting dicatat, query memakai PDO prepared statement, dan keluaran di-escape.
- Tampilan diuji di ponsel dan desktop sebelum modul dinyatakan selesai.

Inventaris ini tidak menyatakan semua modul telah lulus uji fungsional. Isi status pengujian aktual pada `docs/feature-acceptance.md`.
