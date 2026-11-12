# Catatan perubahan SIMS

## 3 November 2026 — Penyelesaian butir evaluasi revisi 2.0

### Ditambahkan

- Laporan ringkas Tenaga Kependidikan di `reports.php`, menampilkan agregat berdasarkan jabatan dan status.
- Filter jabatan dan status, pratinjau cetak, dan ekspor CSV untuk laporan staf.
- Tautan laporan pada halaman modul staf.
- Pengujian statis untuk izin route, agregasi, tautan, dan perlindungan CSV.

### Diperbaiki

- Ekspor CSV laporan menetralkan sel yang dapat ditafsirkan sebagai formula spreadsheet.
- Halaman laporan staf hanya memuat tabel `staff`; query tabel tahun ajaran/kelas tidak dijalankan untuk laporan ini.

### Dipertahankan

- PHP Native, PDO, MySQL/MariaDB, HTML, CSS, JavaScript, dan integrasi tampilan SIMS yang sudah ada.
- Data staf tetap terpisah dari data guru. Tidak ada perubahan atau pemindahan data sekolah.

### Pengujian

- `SIMS_TEST_HTTP=1 SIMS_TEST_DB=1 php tests/run.php`: 20 lulus, 0 gagal, 0 dilewati setelah Apache dan MariaDB XAMPP dijalankan.
- 87 berkas PHP lolos `php -l`; 2 JS lolos `node --check`; 10 CSS lolos pemeriksaan struktur.
- Route publik dan route terproteksi lulus smoke test, koneksi database lulus, dan checksum migrasi cocok.

### Tindak lanjut operasional

- Uji laporan staf secara manual di browser dengan akun yang memiliki dan tidak memiliki izin modul staf; smoke test otomatis belum menguji halaman ini dalam sesi login.

## Perubahan tampilan portal — tombol jeda slideshow

- Tombol **Jeda** pada hero portal publik dihapus sesuai permintaan.
- Kode JavaScript dan CSS khusus tombol tersebut dibersihkan; slideshow tetap memiliki navigasi slide dan tetap menghormati preferensi reduced motion serta visibilitas tab.
- Ikon panah kiri/kanan visual dihapus. Ketuk area kiri/kanan slideshow mengubah slide; tautan dan indikator tidak tertangkap oleh aksi tersebut, dan keyboard panah kiri/kanan tersedia saat hero difokuskan.
- Tes otomatis diperbarui untuk memastikan tombol jeda tidak kembali muncul dan konten panjang tidak terpotong.
- Uji laporan staf di browser dengan akun yang memiliki dan tidak memiliki izin modul staf.
- Lanjutkan konfigurasi SMTP, backup terpisah/offsite, kebijakan PPDB dan retensi, HTTPS/domain resmi, serta penerimaan sekolah setelah keputusan pemilik sistem tersedia.
