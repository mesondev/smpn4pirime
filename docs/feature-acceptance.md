# Penerimaan Fitur SIMS

Gunakan satu salinan checklist ini untuk setiap modul/rilis. Jangan tandai “Lulus” hanya karena URL bisa dibuka. Simpan catatan lingkungan, peran uji, dan bukti hasil. Jangan menggunakan data pribadi siswa sebagai data uji.

## Informasi penerimaan

- **Modul/fitur:**
- **Versi/perubahan:**
- **Tanggal:**
- **Penguji:**
- **Lingkungan:** XAMPP lokal / staging / produksi
- **PHP dan MySQL/MariaDB:**
- **Status:** Belum diuji / Dalam pengujian / Lulus / Gagal

## Pemeriksaan fungsi

- [ ] Daftar tampil dengan kolom dan relasi yang benar.
- [ ] Pencarian, filter, pagination, hitungan data, dan keadaan kosong bekerja.
- [ ] Detail menampilkan informasi lengkap dengan label manusia, bukan hanya ID.
- [ ] Tambah dan edit tersimpan dengan benar serta menjaga relasi.
- [ ] Hapus/arsip mengikuti kebijakan retensi dan menangani data yang masih direlasikan.
- [ ] Impor menampilkan pratinjau dan validasi sebelum menyimpan, bila tersedia.
- [ ] Ekspor sesuai filter, aman dibuka di spreadsheet, dan tidak memuat kolom rahasia.
- [ ] Pesan berhasil/gagal jelas dan tidak mengungkap SQL atau rahasia.

## Keamanan dan integritas

- [ ] Akses diperiksa di server untuk setiap GET, POST, unduhan, dan ekspor.
- [ ] Form perubahan data memakai token CSRF.
- [ ] Input divalidasi server dan SQL memakai PDO prepared statement.
- [ ] Keluaran teks di-escape; unggahan diperiksa tipe, signature, ukuran, dan lokasi.
- [ ] Perubahan penting dicatat tanpa password, token, atau data pribadi yang tidak diperlukan.
- [ ] Transaksi digunakan bila satu tindakan menyentuh beberapa tabel.
- [ ] Uji relasi/constraint dan kasus duplikat berhasil.

## Antarmuka dan responsivitas

- [ ] Tidak ada konten terpotong pada lebar 360, 390, 768, 1024, dan 1366 px.
- [ ] Tabel dapat dibaca/di-scroll pada ponsel; aksi utama tetap dapat dijangkau.
- [ ] Form satu kolom di layar kecil dan tombol mempunyai target sentuh yang layak.
- [ ] Fokus keyboard, label, kontras, dan nama kontrol bisa dipahami.
- [ ] Tema terang/gelap dan navigasi tidak menutupi konten.

## Pengujian dan pemulihan

- [ ] `php tests/run.php` lulus untuk pemeriksaan yang dapat dijalankan pada lingkungan ini.
- [ ] Sintaks seluruh PHP, JavaScript, dan CSS diperiksa.
- [ ] Skenario peran yang relevan diuji, termasuk akses URL langsung dan POST tanpa izin.
- [ ] Database uji/fixture digunakan untuk alur tambah-edit-arsip/hapus.
- [ ] Backup sebelum migrasi tersedia dan checksum cocok.
- [ ] Uji restore ke database dan folder terpisah berhasil untuk perubahan berisiko.

## Hasil dan tindak lanjut

| Skenario | Peran | Hasil yang diharapkan | Hasil aktual | Status/bukti |
|---|---|---|---|---|
| | | | | |

**Catatan/risiko:**

**Tindak lanjut, penanggung jawab, dan tenggat:**

**Keputusan penerimaan:**
