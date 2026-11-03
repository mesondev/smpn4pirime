# Backup dan uji pemulihan SIMS

Perintah berikut untuk PHP Native yang dijalankan dari **Laragon Terminal**. Backup ditulis ke lokasi privat di luar folder proyek dan document root. Jangan arahkan lokasi backup ke `public/`, `uploads/`, atau folder yang dapat diunduh melalui browser.

## Prasyarat

- Laragon menjalankan MySQL yang digunakan oleh `sims_db`.
- PHP CLI Laragon minimal 8.1 dengan `pdo_mysql` dan `zip` aktif.
- `mysqldump.exe` dan `mysql.exe` tersedia di instalasi MySQL Laragon.
- Akun database mampu membaca struktur/data SIMS; akun yang dipakai `restore-test.php` juga memerlukan izin membuat database lokal.
- Folder backup berada di luar folder proyek dan dapat ditulis oleh akun Windows yang menjalankan Laragon Terminal.

Contoh lokasi untuk instalasi Laragon lokal:

```powershell
$env:SIMS_BACKUP_DIR = 'C:\laragon\data\sims_smpn4_backups'
```

Nama file executable dapat dipilih otomatis dari `C:\laragon\bin\mysql`. Jika MySQL disimpan di lokasi berbeda, atur `SIMS_MYSQLDUMP` dan `SIMS_MYSQL` ke executable versi yang sedang digunakan. Kredensial database dibaca dari `SIMS_DB_HOST`, `SIMS_DB_PORT`, `SIMS_DB_NAME`, `SIMS_DB_USER`, dan `SIMS_DB_PASS`, sama seperti konfigurasi aplikasi. File `.env` tidak dibaca otomatis oleh proyek.

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

Jika proses gagal, status keluar bukan nol dan berkas parsial di folder hasil yang baru dibuat dibersihkan. Script hanya dapat dijalankan melalui CLI dan menolak folder output di dalam proyek.

Konfigurasi rahasia lokal seperti `config/database.php` dan `.env` tidak dimasukkan ke arsip. Simpan salinan konfigurasi yang diperlukan secara terpisah di lokasi privat dan terlindungi; jangan commit atau membagikannya bersama backup data.

## Memeriksa dan menguji pemulihan

Pertama, verifikasi manifest, checksum, dan jalur file tanpa membuat database:

```powershell
php tools/restore-test.php 'C:\laragon\data\sims_smpn4_backups\NAMA_FOLDER_BACKUP' --verify-only
```

Kemudian, uji pemulihan penuh:

```powershell
php tools/restore-test.php 'C:\laragon\data\sims_smpn4_backups\NAMA_FOLDER_BACKUP'
```

Uji penuh hanya diizinkan pada environment lokal dan host database lokal. Script membuat database baru bernama `sims_restore_test_...` serta folder `restore-test-...` di dalam lokasi backup. Script tidak menghapus database yang sudah ada, tidak mengganti `sims_db`, dan mempertahankan hasil gagal agar dapat diperiksa. Setelah admin memeriksa tabel dan file hasil pemulihan, database dan folder uji dapat dibersihkan secara manual sesuai kebijakan sekolah.

Keberhasilan uji menunjukkan dump dapat diimpor, tabel dapat dibaca, arsip dapat diekstrak, dan jalur dalam ZIP aman. Sebelum rilis, lanjutkan dengan menguji aplikasi terhadap salinan database hasil pemulihan memakai konfigurasi lokal terpisah; jangan mengubah konfigurasi aplikasi utama untuk menunjuk ke database uji.

## Jadwal, retensi, dan salinan terpisah

Tentukan jadwal backup bersama penanggung jawab sekolah. Rekomendasi awal: backup database setiap hari, backup file harian atau setelah ada perubahan, serta backup tambahan sebelum migrasi dan pembaruan. Tetapkan retensi harian/mingguan/bulanan berdasarkan ruang simpan dan kebijakan sekolah.

Salin backup ke media atau lokasi lain yang aksesnya dibatasi. Satu salinan pada disk yang sama bukan perlindungan memadai terhadap kerusakan perangkat. Untuk data sensitif, gunakan media terenkripsi dan batasi siapa yang dapat membaca atau memulihkannya. Jangan membuat tugas terjadwal sampai akun Windows, lokasi penyimpanan, retensi, dan pemilik notifikasi kegagalan telah ditentukan.

## Batas fitur saat ini

- Backup dan pemulihan dijalankan manual melalui CLI; belum ada tombol dashboard atau scheduler bawaan.
- Script membuat salinan, tetapi tidak mengenkripsi hasilnya sendiri.
- `restore-test.php` sengaja tidak menghapus database uji atau hasil ekstraksi.
- Uji pemulihan belum membuktikan bahwa salinan eksternal/offsite tersedia atau prosedur operasional sekolah sudah siap.

Catat waktu backup, checksum, hasil uji, operator, dan tindakan lanjutan. Jangan menyatakan backup siap dipakai sebelum uji pemulihan berhasil.
