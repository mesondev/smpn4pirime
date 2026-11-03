# SIMS SMP Negeri 4 Pirime

Sistem Informasi Manajemen Sekolah berbasis PHP dan MySQL/MariaDB. SIMS menghubungkan website publik sekolah dengan dashboard internal untuk mengelola data, kegiatan belajar, laporan, dan akun pengguna.

## Fitur utama

- Login terpadu dengan hak akses sesuai peran.
- Dashboard responsif untuk Super Admin, Kepala Sekolah, Operator, Guru, dan Siswa.
- Dashboard Guru berisi jadwal harian, pemantauan absensi dan pengumpulan; dashboard Kepala Sekolah menyajikan rekap akademik, pembelajaran, kehadiran, dan prestasi.
- Manajemen data siswa, guru, kelas, mata pelajaran, tahun ajaran, jadwal, absensi, dan nilai.
- Manajemen akun, tautan akun ke profil guru/siswa, foto profil, status akun, dan reset kata sandi.
- Pusat pengaturan akun dengan preferensi tampilan per pengguna, termasuk tema, ukuran teks, kerapatan tabel, sidebar, dan periode grafik bawaan.
- Materi dan tugas pembelajaran dengan lampiran privat, pengumpulan jawaban teks/file, serta penilaian guru.
- Inbox notifikasi per akun dengan pencarian, status baca, dan arsip.
- Pencatatan prestasi siswa dan alur rencana ajar guru.
- Kontrol Super Admin untuk log aktivitas dan pemantauan status materi/tugas.
- Laporan siswa, guru, distribusi kelas, nilai, jadwal, absensi, tugas, dan pengumpulan; tersedia cetak dan ekspor CSV.
- Website publik berisi profil sekolah, berita, pengumuman, agenda, galeri, PPDB, kontak, dan direktori guru.
- Pengelolaan periode dan permohonan PPDB melalui dashboard Super Admin dengan pencarian, filter status, catatan pendaftar, dan riwayat perubahan status.
- Ringkasan dan pengelolaan konten portal publik untuk ekstrakurikuler, fasilitas, struktur organisasi, prestasi, jadwal publik, dokumen PDF, dan tautan media sosial; publikasi menerapkan pemeriksaan izin dan verifikasi sesuai jenis konten.
- Formulir kontak publik mengirim percakapan ke kotak pesan Super Admin; balasan disimpan dan diteruskan ke email pengirim melalui layanan email server.
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
- Apache (Laragon atau XAMPP untuk pengembangan lokal)
- Browser modern

> Impor berkas XLS/XLSX memerlukan paket PhpSpreadsheet dan ekstensi PHP `zip`. Impor CSV tetap tersedia tanpa dependensi tersebut. Backup berkas juga memerlukan ekstensi PHP `zip` pada PHP CLI Laragon.

## Tes otomatis

Proyek menyediakan runner tes tanpa paket eksternal. Dari terminal Laragon pada folder proyek, jalankan:

```powershell
composer test
```

Runner memeriksa helper HTML/CSRF dan izin peran, parser serta dependensi migrasi, sintaks seluruh berkas PHP, sintaks JavaScript bila Node.js tersedia, keseimbangan blok CSS, dan checksum/status migrasi database lokal. Pemeriksaan database hanya membaca data; runner tidak membuat, mengubah, atau menghapus tabel maupun baris. Jika database lokal tidak tersedia, pemeriksaan tersebut dilewati secara terlihat. Atur `SIMS_TEST_DB=1` untuk menjadikan koneksi database wajib dan menggagalkan tes bila database tidak tersedia.

Jika Apache aktif, runner juga menguji halaman login dan rute publik pada `http://localhost/sims_smpn4`. Untuk Laragon dengan virtual host berbeda, set URL proyek dan wajibkan uji HTTP:

```powershell
$env:SIMS_TEST_BASE_URL = 'http://sims_smpn4.test'
$env:SIMS_TEST_HTTP = '1'
composer test
```

Kegagalan tes menghasilkan exit code bukan nol agar dapat dipakai di proses otomatis.

## Instalasi lokal dengan Laragon

1. Gunakan folder proyek ini sebagai document root Apache Laragon. Jika proyek berada di luar `C:\laragon\www` (misalnya `C:\xampp\htdocs\sims_smpn4`), buat virtual host Laragon yang menunjuk ke folder tersebut; jangan mengedit salinan lain karena perubahan kode harus tetap berada di workspace yang sama.
2. Jalankan Apache dan MySQL/MariaDB dari Laragon. Pastikan versi PHP Laragon yang aktif minimal 8.1 dan ekstensi `pdo_mysql`, `mbstring`, serta `fileinfo` aktif.
3. Buat atau impor database `sims_db` melalui HeidiSQL atau phpMyAdmin Laragon menggunakan `database.sql`.
4. `config/database.php` memakai default lokal `127.0.0.1:3306`, database `sims_db`, pengguna `root`, dan password kosong. Sesuaikan file konfigurasi lokal yang diabaikan Git jika instalasi Laragon Anda memakai port/kredensial berbeda. Nilai `.env.example` hanya contoh; aplikasi tidak membaca file `.env` secara otomatis.
5. Buka **Laragon Terminal** agar perintah `php` memakai versi PHP aktif Laragon. Atur lokasi backup privat di luar document root, lalu periksa dan buat backup sebelum migrasi:

   ```powershell
   $env:SIMS_BACKUP_DIR = 'C:\laragon\data\sims_smpn4_backups'
   php tools/backup.php --check
   php tools/backup.php
   php database/migrate.php
   ```

   Runner migrasi hanya untuk CLI, mencatat checksum/status migrasi, dan menolak database yang bukan `sims_db`.
6. Buka alamat virtual host yang dibuat Laragon (umumnya `http://sims_smpn4.test/`) untuk dashboard, atau tambahkan `/public/` untuk portal publik. Jika memakai alias localhost, gunakan URL yang sesuai dengan konfigurasi Apache Anda.
7. Buat akun Super Admin melalui `setup_admin.php`, lalu hapus atau nonaktifkan endpoint tersebut setelah akun dibuat.

> Jangan arahkan Laragon ke database produksi. Migrasi mengubah struktur database; buat backup terlebih dahulu dan pastikan targetnya adalah database lokal `sims_db`.

## Instalasi lokal dengan XAMPP

Proyek ini juga dapat dijalankan dari `C:\xampp\htdocs\sims_smpn4`. Jalankan Apache/MySQL dari XAMPP, buka `http://localhost/sims_smpn4/`, dan ikuti langkah database, konfigurasi lokal, migrasi, serta setup admin di atas. Gunakan terminal yang sesuai dengan versi PHP aktif XAMPP saat menjalankan migrasi.

> Jalankan migrasi pada database yang benar. Buat backup yang dapat dipulihkan sebelum memperbarui database berisi data penting.

## Struktur proyek

- `index.php` — dashboard internal dan router modul administrasi.
- `login.php`, `logout.php`, `profile.php` — autentikasi, keluar, dan pengaturan profil.
- `users.php` — Manajemen Pengguna.
- `teacher.php`, `student.php`, `learning.php` — halaman Guru, Siswa, materi, tugas, dan pengumpulan.
- `reports.php`, `activity.php` — laporan dan log aktivitas Super Admin.
- `public/` — halaman website publik.
- `assets/templates/` — template CSV dan Excel untuk impor data guru.
- `docs/portal-publik.md` — cara mengelola konten layanan publik, dokumen unduhan, media sosial, dan periode PPDB.
- `modules/` — konfigurasi modul data.
- `includes/` — autentikasi, helper, navigasi, dan layout.
- `config/` — konfigurasi koneksi database.
- `database.sql`, `database/migrations/` — skema awal dan perubahan database bertahap.
- `tools/backup.php`, `tools/restore-test.php` — backup database/berkas dan uji pemulihan ke database lokal terpisah.
- `docs/backup-restore.md` — konfigurasi, jadwal, verifikasi, dan prosedur pemulihan.
- `uploads/` — file profil, portal, guru, dan materi; akses file privat dilayani melalui pemeriksaan aplikasi.
- `docs/` — panduan peran, backup/pemulihan, migrasi, dan penerapan.
- `storage/logs/` — log harian lokal; untuk produksi arahkan `SIMS_LOG_DIR` ke luar web root.
- `database/migrations/2026_11_01_public_contact_messages.sql` — tabel percakapan layanan pesan dari portal publik.

## Catatan keamanan dan batasan

- Ganti konfigurasi database bawaan sebelum memasang aplikasi di server publik. Jangan memakai akun database `root` di produksi.
- Gunakan HTTPS, password unik yang kuat, akses database minimum, dan backup rutin untuk deployment online.
- Jangan mengunggah data siswa, password, token, atau file backup ke repositori Git.
- Lampiran tugas dan materi disimpan privat di `uploads/` dan diunduh melalui pemeriksaan izin aplikasi; format yang didukung dibatasi dan ukuran maksimal 10 MB per berkas.
- Backup dan uji pemulihan tersedia melalui CLI; otomasi terjadwal serta pengelolaan backup dari dashboard belum tersedia.
- Izin per modul yang dapat dikonfigurasi, riwayat revisi konten, dan riwayat perpindahan kelas masih dapat dikembangkan lebih lanjut.
- Uji semua peran, laporan, unggahan, dan pemulihan backup pada lingkungan pengujian sebelum aplikasi digunakan secara resmi.
# smpn4pirime
