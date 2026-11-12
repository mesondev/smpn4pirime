# Backup dan uji pemulihan SIMS

Perintah berikut untuk PHP Native yang dijalankan dari **XAMPP Shell/PowerShell**. Backup ditulis ke lokasi privat di luar folder proyek dan document root. Jangan arahkan lokasi backup ke `public/`, `uploads/`, atau folder yang dapat diunduh melalui browser.

## Prasyarat

- XAMPP Control Panel menunjukkan Apache dan MySQL berjalan stabil; pastikan MySQL dapat tetap berjalan dan database `sims_db` dapat dibuka di phpMyAdmin.
- PHP CLI XAMPP minimal 8.1 dengan `pdo_mysql` dan `zip` aktif.
- `mysqldump.exe` dan `mysql.exe` tersedia di `C:\xampp\mysql\bin`.
- Akun database mampu membaca struktur/data SIMS; akun yang dipakai `restore-test.php` juga memerlukan izin membuat database lokal.
- Folder backup berada di luar folder proyek dan dapat ditulis oleh akun Windows yang menjalankan XAMPP/PowerShell Task Scheduler.

Contoh lokasi untuk instalasi XAMPP lokal (gunakan drive terpisah jika tersedia):

```powershell
$env:SIMS_BACKUP_DIR = 'D:\Backup_SIMs\sims_smpn4'
$env:SIMS_MYSQLDUMP = 'C:\xampp\mysql\bin\mysqldump.exe'
$env:SIMS_MYSQL = 'C:\xampp\mysql\bin\mysql.exe'
$env:SIMS_DB_HOST = '127.0.0.1'
$env:SIMS_DB_PORT = '3306'
$env:SIMS_DB_NAME = 'sims_db'
$env:SIMS_DB_USER = 'root'
# Bila MySQL memakai password, atur SIMS_DB_PASS sebagai environment variable privat
# untuk sesi/task Windows yang menjalankan backup. Jangan simpan di skrip/repository.
```

Pastikan path PHP CLI berasal dari `C:\xampp\php\php.exe` (atau tambahkan `C:\xampp\php` ke PATH). Kredensial database dibaca dari `SIMS_DB_HOST`, `SIMS_DB_PORT`, `SIMS_DB_NAME`, `SIMS_DB_USER`, dan `SIMS_DB_PASS`, sama seperti konfigurasi aplikasi. File `.env` tidak dibaca otomatis oleh proyek. Jika database memakai password, sediakan sebagai environment variable process milik akun Windows yang menjalankan tugas, dengan ACL terbatas; jangan tulis password langsung di argumen proses, file `.bat`, Task Scheduler action, atau repository.

## Membuat backup

Jalankan dari folder proyek:

```powershell
php tools/backup.php --check
php tools/backup.php
```

Perintah `--check` hanya memeriksa koneksi database, ekstensi ZIP, executable MySQL, dan lokasi backup. Perintah backup membuat folder bertanggal berisi:

- `database.sql` — dump database dengan struktur, data, trigger, routine, dan event.
- `files.zip` — berkas di `uploads/` dan `storage/`.
- `manifest.json` — waktu, ukuran, jumlah file, dan SHA-256 kedua hasil backup.

Super Admin juga dapat menjalankan backup manual melalui **Dashboard → Kontrol Sistem → Buat backup sekarang**. Form menggunakan sesi login dan token CSRF; proses web hanya memanggil skrip CLI yang sama (`tools/backup.php`) dengan PHP XAMPP. Backup ditulis ke `SIMS_BACKUP_DIR`, atau secara bawaan ke `%USERPROFILE%\Documents\SIMS_Backups` pada Windows. Tunggu notifikasi berhasil sebelum menutup halaman; tombol menolak permintaan bersamaan ketika proses backup lain sedang berjalan.

Jika proses gagal, status keluar bukan nol dan berkas parsial di folder hasil yang baru dibuat dibersihkan. Script hanya dapat dijalankan melalui CLI dan menolak folder output di dalam proyek.

Konfigurasi rahasia lokal seperti `config/database.php` dan `.env` tidak dimasukkan ke arsip. Simpan salinan konfigurasi yang diperlukan secara terpisah di lokasi privat dan terlindungi; jangan commit atau membagikannya bersama backup data.

## Memeriksa dan menguji pemulihan

Pertama, verifikasi manifest, checksum, dan jalur file tanpa membuat database:

```powershell
php tools/restore-test.php 'D:\Backup_SIMs\sims_smpn4\NAMA_FOLDER_BACKUP' --verify-only
```

Kemudian, uji pemulihan penuh:

```powershell
php tools/restore-test.php 'D:\Backup_SIMs\sims_smpn4\NAMA_FOLDER_BACKUP'
```

Uji penuh hanya diizinkan pada environment lokal dan host database lokal. Script membuat database baru bernama `sims_restore_test_...` serta folder `restore-test-...` di dalam lokasi backup. Script tidak menghapus database yang sudah ada, tidak mengganti `sims_db`, dan mempertahankan hasil gagal agar dapat diperiksa. Setelah admin memeriksa tabel dan file hasil pemulihan, database dan folder uji dapat dibersihkan secara manual sesuai kebijakan sekolah.

Keberhasilan uji menunjukkan dump dapat diimpor, tabel dapat dibaca, arsip dapat diekstrak, dan jalur dalam ZIP aman. Sebelum rilis, lanjutkan dengan menguji aplikasi terhadap salinan database hasil pemulihan memakai konfigurasi lokal terpisah; jangan mengubah konfigurasi aplikasi utama untuk menunjuk ke database uji.

## Jadwal, retensi, dan salinan terpisah

Pada komputer XAMPP ini, Task Scheduler Windows **SIMS SMPN4 Backup Harian** sudah dibuat untuk berjalan setiap hari pukul 02:30 menggunakan akun Windows lokal saat ini. Tugas memanggil `tools/xampp-backup.ps1`, yang menjalankan backup CLI dan menyimpan hasil di `%USERPROFILE%\Documents\SIMS_Backups`. Tugas telah dipicu satu kali secara manual; hasil terakhir memiliki exit code `0`. Periksa **Task Scheduler > Task Scheduler Library > SIMS SMPN4 Backup Harian**, `scheduled-backup.log`, checksum, dan folder backup untuk memastikan eksekusi berikutnya.

Backup manual tambahan tetap dianjurkan sebelum migrasi dan pembaruan. Tugas saat ini tidak menghapus backup lama. Tetapkan retensi harian/mingguan/bulanan berdasarkan ruang simpan dan kebijakan sekolah sebelum mengaktifkan pembersihan otomatis. Periksa hak akses akun Windows terhadap folder backup dan pantau kode keluar non-nol.

Salin backup ke media atau lokasi lain yang aksesnya dibatasi. Satu salinan pada disk yang sama bukan perlindungan memadai terhadap kerusakan perangkat. Untuk data sensitif, gunakan media terenkripsi dan batasi siapa yang dapat membaca atau memulihkannya. Jangan membuat tugas terjadwal sampai akun Windows, lokasi penyimpanan, retensi, dan pemilik notifikasi kegagalan telah ditentukan.

## Batas fitur saat ini

- Backup manual tersedia dari Dashboard Super Admin dan CLI; satu Task Scheduler lokal telah dibuat. Jadwal sistem Windows tidak dipasang otomatis oleh aplikasi.
- Script membuat salinan, tetapi tidak mengenkripsi hasilnya sendiri.
- `restore-test.php` sengaja tidak menghapus database uji atau hasil ekstraksi.
- Jadwal dan pemulihan telah diuji pada komputer ini, tetapi salinan eksternal/offsite belum tersedia dan prosedur operasional sekolah perlu disepakati.

Catat waktu backup, checksum, hasil uji, operator, dan tindakan lanjutan. Jangan menyatakan backup siap dipakai sebelum uji pemulihan berhasil.
