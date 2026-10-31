# SIMS SMP Negeri 4 Pirime

Sistem Informasi Manajemen Sekolah berbasis PHP dan MySQL/MariaDB. SIMS menghubungkan website publik sekolah dengan dashboard internal untuk mengelola data, kegiatan belajar, laporan, dan akun pengguna.

## Fitur utama

- Login terpadu dengan hak akses sesuai peran.
- Dashboard responsif untuk Super Admin, Kepala Sekolah, Operator, Guru, dan Siswa.
- Dashboard Guru berisi jadwal harian, pemantauan absensi dan pengumpulan; dashboard Kepala Sekolah menyajikan rekap akademik, pembelajaran, kehadiran, dan prestasi.
- Manajemen data siswa, guru, kelas, mata pelajaran, tahun ajaran, jadwal, absensi, dan nilai.
- Manajemen akun, tautan akun ke profil guru/siswa, foto profil, status akun, dan reset kata sandi.
- Materi dan tugas pembelajaran dengan lampiran privat, pengumpulan jawaban teks/file, serta penilaian guru.
- Inbox notifikasi per akun dengan pencarian, status baca, dan arsip.
- Pencatatan prestasi siswa dan alur rencana ajar guru.
- Kontrol Super Admin untuk log aktivitas dan pemantauan status materi/tugas.
- Laporan siswa, guru, distribusi kelas, nilai, jadwal, absensi, tugas, dan pengumpulan; tersedia cetak dan ekspor CSV.
- Website publik berisi profil sekolah, berita, pengumuman, agenda, galeri, PPDB, kontak, dan direktori guru.
- Pengamanan dasar: password hash, prepared statements, CSRF, validasi unggahan, pembatasan percobaan login, dan pemeriksaan hak akses di server.

## Peran pengguna

| Peran | Kegunaan utama |
|---|---|
| **Super Admin** | Mengelola akun, data, konfigurasi, laporan, aktivitas, serta memantau konten dan pembelajaran. |
| **Kepala Sekolah** | Memantau informasi sekolah dan membuka laporan sesuai kewenangan. |
| **Operator** | Mengelola administrasi sekolah sesuai hak akses yang tersedia. |
| **Guru** | Mengakses penugasan, kelas, absensi, nilai, materi, dan tugas yang menjadi kewenangannya. |
| **Siswa** | Melihat jadwal, absensi, nilai, materi, dan tugas sesuai akun serta kelasnya. |

## Teknologi dan persyaratan

- PHP 8.1 atau lebih baru
- Ekstensi PHP `pdo_mysql`, `mbstring`, dan `fileinfo` aktif
- MySQL atau MariaDB
- Apache (XAMPP disarankan untuk pengembangan lokal)
- Browser modern

> Impor berkas XLS/XLSX memerlukan paket PhpSpreadsheet dan ekstensi PHP `zip`. Impor CSV tetap tersedia tanpa dependensi tersebut.

## Instalasi lokal dengan XAMPP

1. Salin folder proyek ke `C:\xampp\htdocs\sims_smpn4`.
2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
3. Buat atau impor database `sims_db` dengan menjalankan `database.sql` melalui phpMyAdmin.
4. Salin `config/database.example.php` menjadi `config/database.php` jika konfigurasi lokal belum ada. Untuk lokal, nilai bawaan ditujukan bagi XAMPP (`127.0.0.1`, database `sims_db`, pengguna `root`, password kosong). Server produksi harus mengatur `SIMS_APP_ENV=production` dan variabel database pada environment PHP. Lihat `.env.example` dan `docs/operations.md`; file contoh bukan file konfigurasi yang dimuat otomatis.
5. Periksa backup database, lalu jalankan `php database/migrate.php` dari PowerShell di folder proyek. Runner hanya untuk CLI, mencatat checksum/status migrasi, dan menolak database yang bukan `sims_db`.
6. Buka `http://localhost/sims_smpn4/setup_admin.php` dan buat akun Super Admin dengan password kuat (minimal 10 karakter).
7. Setelah akun dibuat, hapus atau nonaktifkan `setup_admin.php` dari server.
8. Buka `http://localhost/sims_smpn4/` untuk dashboard internal atau `http://localhost/sims_smpn4/public/` untuk website publik.

> Jalankan migrasi pada database yang benar. Buat backup yang dapat dipulihkan sebelum memperbarui database berisi data penting.

## Struktur proyek

- `index.php` — dashboard internal dan router modul administrasi.
- `login.php`, `logout.php`, `profile.php` — autentikasi, keluar, dan pengaturan profil.
- `users.php` — Manajemen Pengguna.
- `teacher.php`, `student.php`, `learning.php` — halaman Guru, Siswa, materi, tugas, dan pengumpulan.
- `reports.php`, `activity.php` — laporan dan log aktivitas Super Admin.
- `public/` — halaman website publik.
- `modules/` — konfigurasi modul data.
- `includes/` — autentikasi, helper, navigasi, dan layout.
- `config/` — konfigurasi koneksi database.
- `database.sql`, `database/migrations/` — skema awal dan perubahan database bertahap.
- `uploads/` — file profil, portal, guru, dan materi; akses file privat dilayani melalui pemeriksaan aplikasi.
- `docs/` — panduan peran, backup/pemulihan, migrasi, dan penerapan.
- `storage/logs/` — log harian lokal; untuk produksi arahkan `SIMS_LOG_DIR` ke luar web root.

## Catatan keamanan dan batasan

- Ganti konfigurasi database bawaan sebelum memasang aplikasi di server publik. Jangan memakai akun database `root` di produksi.
- Gunakan HTTPS, password unik yang kuat, akses database minimum, dan backup rutin untuk deployment online.
- Jangan mengunggah data siswa, password, token, atau file backup ke repositori Git.
- Lampiran tugas dan materi disimpan privat di `uploads/` dan diunduh melalui pemeriksaan izin aplikasi; format yang didukung dibatasi dan ukuran maksimal 10 MB per berkas.
- Izin per modul yang dapat dikonfigurasi, riwayat revisi konten, backup/pemulihan dari dashboard, dan riwayat perpindahan kelas masih dapat dikembangkan lebih lanjut.
- Uji semua peran, laporan, unggahan, dan pemulihan backup pada lingkungan pengujian sebelum aplikasi digunakan secara resmi.
