# Laporan Lengkap Navigasi dan Tata Letak Portal Publik SIMS

**Tanggal pemeriksaan:** 3 November 2026  
**Ruang lingkup:** navigasi utama, dropdown Profil/Akademik/Informasi/PPDB, halaman yang dituju, susunan konten, data dinamis, dan perilaku responsif.  
**Sumber pemeriksaan:** `public/_header.php`, `public/_footer.php`, halaman PHP dalam `public/`, `public/_common.php`, `public/_public_sections.php`, serta stylesheet dan JavaScript portal.

Laporan ini menjelaskan implementasi yang ada. Keberadaan sebuah halaman tidak berarti konten sekolah di dalamnya sudah lengkap atau telah disahkan; banyak daftar mengambil data dari database dan dapat menampilkan pesan keadaan kosong sampai pengelola menerbitkan konten resmi.

## 1. Kerangka tata letak bersama

Urutan tampilan portal pada sebagian besar halaman:

1. **Skip link** “Lewati ke konten utama” untuk pengguna keyboard/pembaca layar.
2. **Bar informasi atas:** ikon media sosial resmi, nomor WhatsApp jika dikonfigurasi, tanggal dan jam zona Asia/Jayapura, serta cuaca Pirime.
3. **Header dan navigasi:** logo/nama sekolah, tombol menu untuk ponsel, menu utama dan dropdown, pencarian, serta tautan masuk SIMS untuk staf.
4. **Area konten utama:** halaman beranda, hero halaman, daftar kartu/tabel, formulir, atau detail sesuai jenis halaman.
5. **Footer:** ringkasan portal, tautan Profil, Akademik, Informasi & Layanan, alamat/kontak, media sosial, dan hak cipta.

CSS portal berada di `assets/public.css`; interaksi menu, slideshow, jam, cuaca, dan elemen publik berada di `assets/public.js`. Pada layar kecil navigasi dapat dibuka lewat tombol menu. Tata letak daftar memakai kartu, kisi yang menyesuaikan lebar layar, atau pembungkus tabel agar konten panjang tidak memotong seluruh halaman.

### Sorotan/slideshow beranda

- Slide berisi label, judul, ringkasan, dan dua tautan tindakan.
- Tombol panah visual dan tombol “Jeda” sudah dihapus sesuai permintaan.
- Ketuk area kiri slideshow untuk slide sebelumnya; area kanan untuk slide berikutnya. Klik pada tautan, tombol indikator, dan kontrol interaktif tidak diambil alih.
- Indikator bulat tetap bisa dipilih. Keyboard panah kiri/kanan bekerja saat area sorotan difokuskan.
- Putar otomatis menghormati reduced motion dan berhenti saat tab tersembunyi.

## 2. Menu Profil

Menu ini berisi halaman identitas, sejarah, kepemimpinan, tenaga sekolah, dan lingkungan sekolah.

| Nama menu | Tujuan | Isi dan susunan halaman |
|---|---|---|
| Tentang sekolah | `profil.php#schoolAboutTitle` | Halaman Profil Sekolah. Bagian pengantar profil, identitas sekolah, visi, misi, tujuan, pengenalan lingkungan, kepala sekolah/guru, dan ringkasan struktur. Data identitas dibaca dari profil sekolah; halaman juga menampilkan catatan sumber untuk data identitas tertentu. |
| Identitas sekolah | `profil.php#schoolIdentityTitle` | Bagian dalam halaman profil yang menampilkan identitas seperti nama, NPSN, alamat/wilayah, jenjang, status, dan informasi lain yang tersedia. Nilai yang belum dikonfirmasi ditandai sebagai belum tersedia/perlu verifikasi, bukan diisi dengan tebakan. |
| Riwayat sekolah | `sejarah.php` | Hero/judul sejarah, isi riwayat sekolah, sumber/tahap peninjauan, dan keadaan kosong saat riwayat resmi belum diterbitkan. Status riwayat saat ini perlu ditinjau oleh sekolah. |
| Visi dan misi | `visi-misi.php` | Tiga bagian utama: Visi, Misi, dan Tujuan. Konten terkait profil sekolah dan mempunyai status publikasi/tinjauan. Draf tidak boleh dianggap sebagai rumusan final. |
| Kepala sekolah | `profil.php#kepala-sekolah` | Bagian profil yang memuat informasi kepala sekolah dari data biodata/profil yang telah disetujui. Nama, sambutan, foto, dan periode jabatan perlu mengikuti data resmi. |
| Guru & tenaga kependidikan | `guru.php` | Hero direktori, kolom pencarian, kartu/potret guru yang disetujui tampil, dan panel keadaan kosong. Hanya informasi yang diizinkan untuk publik yang seharusnya ditampilkan; direktori ini bukan daftar data staf privat. |
| Struktur organisasi | `struktur.php` | Hero struktur, ringkasan tata kelola, pimpinan, guru/tenaga kependidikan, unit layanan, diagram/bagan, dan keterangan ketika relasi jabatan belum tersedia. Data bagan berasal dari konten publik yang diterbitkan. |
| Fasilitas sekolah | `fasilitas.php` | Hero fasilitas, kisi kartu ruang/fasilitas, foto jika tersedia, nama dan uraian. Konten bergantung pada entri portal yang berstatus terbit. |
| Lokasi sekolah | `kontak.php#contactMapTitle` | Bagian lokasi pada halaman Kontak: alamat, peta atau petunjuk arah jika dikonfigurasi. Tautan peta perlu diperiksa agar sesuai lokasi resmi. |

## 3. Menu Akademik

| Nama menu | Tujuan | Isi dan susunan halaman |
|---|---|---|
| Ringkasan akademik | `akademik.php` | Pengantar akademik, program pembelajaran, angka ringkasan yang tersedia, informasi kelas, jadwal, agenda mendatang, serta ekstrakurikuler. Statistik menggunakan data aktif yang tersedia di database. |
| Kurikulum | `kurikulum.php` | Halaman informasi kurikulum. Menampilkan keterangan ketika informasi masih perlu dikonfirmasi. Nama kurikulum, tahun penerapan, dan ringkasan program harus disahkan sekolah sebelum ditampilkan sebagai fakta. |
| Jadwal pelajaran | `jadwal.php` | Filter tahun ajaran/kelas/hari yang tersedia, tabel jadwal, serta keadaan kosong. Pastikan jadwal publik memang boleh dilihat umum; jadwal detail internal tidak otomatis sama dengan jadwal publik. |
| Peserta didik | `akademik.php#peserta-didik` | Bagian ringkasan peserta didik; menyajikan agregat seperlunya, tidak seharusnya membuka identitas atau daftar pribadi siswa. |
| Rombongan belajar | `akademik.php#rombongan-belajar` | Bagian informasi kelas/rombongan belajar dan jumlah ringkasan jika sumber data tersedia. Nama dan rincian yang dapat mengidentifikasi peserta didik tidak perlu dipublikasikan. |
| Kalender pendidikan | `agenda.php` | Kalender/daftar agenda akademik dengan filter yang disediakan, tanggal, judul kegiatan, dan keterangan. Isi diambil dari kalender akademik yang diterbitkan. |
| Ekstrakurikuler | `ekstrakurikuler.php` | Daftar kegiatan ekstrakurikuler berbentuk kartu berisi nama, deskripsi, jadwal/pembina bila tersedia, dan gambar opsional. Tampil berdasarkan konten publik aktif. |

## 4. Menu Informasi

| Nama menu | Tujuan | Isi dan susunan halaman |
|---|---|---|
| Berita sekolah | `berita.php` | Hero berita, pencarian/kategori bila tersedia, kisi berita terbit, ringkasan, tanggal, dan tautan baca selengkapnya. |
| Pengumuman | `pengumuman.php` | Pencarian pengumuman, daftar pengumuman terbit dalam urutan waktu, tanggal tayang, isi/ringkasan, dan keadaan kosong. Periode tayang menentukan apakah pengumuman terlihat. |
| Agenda kegiatan | `agenda.php` | Kalender kegiatan sekolah yang sama dengan halaman kalender pendidikan. Tanggal dan isi harus berasal dari agenda resmi; pada navigasi saat ini kedua tautan mengarah ke halaman yang sama. |
| Prestasi sekolah | `prestasi.php` | Daftar prestasi yang memenuhi status terbit, bukti terverifikasi, dan izin publikasi. Nama siswa/foto hanya boleh tampil dengan persetujuan yang diperlukan. |

Halaman detail terkait informasi juga tersedia melalui tautan item:

- `detail_berita.php` / `detail-berita.php`: detail berita. Kedua nama file merupakan route legacy/alias yang perlu dijaga konsistensinya.
- `detail_pengumuman.php`: detail pengumuman.

## 5. Tautan utama di luar dropdown

| Menu | Tujuan | Isi dan fungsi |
|---|---|---|
| Beranda | `index.php` | Slideshow sorotan, ringkasan profil, pengumuman, agenda, direktori guru, statistik akademik, galeri, dan ajakan menuju layanan publik. Konten dinamis bergantung pada data yang diterbitkan. |
| Galeri | `galeri.php` | Kisi foto kegiatan dan keterangan jika tersedia. Pastikan foto memiliki izin publikasi dan tidak memuat informasi siswa yang seharusnya privat. |
| Unduhan | `unduhan.php` | Daftar dokumen PDF yang dipublikasikan, deskripsi, ukuran/tautan unduh melalui endpoint pemeriksa status. Jangan masukkan dokumen pribadi siswa/PPDB. |
| Kontak | `kontak.php` | Informasi kontak, alamat/lokasi sekolah, peta jika dikonfigurasi, serta formulir pesan. Formulir memiliki validasi dan perlindungan spam; isi pesan masuk bersifat privat untuk petugas. |
| Cari | `pencarian.php` | Form pencarian portal dan daftar hasil yang diizinkan untuk publik. Halaman pencarian disetel noindex. |
| SIMS | `../login.php` | Tautan ke login untuk staf/pengguna aplikasi; bukan halaman konten publik. |

## 6. Menu PPDB / SPMB

| Nama menu | Tujuan | Isi dan susunan halaman |
|---|---|---|
| Informasi dan jadwal | `ppdb.php#informasi-ppdb` | Periode aktif, tanggal, ringkasan prosedur, dan informasi penerimaan. Periode baru hanya aktif bila status serta rentang tanggal sesuai. |
| Persyaratan | `ppdb.php#persyaratan` | Daftar syarat resmi dari periode penerimaan. Jika belum ada periode atau syarat belum disahkan, halaman memberi keterangan belum tersedia. |
| Isi formulir | `ppdb.php#formulir` | Formulir permohonan awal berisi data calon siswa dan wali yang diperlukan. Saat berhasil, pendaftar memperoleh nomor dan kunci status; formulir tidak menerima dokumen sensitif. |
| Cek status pendaftaran | `cek_pendaftaran.php` | Form nomor pendaftaran dan kunci status untuk memeriksa permohonan sendiri. Halaman noindex; hasil tidak boleh mengungkap daftar pendaftar lain. |
| Hasil seleksi | `hasil-seleksi.php` | Pemeriksaan hasil secara pribadi berdasarkan kredensial/identitas yang ditentukan halaman. Hasil seleksi hanya tampil sesuai status yang sudah diterbitkan sekolah. |

Jadwal, persyaratan, kuota, hasil seleksi, dan keputusan penerimaan adalah kebijakan sekolah. Pengembang tidak mengisi nilai tersebut dengan data asumsi.

## 7. Halaman yang ditautkan dari footer

Footer menambahkan tautan **Laporan Pengembangan** (`laporan_pengembangan.php`) dan **Kebijakan Privasi** (`privasi.php`). Kebijakan privasi menjelaskan data pengunjung, permohonan PPDB, penggunaan/keamanan, serta kontak. `404.php` menyediakan halaman tidak ditemukan dan jalur kembali ke portal.

## 8. Perilaku data dan batas kelengkapan konten

- Portal memakai profil sekolah dan fungsi query aman di `public/_common.php`.
- Daftar berita, pengumuman, guru, agenda, fasilitas, prestasi, struktur, galeri, unduhan, jadwal publik, dan ekstrakurikuler dapat bergantung pada data berstatus terbit.
- Panel “belum tersedia” berarti konten tidak ada atau belum disetujui; jangan menggantinya dengan data rekaan.
- Status akreditasi, visi/misi/tujuan, sejarah, kurikulum, kepala sekolah, nomor kontak, peta, foto, dan media sosial perlu ditinjau pemilik data sekolah.
- Data dinamis publik harus memakai izin publikasi dan menyaring data sensitif. Data internal siswa, nilai, absensi personal, akun, pesan masuk, dan dokumen pendaftaran tidak menjadi konten portal.

## 9. Pemeriksaan tautan dan tindak lanjut

Pada pemeriksaan source, seluruh tautan halaman dan anchor dropdown di `public/_header.php` merujuk ke file/ID yang tersedia. Dua halaman agenda pada menu berbeda sengaja berbagi `agenda.php`; halaman kalender legacy `kalender.php` masih ada tetapi bukan target dropdown utama.

Sebelum menyatakan portal siap dipublikasikan, lakukan:

1. Periksa satu per satu halaman pada ponsel, tablet, dan desktop; khususnya dropdown, formulir, kartu panjang, dan tabel jadwal.
2. Uji tautan media sosial, WhatsApp, peta, cuaca, unduhan, pencarian, dan detail berita/pengumuman.
3. Minta sekolah menyetujui isi identitas, visi/misi, sejarah, kurikulum, kontak, jadwal, PPDB, foto, dan data personel.
4. Pastikan konten siswa mempunyai izin publikasi dan dokumen sensitif tidak dapat diunduh dari portal.
5. Periksa status tayang serta keadaan kosong setelah perubahan konten, lalu jalankan tes HTTP dan regresi.

Laporan ini adalah inventaris atas susunan kode dan sumber datanya, bukan pengesahan isi faktual sekolah atau sertifikasi aksesibilitas seluruh perangkat.

## 10. Penyempurnaan isi dan responsivitas

Penyempurnaan berikut mempertahankan nama menu dan URL navigasi yang sudah ada di `public/_header.php`:

- Beranda memakai foto galeri, data guru, fasilitas, pimpinan, berita, agenda, dan statistik yang benar-benar tersedia serta layak tampil. Ilustrasi contoh tidak dipakai sebagai dokumentasi sekolah.
- Beranda memiliki ringkasan profil singkat setelah sorotan, dengan identitas dan lokasi dari data terstruktur sebelum statistik sekolah.
- Profil disusun dengan urutan pengenalan, identitas, kepemimpinan, sejarah, visi/misi/tujuan, lalu struktur dan fasilitas. Status rancangan/perlu verifikasi tetap terlihat. Sambutan hanya ditampilkan sebagai resmi bila skema memiliki `sambutan_status='resmi'`; instalasi yang belum memiliki kolom persetujuan menampilkan keterangan, bukan naskah yang belum jelas statusnya.
- Berita dan pengumuman memiliki pencarian, filter relevan, ringkasan jumlah hasil, serta pagination yang mempertahankan pilihan filter.
- Detail berita menampilkan tanggal terbit/perubahan dan berita terkait yang telah terbit.
- Unduhan menampilkan kategori, format, ukuran, dan tanggal bila tersedia. Tautan unduh hanya muncul untuk PDF yang berkasnya lolos pemeriksaan lokasi dan nama file.
- Guru, galeri, fasilitas, dan peta tidak lagi menampilkan foto/peta contoh saat data publik yang valid belum tersedia.
- Alamat beranda, kontak, dan footer dibentuk dari kolom alamat terstruktur sehingga teks warisan yang memuat placeholder seperti “tidak diisi” tidak ikut ditampilkan.
- Jadwal dapat difilter menurut tahun ajaran, kelas, dan hari. Statistik akademik menyajikan agregat dan keterangan sumber.
- Semua tujuan pada dropdown Profil memakai susunan hero, breadcrumb, konten, dan keadaan data yang seragam serta responsif. Halaman Sejarah juga menerima status resmi `resmi` (dan nilai legacy `terverifikasi`), sehingga naskah yang sudah disahkan tidak salah disembunyikan.
- Halaman Visi dan Misi memisahkan Visi, Misi, dan Tujuan serta hanya menampilkan isi yang statusnya resmi. Direktori guru memakai kartu tanpa style inline, hasil pencarian yang jelas, dan pagination dengan status halaman aktif.
- Formulir PPDB meminta konfirmasi sebelum pengiriman. Tata letak filter, kartu, metadata, dan daftar memakai aturan responsif pada stylesheet portal.

Pemeriksaan terakhir: beranda dan profil sempat berhasil dibuka di browser lokal XAMPP sebelum MySQL berhenti. Runner pengujian statis/regresi terakhir menghasilkan 18 lulus, 0 gagal, dan 2 dilewati; lint PHP (87 file), pemeriksaan JavaScript (2 file), dan keseimbangan CSS (10 file) lulus. Integrasi HTTP dan database tidak lolos pada percobaan terakhir karena layanan lokal tidak aktif. Log `C:\xampp\mysql\data\mysql_error.log` mencatat `ibdata1 must be writable` saat MariaDB mencoba mulai pada 18:12:38; proses MySQL tidak berjalan pada pemeriksaan terakhir. Perlu jalankan panel XAMPP dengan hak yang sesuai dan periksa izin/kunci file MySQL sebelum verifikasi runtime ulang. Navigasi `public/_header.php` tidak diubah.
