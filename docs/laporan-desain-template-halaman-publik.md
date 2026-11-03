# Laporan Desain Halaman Publik dan Spesifikasi Template

## 1. Tujuan dan ruang lingkup

Dokumen ini merangkum halaman publik SMP Negeri 4 Pirime yang ada di proyek serta menerjemahkan pola visual pada gambar referensi menjadi spesifikasi template sekolah. Gambar menunjukkan homepage kedai kopi: header navigasi tipis, hero foto besar dengan ajakan utama, kartu produk, panel cerita, video, CTA, lalu footer gelap. Elemen yang dapat diadaptasi adalah hierarki, ritme, grid, kartu, kontras, dan penempatan CTA. Nama, foto, produk, menu, harga, serta klaim kedai kopi tidak dipakai untuk situs sekolah.

**Status pekerjaan:** laporan dan spesifikasi template; belum ada perubahan pada kode atau tampilan situs.

## 2. Ringkasan kondisi halaman publik saat ini

Halaman beranda dirender oleh `public/index.php`, memakai header bersama `public/_header.php`, footer bersama `public/_footer.php`, gaya `assets/public.css`, dan interaksi `assets/public.js`. Halaman menggunakan bahasa Indonesia, identitas SMP Negeri 4 Pirime, palet biru, tipografi DM Sans dan Manrope, Bootstrap Icons, menu responsif, serta konten yang bersumber dari database.

### Susunan aktual beranda

1. **Bar informasi atas** — tautan media sosial, WhatsApp, tanggal/jam zona Asia/Jayapura, dan cuaca Pirime.
2. **Header dan navigasi** — logo/nama sekolah; Beranda; menu Profil, Akademik, Informasi, PPDB/SPMB; Galeri; Unduhan; pencarian dan navigasi mobile.
3. **Hero slideshow** — lima pesan bergantian tentang sekolah, belajar, informasi, agenda, dan ajakan menghubungi; masing-masing memiliki label, judul, deskripsi, dua tautan, ikon, panah, titik navigasi, dan progress.
4. **Ringkasan statistik** — guru aktif, siswa aktif, kelas tahun ajaran aktif, pengumuman aktif. Nilai kosong ditampilkan sebagai tanda —.
5. **Jalan pintas layanan** — Profil, Pengumuman, Agenda, Guru, Akademik, Galeri.
6. **Program dan layanan** — Ekstrakurikuler, Prestasi terverifikasi/berizin, PPDB/SPMB, dan Pusat Unduhan.
7. **Sambutan dan pengumuman** — panel sambutan sekolah berdampingan dengan tiga pengumuman terbaru.
8. **Berita dan kegiatan** — maksimal tiga kartu berita dengan kategori, tanggal, gambar jika tersedia, ringkasan, dan tautan detail.
9. **Agenda terdekat** — daftar acara mendatang yang ditandai untuk publik.
10. **Galeri pembelajaran** — dokumentasi yang dipublikasikan dan gambar ilustrasi yang diberi label jelas.
11. **Kontak/lokasi** — alamat, CTA lihat peta, dan telepon.
12. **Footer** — identitas dan ringkasan portal, tautan sosial, kelompok tautan Profil/Akademik/Informasi & Layanan, kontak, hak cipta, dan catatan publikasi.

Jika data belum dipublikasikan, beranda menampilkan empty state/teks penjelas. Ini adalah perilaku yang perlu dipertahankan agar situs tidak memberi kesan data palsu atau kosong tanpa konteks.

## 3. Pembacaan visual gambar referensi

Gambar referensi adalah screenshot ponsel dari homepage kedai kopi dengan lebar konten desktop yang diperkecil di tengah layar. Urutan yang terlihat:

- **Pita atas tipis** untuk pengumuman/jam operasional dan kontak.
- **Navigasi putih** dengan merek di kiri, menu di tengah, dan tombol tindakan di kanan.
- **Hero foto lebar** berlapis biru gelap, teks besar di kiri, dua tombol; kartu informasi/jam buka mengambang di kanan.
- **Bar manfaat singkat** setelah hero, berisi tiga nilai jual dalam kolom.
- **Katalog favorit** dengan filter kategori dan kartu foto, nama, keterangan, harga, tombol.
- **Panel cerita/merek** dengan copy di kiri dan angka/infografik di kanan.
- **Galeri/video** berbentuk kartu media.
- **CTA reservasi/pertanyaan** lebar, berwarna biru kuat.
- **Footer navy** dengan merek, navigasi ringkas, kontak, media sosial, dan copyright.

Rasa visualnya: editorial, bersih, banyak ruang putih, latar biru muda, sudut kartu membulat, aksen navy, dan satu warna CTA yang kuat. Pada screenshot ini teks halus tidak selalu terbaca; analisis berfokus pada struktur dan tampilan, bukan menebak copy kecil.

## 4. Pemetaan referensi ke portal sekolah

| Pola dari referensi | Padanan sekolah yang disarankan | Keputusan |
|---|---|---|
| Hero foto kedai + kartu jam buka | Foto lingkungan/kegiatan sekolah yang sah untuk dipublikasikan + kartu “Informasi sekolah” atau “PPDB sedang dibuka” | Adaptasi; jangan memakai foto siswa tanpa izin |
| Tiga nilai jual | Tiga layanan/nilai sekolah: pembelajaran, karakter, kegiatan | Gunakan hanya jika sudah disahkan sekolah |
| Katalog produk dan harga | Kartu layanan publik/shortcut: profil, akademik, PPDB, pengumuman | Jangan membuat katalog semu; gunakan tautan informatif |
| Cerita kedai + angka | Sambutan kepala sekolah + statistik sekolah yang terverifikasi | Pertahankan sumber data dan privasi |
| Video produk | Berita, dokumentasi kegiatan, atau galeri resmi | Beri tanggal/keterangan dan izin publikasi |
| Reservasi/CTA | “Lihat informasi PPDB”, “Hubungi sekolah”, atau “Lihat lokasi” | CTA bergantung status layanan aktual |
| Footer bisnis | Footer sekolah dengan kelompok profil, akademik, layanan, kontak | Gunakan kontak resmi dan tautan aktif |

Beranda proyek saat ini sudah memiliki padanan yang cukup lengkap. Perbedaan terbesarnya adalah halaman sekolah menampilkan lebih banyak navigasi akademik dan informasi resmi, sedangkan screenshot bisnis memakai satu panel cerita/angka dan katalog produk. Desain serupa sebaiknya mengambil **sistem visual**, bukan menghapus kedalaman informasi sekolah.

## 5. Spesifikasi template desain lengkap

### 5.1 Arah visual

- **Karakter:** resmi, ramah keluarga, terpercaya, ringan, dan mudah dipindai.
- **Palet inti saat ini:** navy `#123b66`, biru `#1d65a6`, latar biru pucat `#eaf4ff`, teks `#182b3f`, teks sekunder `#61748a`, garis `#e3ebf3`, latar terang `#f6f9fc` dan putih.
- **Tipografi:** DM Sans untuk isi; Manrope untuk judul dan identitas.
- **Bentuk:** kartu dan tombol bersudut membulat konsisten; bayangan halus; border tipis; gunakan latar soft sebagai pergantian antarbagian.
- **Konten foto:** foto sekolah asli yang sudah disetujui. Jika memakai ilustrasi, beri label “Ilustrasi”. Jangan membuat placeholder seolah dokumentasi faktual.

### 5.2 Grid dan ukuran

- Gunakan satu container tengah dengan lebar maksimum sekitar 1.160–1.320 px sesuai breakpoint stylesheet, dan gutter konsisten.
- Desktop: 12 kolom konseptual; konten utama sekitar 7–8 kolom dan panel samping 4–5 kolom.
- Kartu layanan/berita: tiga kolom di desktop, dua di tablet, satu di ponsel. Kartu statistik mengikuti ruang, lalu menumpuk pada layar sangat sempit.
- Ruang bagian desktop sekitar 42–60 px vertikal; ponsel sekitar 38 px. Jaga jarak antarjudul, deskripsi, dan grid agar ritmenya seragam.
- Rasio foto kartu berita/galeri 16:10 atau crop konsisten; hero gunakan crop lebar dengan overlay yang menjaga kontras teks.

### 5.3 Header

- Bar atas boleh memuat sosial, telepon/WhatsApp, tanggal dan cuaca jika datanya aktif dan bermanfaat.
- Header sticky berwarna putih/translusen dengan blur ringan, logo sekolah, nama, label portal resmi, navigasi dan pencarian.
- Menu desktop dikelompokkan sesuai tugas pengguna. Di ponsel berubah ke tombol menu; dropdown harus bisa digunakan dengan sentuhan dan keyboard.
- Beri state aktif yang jelas, fokus keyboard, label aksesibel, dan tautan “Lewati ke konten utama”.

### 5.4 Hero beranda

- Tinggi desktop sekitar 430–560 px; ponsel sekitar 500–600 px jika slideshow dipertahankan.
- Background berupa foto sekolah resmi atau visual abstrak sekolah. Tambahkan overlay navy/gradient untuk menjaga keterbacaan.
- Komposisi: label kecil, satu H1 singkat, deskripsi 1–3 baris, CTA primer dan sekunder.
- Area kanan pada desktop dapat menampung panel kecil: status PPDB, alamat/jam layanan yang terkonfirmasi, atau angka statistik. Pada ponsel panel menjadi susunan vertikal atau hilang bila mengganggu.
- Batasi 3–5 slide; sediakan kontrol manual, status aktif, tombol berlabel, jeda/dukungan reduced motion. Hindari pergantian terlalu cepat.
- CTA contoh: “Kenali sekolah”, “Lihat PPDB”, “Baca pengumuman”. Tampilkan CTA PPDB hanya sesuai periode resmi.

### 5.5 Bagian isi beranda

1. **Strip manfaat/layanan:** tiga item pendek dengan ikon dan deskripsi yang faktual.
2. **Sorotan layanan:** kartu profil, akademik, kalender, direktori guru, galeri, dan dokumen. Gunakan ikon seragam.
3. **Program utama:** kegiatan/ekstrakurikuler, prestasi, PPDB, unduhan. Tampilkan ringkasan singkat, status, dan tautan detail.
4. **Sambutan dan pengumuman:** dua kolom desktop; sambutan tetap ringkas, pengumuman berupa daftar tanggal, judul, cuplikan.
5. **Berita:** tiga kartu yang dapat dibaca sekilas, foto, kategori/tanggal, judul, ringkasan, tautan detail.
6. **Angka/sejarah sekolah:** hanya angka yang jelas sumber dan tanggal acuannya. Hindari statistik yang tidak dapat diperbarui atau dibuktikan.
7. **Agenda:** 3–4 acara terdekat dengan tanggal, label kategori, ringkasan.
8. **Galeri:** 3 foto/kartu; caption serta status dokumentasi. Jangan mengulang foto yang sama untuk mengisi ruang.
9. **CTA kontak:** panel biru kuat dengan pertanyaan/ajakan, kontak resmi, lokasi atau PPDB sebagai tombol.
10. **Footer:** 4–5 kelompok yang ringkas, kontak, sosial, copyright, kebijakan privasi.

Tidak semua blok perlu muncul di setiap halaman. Homepage boleh mengkurasi ringkasan; halaman detail memberi informasi lengkap.

### 5.6 Jenis template halaman publik

- **Landing/beranda:** hero + jalan pintas + ringkasan konten + CTA.
- **Listing berita/pengumuman/prestasi/galeri:** judul halaman, intro, pencarian/filter yang relevan, grid/list, status kosong, pagination bila data bertambah.
- **Detail berita/pengumuman:** breadcrumb, kategori/tanggal, judul, gambar, isi, lampiran bila aman, konten terkait, kembali ke daftar.
- **Halaman profil/visi/sejarah/struktur/fasilitas:** hero halaman sederhana, subnavigasi bila panjang, isi terstruktur, foto dengan caption dan sumber.
- **Akademik/kurikulum/jadwal/agenda:** ringkasan, filter/periode, tabel yang responsif atau kartu tanggal, keterangan versi/tahun ajaran.
- **PPDB/SPMB:** status periode, syarat, tahapan, jadwal, CTA pendaftaran, konfirmasi, serta cek status terpisah. Jangan meminta unggahan dokumen sensitif bila alur resmi tidak mendukungnya.
- **Kontak:** kanal resmi, alamat, jam layanan jika terverifikasi, peta, formulir dengan validasi dan pesan sukses/error.
- **Unduhan:** kategori, nama dokumen, tipe/tanggal, tombol unduh dan penjelasan dokumen.
- **Pencarian/404/privasi:** state yang jelas, tindakan pemulihan, bahasa sederhana.

### 5.7 Komponen dan state wajib

- Tombol primer, sekunder, teks; tautan dengan hover dan fokus yang terlihat.
- Kartu dengan struktur judul, metadata, deskripsi, tindakan; area klik tidak menyesatkan.
- Breadcrumb pada halaman dalam.
- Empty state yang menjelaskan mengapa belum ada konten dan langkah berikutnya.
- Error layanan yang tidak membocorkan detail teknis/database.
- Form dengan label terlihat, validasi server, pesan kesalahan dekat field, status terkirim.
- Filter/search dengan pilihan yang bisa diakses keyboard dan tombol reset.
- Badge status hanya untuk kondisi yang benar-benar berasal dari data.
- Semua gambar punya alt yang sesuai; dekorasi menggunakan alt kosong.

### 5.8 Responsif, aksesibilitas, performa

- Uji lebar 360 px, 390 px, 768 px, 1024 px, dan desktop lebar.
- Tidak boleh ada scroll horizontal; judul panjang, alamat, dan email harus membungkus dengan baik.
- Kontras teks minimal mengikuti WCAG AA; jangan menaruh teks kecil di foto tanpa overlay.
- Gunakan urutan heading yang logis (satu H1 per halaman), landmark header/nav/main/footer, label ARIA seperlunya, fokus keyboard, dan reduced motion.
- Optimalkan ukuran foto, gunakan lazy loading untuk gambar di bawah fold, dimensi eksplisit untuk mengurangi layout shift.
- Jangan memuat efek blur/animasi berat pada elemen konten utama di perangkat rendah.

## 6. Inventaris halaman publik proyek

Halaman yang tersedia di folder `public/` meliputi: Beranda, Profil, Sejarah, Visi-Misi, Struktur, Guru, Fasilitas, Akademik, Kurikulum, Jadwal, Kalender, Agenda, Ekstrakurikuler, Prestasi, PPDB, Cek Pendaftaran, Hasil Seleksi, Berita, Detail Berita, Pengumuman, Detail Pengumuman, Galeri, Unduhan, Kontak, Pencarian, Laporan Pengembangan, Privasi, dan 404. Ada juga endpoint proses untuk formulir/kiriman. Detail konten dan menu yang perlu ditampilkan sebaiknya diselaraskan dengan aturan sekolah dan status data.

## 7. Data dan tata kelola konten

- Publikasikan berita/pengumuman hanya setelah ditinjau, dengan tanggal dan penanggung jawab konten.
- Prestasi siswa perlu verifikasi dan izin publikasi yang sesuai.
- Foto peserta didik perlu izin; beri caption dan konteks yang benar.
- Statistik harus punya sumber dan waktu pembaruan.
- Jadwal akademik yang ditampilkan untuk publik harus ditinjau agar tidak membocorkan informasi internal.
- PPDB hanya tampil aktif pada periode resmi; persyaratan dan tanggal harus konsisten dengan pengumuman sekolah.
- Dokumen publik hanya dokumen resmi yang memang boleh diunduh; jangan meletakkan dokumen pribadi.
- Tautan sosial, nomor telepon, email, alamat, dan jam layanan perlu diverifikasi berkala.

## 8. Prioritas penerapan jika desain akan dikembangkan

1. Tetapkan foto hero, judul utama, CTA, dan konten yang benar-benar disahkan.
2. Tentukan apakah ingin mempertahankan slideshow atau menjadikan satu hero statis dengan panel informasi di kanan seperti gambar.
3. Samakan proporsi kartu, border, radius, warna latar, dan spacing pada seluruh halaman publik.
4. Audit responsif dan aksesibilitas untuk header, hero, grid, tabel, form, footer.
5. Audit semua aset visual dan izin publikasi serta keadaan tanpa data.
6. Tinjau semua link, tanggal, angka, status PPDB, dan kontak sebelum rilis.

## 9. File sumber yang ditinjau

- `public/index.php` — susunan dan sumber konten beranda.
- `public/_header.php` — metadata, bar atas, identitas, navigasi.
- `public/_footer.php` — navigasi dan kontak footer.
- `assets/public.css` — palet dan tata letak responsif.
- `assets/public.js` — interaksi halaman publik.
- `docs/portal-publik.md` — catatan pengelolaan layanan publik dan konten.
