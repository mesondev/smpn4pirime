# Audit Kekurangan dan Saran Pengembangan Portal Publik

**Tanggal audit:** 2 November 2026  
**Cakupan:** pemeriksaan statis berkas portal publik pada folder `public/`, stylesheet/script terkait, dan dokumentasi portal.  
**Tujuan:** menjadi daftar kerja yang konkret untuk melengkapi informasi, memperbaiki konsistensi, dan mengembangkan template publik yang mengikuti pola visual referensi.  
**Batas audit:** ini adalah pemeriksaan isi kode/dokumen. Halaman tidak dijalankan di browser dan database produksi tidak diperiksa, sehingga keberhasilan runtime, kelengkapan data aktual, dan kebenaran informasi lapangan tetap perlu diverifikasi.

## 1. Kesimpulan audit

Portal publik sudah mempunyai cakupan fitur yang luas: beranda, profil, guru, akademik, berita, pengumuman, kalender, galeri, prestasi, ekstrakurikuler, fasilitas, struktur, PPDB/SPMB, unduhan, kontak, pencarian, privasi, dan halaman 404. Struktur visual bersama, navigasi responsif, breadcrumb, empty state, pencarian pada beberapa daftar, moderasi status terbit, dan beberapa perlindungan formulir juga telah diterapkan.

Kekurangan utama yang terlihat bukan kekurangan jumlah halaman. Yang perlu dibereskan adalah **kelengkapan dan pemutakhiran konten resmi, konsistensi sumber data, pengelolaan konten oleh petugas, verifikasi aset dan kontak, penyempurnaan metadata/SEO, serta pemeriksaan tampilan dan alur di perangkat nyata**. Desain template dapat dikembangkan mengikuti gambar dengan hero foto dan panel informasi, kartu ringkas, bagian cerita/statistik, galeri, CTA, dan footer. Implementasi visual sebaiknya dilakukan setelah isi dan aset yang sah ditetapkan.

## 2. Hal yang sudah tersedia

- Kerangka umum situs: header, navigasi utama, area konten, footer dan halaman dalam dengan judul/breadcrumb.
- Beranda dengan hero slideshow, statistik, shortcut layanan, program, sambutan, pengumuman, berita, agenda, galeri, dan CTA lokasi.
- Banyak halaman daftar/detail, pencarian tertentu, keadaan kosong dan pesan ketika layanan/datanya belum siap.
- PPDB memiliki kondisi periode aktif/tutup, formulir permohonan, persetujuan kebijakan privasi, nomor dan kunci status; hasil tidak dibuat sebagai daftar publik terbuka.
- Konten prestasi memiliki syarat status terbit, verifikasi bukti dan izin publikasi.
- Dokumentasi layanan menyebut pembatasan tipe/lokasi file publik, pemeriksaan media, CSRF, honeypot dan jeda formulir.
- Navigasi mobile, label aksesibilitas, tautan lewati konten dan dukungan reduced motion telah tampak di implementasi.
- Bahasa visual biru, tipografi judul/isi terpisah, kartu, grid, dan breakpoint responsif sudah menjadi dasar yang dapat dipakai.

## 3. Temuan kekurangan dan saran

### A. Prioritas kritis sebelum informasi dipublikasikan

| Temuan | Bukti/indikasi pada kode/dokumen | Saran pengembangan |
|---|---|---|
| Konten layanan publik bergantung pada isian database yang mungkin belum lengkap | Banyak halaman menampilkan kalimat “belum tersedia”, “belum diterbitkan”, atau menunggu verifikasi; dokumentasi layanan baru meminta data dimasukkan setelah migrasi | Buat daftar data wajib per halaman, tetapkan petugas pemilik data dan tanggal peninjauan. Tandai setiap konten sebagai belum siap/siap tayang dan jangan mengisi dengan data tebakan. |
| Data kontak tidak konsisten sumbernya | **Diperbaiki di kode:** profil, kontak, footer, tombol telepon, dan WhatsApp sekarang menggunakan kolom telepon/email pada profil sekolah; format divalidasi sebelum dibuat tautan. Nilai yang kosong tidak ditampilkan sebagai tautan palsu. | Petugas tetap perlu mengisi dan mengonfirmasi nomor/email pada menu konfigurasi profil sekolah. |
| Peta dan koordinat berpotensi tidak selaras | **Diperbaiki sebagian:** halaman kontak memakai URL `peta_url` dari profil bila URL HTTPS Google Maps valid, dan petunjuk arah mencari alamat profil, bukan koordinat tetap. Peta My Maps lama masih menjadi fallback agar tampilan tidak mendadak kosong. | Konfirmasi peta fallback dan isi URL embed sekolah yang benar pada pengaturan profil. Setelah titik resmi disahkan, hapus fallback lama jika sudah tidak diperlukan. |
| Foto/identitas orang perlu verifikasi | Profil menampilkan gambar staf dan menyebut nama/jabatan menunggu konfirmasi; beberapa bagian galeri/fasilitas dapat memakai ilustrasi/aset referensi | Audit semua file gambar: sumber, tanggal, subjek, izin publikasi, teks alt, dan status dokumentasi/ilustrasi. Sembunyikan foto sensitif sampai persetujuan dan keterangannya dipastikan. |
| Alur pengelolaan konten layanan baru belum terlihat sebagai alur admin terpadu | `docs/portal-publik.md` menginstruksikan data tertentu untuk dimasukkan/diatur melalui database | Tambahkan form admin dengan validasi, status draft/review/terbit, penulis, tanggal publikasi, unggah aman, pratinjau, dan log perubahan. Jika belum dibangun, dokumentasikan prosedur manual, akses DB yang berwenang, serta checklist rilis supaya perubahan tidak bergantung pada satu orang teknis. |
| Detail status PPDB perlu diuji operasional | Portal menyediakan formulir dan cek status, tetapi petugas harus menangani permohonan dan memperbarui status; informasi resmi wajib tersedia sebelum periode tayang | Tetapkan SOP petugas: siapa menerima, tenggat tindak lanjut, status yang boleh dipakai, cara menghubungi keluarga, penanganan pendaftaran ganda, dan prosedur ketika periode ditutup. Lakukan simulasi dari kirim sampai cek status sebelum dibuka. |

### B. Prioritas tinggi untuk kelengkapan konten dan pengalaman pengguna

| Area | Hal yang perlu dilengkapi | Saran |
|---|---|---|
| Identitas sekolah | Visi/misi, sejarah, sambutan, kepala sekolah, identitas dan akreditasi dapat membutuhkan dokumen/konfirmasi terbaru | Minta pengesahan tertulis dari sekolah; tampilkan sumber dan tahun data untuk identitas yang berasal dari rujukan eksternal. Jangan tampilkan placeholder sebagai fakta. |
| Akademik | Tahun ajaran, kalender, kurikulum, jadwal dan ringkasan kelas perlu periode yang jelas | Cantumkan “berlaku untuk tahun ajaran …”, tanggal pembaruan dan pihak sumber. Bedakan jadwal publik dengan jadwal internal yang berisiko membuka informasi siswa. |
| Berita/pengumuman | Konten memerlukan ringkasan, kategori, tanggal, gambar, masa tayang, dan tautan detail yang konsisten | Tetapkan format editorial sederhana, penanggung jawab dan batas masa tayang; sediakan penanda tidak ada hasil, reset filter, pagination saat jumlah konten meningkat. |
| Guru/struktur | Direktori dan struktur harus terbaru dan disetujui | Tetapkan data minimal yang layak publik (nama/jabatan/foto bila berizin), tanggal pembaruan, serta cara menarik profil saat tidak lagi aktif. Jangan tampilkan data kontak personal. |
| Galeri | Ilustrasi dapat mengisi ruang saat belum ada dokumentasi aktual | Prioritaskan dokumentasi yang diizinkan. Beri label konsisten “Ilustrasi” untuk gambar pendukung dan jangan mencampur dokumentasi dan ilustrasi tanpa pemisah yang jelas. Hindari pengulangan aset untuk memenuhi jumlah kartu. |
| Fasilitas | Data aktual menunggu pemeriksaan; beberapa gambar berstatus aset referensi | Cocokkan daftar dan foto dengan kondisi sekolah saat ini. Setiap gambar perlu caption yang menggambarkan fasilitas benar-benar ada. |
| Unduhan | Daftar dokumen bergantung pada dokumen resmi terbit | Cantumkan nama, jenis, ukuran, tanggal/versi, dan siapa yang menerbitkan. Tinjau berkala apakah dokumen masih berlaku dan tidak memuat data pribadi. |
| Kontak | Jam layanan/alamat dapat belum diisi; peta eksternal bergantung layanan pihak ketiga | Lengkapi alamat tertulis, petunjuk lokasi lokal yang mudah dipahami, jam layanan aktual, kanal cadangan, dan perilaku jika peta tidak termuat. Jangan mengandalkan peta saja. |
| Formulir pesan | Formulir meminta nama, email, telepon opsional, kategori, subjek, isi | Uji pesan terkirim/gagal, validasi, CSRF, honeypot, pembatasan laju, konfirmasi kepada pengirim, dan antrean petugas. Jelaskan perkiraan waktu tanggapan hanya jika dapat dipenuhi. |
| Pencarian | Ada halaman pencarian dan pencarian daftar tertentu | Pastikan pencarian mencakup jenis konten yang dijanjikan, meng-escape kata kunci, menangani tanpa hasil, dan tidak membocorkan konten draft/tidak terbit. Tambahkan filter dan pagination bila hasil panjang. |
| Navigasi | Banyak item dalam dropdown dan menu mobile | Tinjau urutan menurut tugas orang tua/calon siswa/masyarakat; sediakan tautan PPDB dan kontak yang mudah ditemukan. Periksa setiap link dan anchor karena konten halaman bisa berubah. |

### C. Prioritas desain sesuai gambar referensi

1. **Hero:** pertimbangkan satu visual utama yang kuat dengan foto lingkungan sekolah yang disetujui, overlay navy, headline ringkas, satu CTA utama dan satu CTA pendamping. Tambahkan panel kanan untuk status PPDB atau informasi resmi yang berubah secara dinamis. Jika slideshow dipertahankan, kurangi slide yang berulang dan pastikan kontrol/pause dapat digunakan.
2. **Bar ringkasan:** gunakan tiga nilai/layanan sekolah yang nyata (misalnya pendidikan, karakter, kegiatan) dan hindari klaim promosi yang belum disahkan.
3. **Kartu beranda:** ganti pola katalog produk referensi menjadi kartu shortcut layanan, berita, agenda, program, dan dokumen. Jangan menambahkan angka/harga yang tidak bermakna untuk sekolah.
4. **Panel cerita dan statistik:** sambutan sekolah di satu sisi, statistik yang terverifikasi dan memiliki tanggal sumber di sisi lain. Bila tidak ada data, sembunyikan statistik alih-alih menampilkan angka buatan.
5. **Media/galeri:** gunakan foto aktual berizin dengan rasio crop konsisten, caption, tanggal, dan tautan ke galeri lengkap.
6. **CTA:** panel warna biru gelap di akhir isi dengan aksi yang sesuai keadaan: kontak, lokasi, atau PPDB jika sedang dibuka.
7. **Footer:** pertahankan kelompok navigasi yang ringkas, tetapi periksa redundansi tautan dan konsistensi kontak.
8. **Konsistensi desain:** satukan token warna, radius, bayangan, lebar container, jarak section dan ukuran heading. `assets/public.css` berisi banyak blok tambahan/override yang menumpuk; perlu refactor bertahap menjadi komponen dasar dan aturan breakpoint yang mudah dirawat.
9. **Ponsel:** screenshot referensi adalah halaman desktop yang diperkecil di layar ponsel. Template sekolah harus benar-benar reflow ke satu kolom, bukan mengecilkan seluruh halaman; tombol, teks, kartu dan menu perlu ukuran sentuh yang nyaman.

### D. Prioritas teknis dan kualitas

| Area | Temuan awal | Saran |
|---|---|---|
| SEO dasar | Metadata yang terlihat di header mencakup judul dan description umum; tidak tampak metadata canonical/Open Graph/Twitter Card dari pencarian kode ini | Tambahkan title/description unik per halaman, canonical, Open Graph, gambar berbagi yang disetujui, dan sitemap/robots bila sesuai konfigurasi deployment. Verifikasi URL produksi sebelum mengaktifkan indeksasi. |
| Data terstruktur | Pencarian kode tidak menemukan blok JSON-LD; `schema.css` sendiri adalah stylesheet, bukan JSON-LD | Pertimbangkan structured data organisasi/sekolah dan breadcrumb yang valid setelah identitas/alamat diverifikasi. Validasi output HTML/structured data. |
| Dependensi luar | Font, ikon dan peta dimuat dari layanan eksternal | Nilai fallback saat jaringan sekolah lambat, kegagalan CDN, atau pemblokiran konten. Pertimbangkan hosting lokal yang sah dan kebijakan privasi/CSP yang cocok. Jangan menghapus sumber lisensi/atribusi yang diwajibkan. |
| Gambar | Placeholder, foto referensi dan foto aktual perlu pembedaan | Kompres/rescale, format modern, dimensi/aspect ratio eksplisit, lazy loading di bawah fold, alt yang sesuai konteks, serta fallback jika file hilang. |
| Gaya CSS | `assets/public.css` telah berkembang dengan banyak blok akhir dan aturan yang menimpa aturan sebelumnya | Pecah menjadi token, layout, komponen, halaman dan breakpoint; hapus duplikasi hanya setelah tampilan dibandingkan. Pertahankan satu sumber nilai per komponen. |
| Pemeliharaan | Ada endpoint alias `kalender.php` yang sangat kecil selain `agenda.php` dan tautan utama diarahkan ke agenda | Pastikan apakah alias diperlukan, memberi redirect/canonical dengan benar, atau dapat dihapus sesuai kompatibilitas. |
| Aksesibilitas | Ada beberapa dukungan aksesibilitas, namun pemeriksaan aktual belum dilakukan | Tinjau kontras, ukuran teks, urutan fokus, dropdown mobile/keyboard, carousel, nama aksesibel ikon, error form, zoom 200%, dan pembaca layar. |
| Privasi dan keamanan publik | Kebijakan privasi dan sebagian kontrol form tersedia; database dan konfigurasi produksi tidak diaudit | Tinjau retensi/penghapusan pesan dan data PPDB, hak akses petugas, penyimpanan rahasia, sanitasi output, unggahan, rate limiting, backup terenkripsi, dan prosedur insiden. Lakukan dengan pemilik sistem sebelum perubahan produksi. |
| Performa | Belum ada pengukuran runtime | Ukur halaman awal dan halaman panjang di ponsel/jaringan lambat; catat waktu muat, ukuran gambar, layout shift dan sumber daya eksternal sebelum menentukan optimasi. |
| Penanganan error | Empty state informatif telah ada di beberapa layanan | Pastikan error DB/HTTP tidak menampilkan stack trace, tersedia logging internal yang aman, dan pengunjung mendapat pesan serta cara kontak yang berguna. |

## 4. Daftar halaman dan pekerjaan spesifik

| Halaman/kelompok | Pemeriksaan/pelengkapan yang disarankan |
|---|---|
| Beranda | Pilih hero final; verifikasi statistik, CTA PPDB, sambutan dan seluruh aset; hapus pengulangan visual; pastikan tidak terlalu panjang di ponsel. |
| Profil | Konfirmasi identitas, akreditasi, kepala sekolah, foto, kontak dan sumber; tampilkan tanggal pembaruan. |
| Sejarah, visi-misi | Minta naskah resmi; tambah sumber/periode pengesahan. |
| Guru, struktur | Konfirmasi daftar aktif, jabatan, ejaan, izin foto/nama dan mekanisme pembaruan. |
| Fasilitas, ekstrakurikuler, prestasi | Verifikasi keberadaan, jadwal/pembina, bukti prestasi dan izin; gunakan foto aktual yang disetujui. |
| Akademik, kurikulum, jadwal | Tambah tahun ajaran/versi dan peninjauan privasi; pastikan perubahan periode tidak membuat informasi lama dianggap berlaku. |
| Agenda/kalender | Pastikan zona waktu, tanggal awal/akhir, pembatalan dan tahun filter; jelaskan perbedaan kalender dan agenda jika keduanya dipertahankan. |
| Berita/pengumuman/detail | Pastikan tanggal publikasi, masa tayang, isi lengkap, breadcrumb, media, tautan terkait dan metadata halaman spesifik. |
| Galeri | Pisahkan ilustrasi dari dokumentasi; caption, izin, tanggal, optimasi, dan fallback gambar. |
| PPDB/cek/hasil seleksi | Simulasi seluruh tahapan; verifikasi copy, status, pesan aman, pencegahan enumerasi kode, dukungan petugas dan kebijakan pengumuman. |
| Unduhan | Metadata versi/ukuran/tanggal, nama file aman, hanya PDF resmi yang disetujui, uji tautan kedaluwarsa. |
| Kontak | Satukan sumber kontak, peta/koordinat, alamat, jam dan CTA; uji formulir dan fallback bila map atau email tidak tersedia. |
| Pencarian | Pastikan cakupan, pengurutan, filter, pagination, pesan tanpa hasil, dan eksklusi konten privat/draft. |
| Kebijakan privasi | Minta tinjauan penanggung jawab sekolah; jelaskan data apa yang dikumpulkan, tujuan, akses, retensi, penghapusan, kanal hak subjek data dan pihak ketiga. |
| Laporan pengembangan | Sinkronkan halaman laporan yang tampil di portal dengan status aktual; jangan menyebut fitur “sudah tersedia” sebelum alur runtime/admin benar-benar dipakai. |
| 404 | Periksa tautan beranda, pencarian, navigasi, dan status HTTP 404 yang benar di server. |

## 5. Roadmap yang disarankan

### Tahap 1 — Benahi fakta dan konfigurasi (paling dahulu)

- Tetapkan seorang penanggung jawab konten dan satu pihak penyetuju.
- Verifikasi nomor, email, alamat, jam layanan, titik peta, identitas, periode PPDB, dan semua klaim publik.
- Buat inventaris foto/dokumen: sumber, izin, status aktual/ilustrasi, tanggal, pemilik, dan lokasi file.
- Satukan nilai kontak/peta pada pengaturan agar tidak tersebar di berkas PHP.
- Lengkapi konten minimum untuk profil, kontak, berita, agenda dan layanan prioritas; tandai halaman yang sengaja belum memiliki konten.

### Tahap 2 — Lengkapi alur operasional

- Buat/rapikan pengelolaan admin untuk berita, pengumuman, agenda, galeri, fasilitas, struktur, prestasi, dokumen, tautan sosial dan PPDB.
- Terapkan status draft → tinjau → terbit → arsip, tanggal tayang, penulis, riwayat perubahan, dan pratinjau.
- Tetapkan SOP untuk pesan masuk dan permohonan PPDB, termasuk petugas, waktu tindak lanjut, status, dan retensi data.
- Verifikasi backup dan pemulihan sebelum bergantung pada data pendaftaran.

### Tahap 3 — Terapkan sistem desain serupa referensi

- Sepakati hero foto/panel informasi/CTA dan struktur homepage final.
- Rapikan token CSS dan tetapkan spesifikasi komponen: header, hero, strip manfaat, kartu, panel angka, daftar, CTA, footer, form dan state.
- Samakan template halaman listing, detail, data/tabel, formulir dan halaman informasi.
- Pastikan versi mobile disusun ulang secara responsif dan navigasinya tetap nyaman.

### Tahap 4 — Pemeriksaan kualitas dan publikasi

- Pemeriksaan manual semua tautan, halaman, formulir, pesan sukses/gagal, status konten, media dan responsif.
- Audit aksesibilitas, metadata, keamanan konfigurasi, performa dan error log.
- Pastikan konten dan izin disetujui; lakukan publikasi dan tentukan jadwal tinjau ulang.
- Catat hasil, tanggal, penanggung jawab dan isu yang belum selesai dalam checklist rilis.

## 6. Checklist penerimaan akhir

Sebelum menyatakan portal lengkap, pastikan semua butir berikut memiliki bukti atau penanggung jawab:

- [ ] Identitas sekolah, kontak, alamat, jam dan peta terverifikasi dan satu sumber.
- [ ] Setiap halaman mempunyai judul, isi pembuka, H1, breadcrumb bila perlu, CTA yang benar, dan metadata halaman.
- [ ] Tidak ada placeholder yang disangka konten faktual; halaman kosong memberi penjelasan dan jalur lanjut.
- [ ] Semua foto/dokumen memiliki sumber, izin, caption/alt, status dan masa berlaku yang diketahui.
- [ ] Berita, agenda, prestasi, jadwal, PPDB dan unduhan mempunyai pemilik serta prosedur pembaruan.
- [ ] Menu, tautan footer, anchor, pencarian dan 404 telah diperiksa.
- [ ] Formulir publik diuji dari awal sampai petugas menerima dan menindaklanjuti data.
- [ ] Privasi, retensi, hak akses, backup dan prosedur insiden ditinjau pihak berwenang.
- [ ] Header, carousel, kartu, tabel, peta, formulir dan footer berfungsi pada ponsel, tablet, desktop, keyboard dan zoom.
- [ ] Metadata berbagi/pencarian, performa, error server dan dependensi eksternal diperiksa di lingkungan yang akan dipakai.
- [ ] Laporan pengembangan portal sesuai dengan fitur dan isi yang benar-benar aktif.

## 7. File yang diperiksa

- `public/index.php`, halaman-halaman `public/*.php`, `public/_header.php`, `public/_footer.php`, `public/_common.php`, `public/_public_sections.php`
- `assets/public.css`, `assets/public.js`, `assets/schema.css`
- `docs/portal-publik.md`, `public/laporan_pengembangan.php`

## 8. Catatan akhir

Audit ini memberi cakupan menyeluruh atas berkas publik yang tersedia, tetapi tidak dapat menjamin “tidak ada sedikit pun yang kurang” tanpa verifikasi data sekolah, database yang dipakai, tampilan browser, alur petugas, serta konfirmasi pemilik informasi. Karena itu, daftar roadmap dan checklist di atas adalah syarat untuk mengubah temuan statis ini menjadi status siap publikasi.

## 9. Tindak lanjut kode pada 2 November 2026

- Kontak telepon/email pada profil publik, footer, halaman kontak, CTA beranda, dan WhatsApp header disatukan ke profil database yang sudah dikelola melalui modul konfigurasi yang ada.
- Nilai telepon dan email divalidasi sebelum dipakai sebagai skema `tel:`, `mailto:`, atau tautan WhatsApp.
- `peta_url` dari profil dipakai hanya bila memakai HTTPS dan host/path Google Maps yang diizinkan; peta lama tetap menjadi fallback sementara. Petunjuk arah dibangun dari alamat profil, sehingga tidak bergantung pada koordinat statis.
- README sekarang memiliki langkah khusus Laragon. Dokumentasi pesan kontak dibetulkan agar sesuai dengan formulir dan menu Pesan Masuk yang tersedia.
- Pemeriksaan sintaks PHP berhasil untuk enam file yang disentuh dan `git diff --check` tidak menemukan whitespace error. Browser, koneksi database Laragon, dan alur formulir belum dijalankan dalam audit ini.

## 10. Tindak lanjut kode pada 3 November 2026

- Menambahkan dashboard **Ringkasan Portal Publik** dan form admin untuk berita, pengumuman, galeri, ekstrakurikuler, fasilitas, struktur, prestasi, jadwal publik, dokumen, dan media sosial. Konten memakai tabel yang sudah tersedia pada migrasi layanan publik.
- Membatasi seluruh modul pengelolaan `portal_*` pada Super Admin di sisi server; navigasi yang disembunyikan bukan satu-satunya lapisan izin.
- Unggah fasilitas/foto memakai pemeriksaan gambar dan nama file acak yang sudah ada. Penerbitan fasilitas mensyaratkan foto aktual. Prestasi mensyaratkan verifikasi bukti dan izin publikasi.
- Menambahkan unggah dokumen PDF maksimal 10 MB dengan pemeriksaan MIME dan signature, nama acak, penyimpanan pada folder dokumen terlindungi, dan pengiriman melalui endpoint unduhan yang memeriksa status terbit.
- Menambahkan validasi domain HTTPS untuk tautan media sosial, tanggal/jam jadwal, batas panjang input, pencatatan aktivitas publikasi, dan penghapusan aset setelah record berhasil dihapus.
- Menambahkan metadata Open Graph dan deskripsi berbeda per halaman. Host lokal (`localhost`, `.test`) serta halaman pencarian, cek pendaftaran, dan 404 diberi `noindex`.
- Pengelolaan PPDB berada pada **Layanan Publik → Pengelolaan PPDB**, dengan periode, pencarian/filter pendaftar, catatan untuk pendaftar, dan riwayat status. Perubahannya memakai migrasi aditif serta akses Super Admin.
- Pemeriksaan koneksi proyek menunjukkan `sims_db` pada server MySQL 8.4.3 di instalasi Laragon; seluruh tabel portal yang dipakai form baru telah diperiksa keberadaan kolomnya.
- `php tests/run.php` lulus 14 tes, termasuk sintaks 78 berkas PHP, 2 JavaScript, 10 CSS, pemeriksaan rute publik/terproteksi dan ledger migrasi.

Belum dilakukan pada tindak lanjut ini: tinjauan resmi nama/alamat/kontak/foto oleh sekolah; pengisian konten yang belum disahkan; pengukuran performa halaman pada perangkat nyata; pengujian browser interaktif dengan akun Super Admin; sitemap/canonical berbasis domain produksi; serta backup dan pemulihan database. Domain resmi perlu disediakan sebelum metadata URL kanonis dan sitemap diaktifkan.
