# Status audit dan pengembangan SIMS

Pembaruan: 3 November 2026. Dokumen ini membedakan fitur yang sudah tersedia, perubahan kode yang baru disiapkan, dan pekerjaan operasional yang belum dapat dianggap selesai. Semua fitur mengikuti arsitektur PHP Native, HTML, CSS, JavaScript, dan PDO; tidak memerlukan Composer atau framework.

## Yang sudah dikerjakan pada perubahan ini

- Modul **Tenaga Kependidikan** menggunakan CRUD generik aplikasi, terhubung ke tabel `staff`, memvalidasi izin di sisi server, dan tersedia bagi Admin serta Operator.
- Data staf dipisahkan dari `guru`, sehingga kolom, relasi, dan data guru yang lama tidak dipindah atau diubah.
- Formulir dan tabel mengikuti komponen Bootstrap/layout SIMS yang sudah responsif. Menu Data Master dan menu Administrasi diberi tautan ke modul baru.
- Migrasi `2026_11_04_create_staff.sql` ditambahkan dan dicakup pemeriksaan skema serta checksum di `database/migrate.php`.
- Migrasi `2026_11_04_student_class_history.sql` menambahkan sejarah kelas/tahun ajaran. Pembuatan siswa mencatat kelas awal dan perubahan kelas menyimpan asal/tujuan, tahun ajaran, alasan opsional, aktor, serta waktu dalam transaksi yang sama.
- Panduan backup diperbarui dari instruksi Laragon menjadi XAMPP/PowerShell. Task Scheduler **SIMS SMPN4 Backup Harian** dibuat untuk pukul 02:30 dan telah dipicu uji sukses.
- Hak modul dinamis menggunakan tabel `roles`, `permissions`, dan `role_permissions`; izin awal mengikuti kebijakan lama, perubahan dikelola melalui halaman Super Admin, dan route diperiksa di server.
- `audit_logs` menambahkan snapshot old/new yang difilter, aktor, waktu, IP, dan user-agent. Balasan kontak masuk ke antrean email transaksi dan diproses worker CLI dengan pengunci, backoff, dan batas percobaan.

## Cara menjalankan modul yang sudah ditambahkan

1. Pastikan MySQL XAMPP stabil, `sims_db` dapat dibaca, dan backup di luar document root sudah dibuat serta diverifikasi.
2. Jika ada migrasi baru, jalankan `C:\xampp\php\php.exe database\migrate.php` dari folder proyek setelah backup baru.
3. Login Super Admin, tinjau hak akses di `permissions.php`, lalu buka `index.php?module=staf` untuk mengelola tenaga kependidikan.
4. Periksa perubahan melalui `audit.php`. Setelah relay email dikonfigurasi, jalankan `C:\xampp\php\php.exe tools\process-mail-queue.php` untuk mengirim balasan antrean.

Migrasi staf, riwayat kelas, RBAC, audit, serta antrean email sudah dijalankan setelah MariaDB stabil dan backup terverifikasi. Runner melewati skema lama yang cocok dan mencatat checksum migrasi baru.

## Status fitur dan pekerjaan berikutnya

| Area | Kondisi kode saat ini | Pekerjaan yang masih diperlukan |
|---|---|---|
| XAMPP/MySQL | MariaDB 10.4.32 dijalankan dan `mysqladmin ping` berhasil; login database dan database `sims_db` tersedia. PHP XAMPP mengaktifkan `zip`. | Proses MariaDB dijalankan untuk sesi Windows saat ini; setelah restart Windows, pastikan start dari XAMPP Control Panel. Periksa log bila gagal start kembali. |
| Migrasi | Runner CLI mencatat status dan SHA-256; seluruh file migrasi yang ada tercatat applied/reconciled pada pemeriksaan terakhir. | Jalankan backup dan uji checksum sebelum migrasi berikutnya; jangan mengedit file migrasi yang sudah tercatat. |
| Tenaga kependidikan | CRUD responsif, filter jabatan/status, impor CSV dua tahap dengan pratinjau/konfirmasi, deteksi NIP ganda, ekspor CSV, izin Admin/Operator dan migrasi `staff` tersedia. | Isi hanya data resmi staf. Laporan analitik khusus masih pengembangan lanjutan; tidak ada pemindahan otomatis dari tabel guru. |
| Hak akses | RBAC per modul aktif. Baseline mengikuti kebijakan lama, Admin terkunci penuh, dan halaman `permissions.php` mengelola peran selain Admin. | Uji ulang role dan akses API/unduhan bila sekolah mengubah grants. Sebagian halaman khusus tetap memiliki guard role lama untuk fungsi sensitif. |
| Audit perubahan | `log_aktivitas` tetap aktif; `audit_logs`/`audit.php` menyimpan snapshot aman untuk CRUD generik baru dan konteks permintaan. | Uji retensi dan akses sekolah. Field pribadi/rahasia disaring; tidak ada histori retroaktif sebelum migrasi. |
| Riwayat kelas siswa | Migrasi diterapkan; siswa baru mendapat catatan awal, perubahan kelas menulis asal/tujuan dalam transaksi yang sama. Halaman detail siswa menampilkan ringkasan; halaman riwayat menyediakan filter tahun/kelas, pagination, cetak, dan ekspor CSV. | Histori siswa lama tidak direkayasa. Isi hanya dari dokumen administrasi resmi. |
| Backup | Backup penuh serta checksum terverifikasi; restore test berhasil pada database/folder uji. Task Scheduler menjalankan backup harian pukul 02:30 dengan akun Windows saat ini. | Hasil disimpan di `Documents\SIMS_Backups` pada disk yang sama; enkripsi, offsite, dan retensi belum dikonfigurasi. Pertimbangkan media terpisah dan retensi berdasarkan kebijakan sekolah. |
| PPDB | Modul pendaftaran dan pengelolaan status tersedia; ada riwayat perubahan status. | Verifikasi migrasi/schema dengan DB; validasi aturan kuota, periode, hasil, daftar ulang, notifikasi, dan arsip bersama sekolah sebelum menetapkan kebijakan yang berdampak pada pendaftar. Upload dokumen pribadi belum boleh diaktifkan tanpa penyimpanan privat dan aturan akses/retensi. |
| Pesan/email | Balasan web masuk ke `outbound_email_queue`; worker CLI retry/backoff dan menyinkronkan status UI. Tidak mengirim email di request. | Relay XAMPP, alamat pengirim sekolah, dan kredensial SMTP belum tersedia. Jadwalkan worker tiap 5 menit setelah relay diuji. Implementasi tetap memakai PHP Native `mail()` dan tidak menambah Composer. |
| Impor guru | CSV memiliki pratinjau terikat sesi, validasi per baris, deteksi NIP ganda, batas ukuran/baris, konfirmasi, transaksi, dan pencatatan aktivitas; XLSX dipertahankan sebagai template, bukan parser berdependency. | Uji alur unggah/konfirmasi pada database uji dengan file valid, duplikat, dan rusak. |
| Laporan | CSV dan print browser tersedia tanpa pustaka Composer. | Tentukan format resmi sekolah, filter/periode, pembatasan akses dan uji hasil. PDF/XLSX server-side belum tersedia; pilih solusi native yang layak atau lanjutkan CSV/print browser. |
| Portal publik | Halaman dan modul konten publik sudah ada, dengan sebagian kontrol review, status, dan izin publikasi. | Isi hanya data resmi, verifikasi foto/izin, uji keyboard/kontras/mobile, dan atur domain produksi/canonical/robots/sitemap setelah domain resmi tersedia. |
| Login/keamanan | Username tetap menjadi identitas login; helper sesi, CSRF dan escape HTML tersedia. | Konfigurasikan HTTPS produksi, pembatasan percobaan login, akun DB berhak minimum, backup rahasia, serta uji akses peran dan unggahan di lingkungan lokal. Email tidak boleh otomatis diperlakukan sebagai akun terverifikasi. |

## Urutan penyelesaian yang aman

1. Pastikan XAMPP dan database stabil; buat backup lalu lakukan verifikasi dan pemulihan uji ke database baru.
2. Jalankan `tests/run.php` dengan HTTP dan DB wajib setelah perubahan kode/migrasi.
3. Uji manual sesi login per peran, CSRF, CRUD staf, perubahan kelas, pengaturan hak akses, audit, impor, laporan, PPDB, dan portal.
4. Siapkan histori siswa lama hanya jika tersedia dokumen resmi sumbernya; jangan mengarang riwayat.
5. Konfigurasi backup terjadwal dan email setelah pemilik, relay, lokasi, retensi, ACL, serta prosedur pemulihan sekolah disetujui.
6. Sebelum produksi, lengkapi data resmi sekolah, domain/HTTPS, pengujian aksesibilitas, dan simulasi restore berkala.

## Catatan batas verifikasi

Terakhir diverifikasi pada audit sebelumnya: suite penuh 18 lulus, 0 gagal, 0 dilewati; dashboard login lokal tampil; seluruh route publik/terproteksi lolos; migration ledger/checksum bersih; restore test database dan berkas berhasil; worker email kosong berjalan tanpa mengirim pesan; Task Scheduler backup terpicu dengan exit code 0. Hasil tersebut adalah catatan historis dan bukan pengganti pengujian ulang setelah kode berubah. Belum ada data resmi staf baru. Relay email, enkripsi/offsite/retensi backup, domain/HTTPS produksi, serta verifikasi data resmi tetap membutuhkan konfigurasi atau keputusan sekolah.

## Pembaruan kode setelah revisi dokumen 2.0

- Ditambahkan `docs/module-inventory.md`, `docs/database-relationships.md`, dan template `docs/feature-acceptance.md` berdasarkan struktur source dan migrasi aktual.
- Halaman detail CRUD generik menampilkan semua field konfigurasi modul, tidak hanya kolom ringkas yang dipakai di tabel.
- Ditambahkan halaman riwayat kelas siswa dengan filter, pagination, cetak, dan ekspor CSV. Tidak ada histori lama yang direkayasa.
- Impor Guru dan Staf sekarang menggunakan pratinjau dan konfirmasi sebelum penyimpanan; impor staf dilengkapi validasi, deteksi NIP ganda, serta ekspor/filter.
- Pemeriksaan sintaks saat pembaruan: 84 file PHP, 2 JavaScript, dan 10 CSS lulus. Pemeriksaan otomatis HTTP/database dari proses CLI terhalang pembatasan akses socket lingkungan; lakukan pengujian ulang dari XAMPP/PowerShell lokal yang dapat mengakses layanan.
- Relay SMTP, lokasi backup eksternal, enkripsi/retensi, kebijakan operasional PPDB, data konten resmi, domain/HTTPS, dan uji lintas perangkat masih memerlukan konfigurasi atau persetujuan operasional sekolah.

## Evaluasi lanjutan berdasarkan dokumen rencana revisi 2.0

- Laporan khusus Tenaga Kependidikan ditambahkan ke `reports.php`. Data yang ditampilkan hanya agregat jumlah menurut jabatan dan status, dengan filter jabatan/status, cetak, dan ekspor CSV. Route laporan memeriksa izin modul `staf` di server.
- Ekspor CSV pada laporan umum sekarang memberi awalan aman pada nilai yang berpotensi dianggap formula oleh aplikasi spreadsheet.
- Apache dan MariaDB XAMPP yang semula berhenti berhasil dijalankan kembali. Setelah itu `SIMS_TEST_HTTP=1 SIMS_TEST_DB=1 php tests/run.php` menghasilkan 20 lulus, 0 gagal, 0 dilewati. Route publik dan route terproteksi memberi HTTP 200 tanpa galat PHP; koneksi database serta checksum seluruh migrasi sesuai.
- Pemeriksaan mencakup 87 berkas PHP, 2 JS, dan 10 CSS. Tes route halaman terproteksi memeriksa perilaku pengunjung anonim; pemeriksaan ini tidak menggantikan uji alur CRUD terautentikasi per peran di browser.
- Fitur dan dokumentasi tidak menetapkan sendiri kebijakan penilaian, PPDB, retensi data, akun SMTP, enkripsi/lokasi backup luar, atau isi resmi sekolah. Hal tersebut tetap perlu keputusan dan konfigurasi pemilik sistem.
