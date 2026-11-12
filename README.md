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
- Apache dan MySQL/MariaDB melalui XAMPP
- Browser modern

> Aplikasi berjalan tanpa Composer atau paket PHP pihak ketiga. Impor data guru memakai CSV; lembar Excel harus disimpan sebagai CSV UTF-8 sebelum diunggah. Backup berkas memerlukan ekstensi PHP `zip` pada PHP CLI.

## Tes otomatis

Proyek menyediakan runner tes tanpa paket eksternal. Dari PowerShell pada folder proyek, jalankan:

```powershell
php tests/run.php
```

Runner memeriksa helper HTML/CSRF dan izin peran, parser serta dependensi migrasi, sintaks seluruh berkas PHP, sintaks JavaScript bila Node.js tersedia, keseimbangan blok CSS, dan checksum/status migrasi database lokal. Pemeriksaan database hanya membaca data; runner tidak membuat, mengubah, atau menghapus tabel maupun baris. Jika database lokal tidak tersedia, pemeriksaan tersebut dilewati secara terlihat. Atur `SIMS_TEST_DB=1` untuk menjadikan koneksi database wajib dan menggagalkan tes bila database tidak tersedia.

Jika Apache aktif, runner juga menguji halaman login dan rute publik pada `http://localhost/sims_smpn4`. Untuk alamat lokal berbeda, set URL proyek dan wajibkan uji HTTP:

```powershell
$env:SIMS_TEST_BASE_URL = 'http://sims_smpn4.test'
$env:SIMS_TEST_HTTP = '1'
php tests/run.php
```

Kegagalan tes menghasilkan exit code bukan nol agar dapat dipakai di proses otomatis.

## Instalasi lokal dengan XAMPP

1. Letakkan proyek di `C:\xampp\htdocs\sims_smpn4`, lalu jalankan Apache dan MySQL pada XAMPP Control Panel.
2. Pastikan PHP XAMPP minimal 8.1 dan aktifkan `pdo_mysql`, `mbstring`, `fileinfo`, serta `zip` pada `C:\xampp\php\php.ini`; restart Apache setelah perubahan.
3. Impor `database.sql` ke MySQL lokal melalui phpMyAdmin bila database belum ada. Konfigurasi bawaan `config/database.php` memakai `127.0.0.1:3306`, `sims_db`, user `root`, dan password kosong. Jangan mengganti atau mengimpor ulang database yang sudah berisi data.
4. Dari PowerShell di direktori proyek, buat dan verifikasi backup di luar web root sebelum migrasi:

   ```powershell
   $env:SIMS_BACKUP_DIR = "$env:USERPROFILE\Documents\SIMS_Backups"
   C:\xampp\php\php.exe tools/backup.php --check
   C:\xampp\php\php.exe tools/backup.php
   C:\xampp\php\php.exe database/migrate.php
   ```

   Runner migrasi hanya untuk CLI, mencatat checksum/status, dan menolak database selain `sims_db`. Jangan mengedit file migrasi yang sudah tercatat.
5. Buka `http://localhost/sims_smpn4/` untuk masuk ke aplikasi. Kelola portal publik pada `http://localhost/sims_smpn4/public/`.
6. Gunakan akun Super Admin yang sudah disiapkan. Endpoint `setup_admin.php` hanya untuk setup lokal yang terkendali; nonaktifkan setelah akun dibuat.

> Jalankan migrasi hanya pada database lokal yang benar. Backup dan uji pemulihan penuh sebelum memperbarui database berisi data penting.

## Struktur proyek

- `index.php` — dashboard internal dan router modul administrasi.
- `login.php`, `logout.php`, `profile.php` — autentikasi, keluar, dan pengaturan profil.
- `users.php` — Manajemen Pengguna.
- `teacher.php`, `student.php`, `learning.php` — halaman Guru, Siswa, materi, tugas, dan pengumpulan.
- `reports.php`, `activity.php` — laporan dan log aktivitas Super Admin.
- `public/` — halaman website publik.
- `assets/templates/` — template CSV dan Excel untuk impor data guru.
- `docs/portal-publik.md` — cara mengelola konten layanan publik, dokumen unduhan, media sosial, dan periode PPDB.
- `docs/laporan-lengkap-navigasi-portal-publik.md` — peta menu Profil/Akademik/Informasi/PPDB, isi halaman, tata letak, sumber data, dan tindak lanjut verifikasi.
- `modules/` — konfigurasi modul data.
- `includes/` — autentikasi, helper, navigasi, dan layout.
- `config/` — konfigurasi koneksi database.
- `database.sql`, `database/migrations/` — skema awal dan perubahan database bertahap.
- `tools/backup.php`, `tools/restore-test.php` — backup database/berkas dan uji pemulihan ke database lokal terpisah.
- `docs/backup-restore.md` — konfigurasi, jadwal, verifikasi, dan prosedur pemulihan.
- `student_class_history.php` — riwayat perpindahan kelas siswa dengan filter, cetak, dan ekspor CSV.
- `staff_import.php`, `staff_export.php` — impor staf dua tahap dan ekspor CSV yang dibatasi izin.
- `docs/module-inventory.md`, `docs/database-relationships.md`, `docs/feature-acceptance.md`, `docs/status-pengembangan-sims.md`, `docs/changelog.md` — inventaris modul, peta relasi, checklist penerimaan, status pengembangan, dan catatan perubahan.
- `uploads/` — file profil, portal, guru, dan materi; akses file privat dilayani melalui pemeriksaan aplikasi.
- `docs/` — panduan peran, backup/pemulihan, migrasi, dan penerapan.
- `storage/logs/` — log harian lokal; untuk produksi arahkan `SIMS_LOG_DIR` ke luar web root.
- `database/migrations/2026_11_01_public_contact_messages.sql` — tabel percakapan layanan pesan dari portal publik.

## Catatan keamanan dan batasan

- Ganti konfigurasi database bawaan sebelum memasang aplikasi di server publik. Jangan memakai akun database `root` di produksi.
- Gunakan HTTPS, password unik yang kuat, akses database minimum, dan backup rutin untuk deployment online.
- Jangan mengunggah data siswa, password, token, atau file backup ke repositori Git.
- Lampiran tugas dan materi disimpan privat di `uploads/` dan diunduh melalui pemeriksaan izin aplikasi; format yang didukung dibatasi dan ukuran maksimal 10 MB per berkas.
- Backup manual tersedia melalui Dashboard Super Admin dan CLI; backup harian Windows Task Scheduler berjalan lokal. Enkripsi, salinan offsite, dan retensi otomatis belum dikonfigurasi.
- Izin per modul dan pencatatan perubahan terstruktur tersedia untuk peran operasional. Riwayat perpindahan kelas tercatat untuk perubahan baru; impor histori lama dan halaman histori khusus masih perlu dikembangkan.
- Uji semua peran, laporan, unggahan, dan pemulihan backup pada lingkungan pengujian sebelum aplikasi digunakan secara resmi.
# smpn4pirime
