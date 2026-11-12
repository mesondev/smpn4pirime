# Pengelolaan layanan portal publik

Menu **Layanan Publik → Portal Publik** pada dashboard Super Admin menjadi satu ruang kerja: ringkasan publikasi, pintasan profil sekolah/PPDB/pesan, daftar seluruh jenis konten, dan pratinjau langsung website tampil pada satu halaman responsif. Tombol **Kelola** membuka formulir modul lama yang sudah memiliki validasi dan kontrol akses; tabel, rute, dan data publik yang ada tetap dipertahankan. Navigasi dropdown serta halaman-halaman website publik tetap seperti sebelumnya.

Fitur tambahan menggunakan database `sims_db` dan PHP Native yang sudah dipakai proyek. Tidak ada data sekolah yang direkayasa; konten hanya tampil setelah dimasukkan dan berstatus terbit.

## Menerapkan perubahan database

Jalankan dari PowerShell di folder proyek:

```powershell
php database/migrate.php
```

Migrasi `2026_11_02_public_services.sql` menambah tabel untuk ekstrakurikuler, fasilitas, struktur, prestasi publik, jadwal publik, dokumen, media sosial, periode PPDB, dan permohonan PPDB.

## Mengelola konten publik

Super Admin dapat membuka **Layanan Publik → Ringkasan Portal** untuk melihat jumlah konten, draf, pesan yang perlu ditindaklanjuti, dan permohonan PPDB. Menu di kelompok yang sama menyediakan form pengelolaan ekstrakurikuler, fasilitas, struktur organisasi, prestasi publik, jadwal publik, dokumen PDF, dan media sosial. Form melakukan validasi server-side, menyimpan log aktivitas, dan menyediakan status Draft/Terbit/Arsip. Akses setiap halaman dibatasi di server untuk Super Admin.

Tabel `portal_ekstrakurikuler`, `portal_fasilitas`, dan `portal_struktur_sekolah` menampilkan baris yang berstatus `Terbit`. Isi data setelah informasi dikonfirmasi oleh sekolah.

Prestasi hanya tampil jika `status='Terbit'`, `bukti_terverifikasi=1`, dan `izin_publikasi=1`. Dapatkan izin publikasi, khususnya untuk nama siswa, sebelum mengubah nilai tersebut.

Jadwal publik disimpan terpisah dalam `portal_jadwal_publik`; jadwal akademik internal tidak otomatis ditampilkan. Isi tabel ini hanya setelah sekolah meninjau bahwa jadwal memang aman dan sesuai untuk publik.

Foto fasilitas dan struktur dapat memakai nama file acak 40 karakter heksadesimal dengan ekstensi `.jpg`, `.png`, atau `.webp` pada `uploads/portal/`. Simpan nama relatif tersebut di `gambar_url` atau `foto_url`. Gambar disalurkan melalui pemeriksaan status pada `media/portal.php`.

## Mengaktifkan PPDB

Kelola periode resmi melalui **Layanan Publik → Pengelolaan PPDB** pada dashboard Super Admin. Masukkan informasi dan persyaratan yang sudah disetujui. Periode berstatus `Terbit` hanya aktif selama tanggal mulai sampai tanggal selesai. Ubah status menjadi `Ditutup` jika pendaftaran perlu dihentikan lebih awal.

Formulir mengumpulkan nama calon siswa, NISN opsional, nama wali, dan nomor telepon wali. Setelah berhasil, keluarga menerima nomor pendaftaran dan kunci status satu kali. Kunci disimpan dalam bentuk hash. Super Admin memperbarui status permohonan melalui dashboard, menambahkan catatan yang dapat dibaca pendaftar, dan meninjau riwayat perubahan. Daftar pendaftar tidak pernah ditampilkan di portal publik.

Dokumen pendaftaran tidak diunggah oleh formulir. Pendaftar menyerahkan berkas melalui prosedur sekolah. Dengan demikian portal tidak menerima salinan akta, kartu keluarga, atau dokumen sensitif lainnya.

## Mengelola dokumen unduhan

Dokumen publik hanya mendukung PDF. Simpan file dengan nama acak 40 karakter heksadesimal dan ekstensi `.pdf` di `storage/public_documents/`. Folder dilindungi `.htaccess`; pengunjung mengambil file melalui `media/public_document.php`, yang memeriksa status terbit, pola nama, lokasi file, dan tipe MIME.

Unggah PDF melalui **Dokumen Publik** pada dashboard Super Admin. Sistem memberi nama acak, memeriksa MIME dan signature PDF, membatasi ukuran 10 MB, dan mengirim unduhan melalui endpoint yang memeriksa status terbit. Jangan letakkan dokumen pribadi atau berkas PPDB di folder ini.

## Media sosial

Tabel `portal_tautan_sosial` menerima `facebook`, `instagram`, `youtube`, `tiktok`, dan `twitter-x`. Atur URL resmi dengan HTTPS dan `status='aktif'`. Tautan baru akan muncul otomatis pada header dan footer.

## Pesan pengunjung

Formulir pada halaman Kontak menyimpan pertanyaan umum di `pesan_kontak` dan isi pesan di `pesan_kontak_balasan`. Petugas dapat menindaklanjuti melalui menu **Layanan Publik → Pesan Masuk**. Pembatasan formulir, honeypot, token CSRF, validasi, dan jeda pengiriman membantu mengurangi kiriman otomatis.

Penghitung publik mencatat perkiraan kunjungan per hari berdasarkan sesi browser, paling banyak satu hitungan per sesi setiap hari. Sistem hanya menyimpan tanggal dan jumlah agregat pada `portal_pengunjung_harian`, tanpa menyimpan alamat IP. Jumlah harian bukan jumlah orang unik yang dapat dipastikan; browser berbeda dan kunjungan pada hari lain dapat dihitung lagi. Migrasi: `2026_11_04_public_visitors.sql`.

## Tampilan, aksesibilitas, dan pemantauan

Slideshow beranda berpindah otomatis. Tombol panah dan tombol jeda visual dihapus; ketuk area kiri untuk slide sebelumnya dan area kanan untuk slide berikutnya. Tautan di dalam slide dan indikator bulat tetap berfungsi. Keyboard panah kiri/kanan juga dapat digunakan saat sorotan difokuskan. Slideshow menghormati pengaturan reduced motion dan menghentikan timer saat tab tersembunyi. Paragraf, daftar, dan tabel ditampilkan utuh secara bawaan; hanya bagian yang secara eksplisit ditandai untuk ringkasan yang dapat dilipat.

Kesalahan query pada portal dicatat di log aplikasi lokal tanpa mengirimkan rincian database kepada pengunjung. Periksa `storage/logs/` saat halaman menampilkan keadaan data kosong yang tidak diharapkan. Untuk produksi, arahkan `SIMS_LOG_DIR` ke lokasi privat di luar web root dan atur rotasi serta retensi log.

Canonical, sitemap, dan robots untuk domain produksi belum diaktifkan karena domain resmi belum ditetapkan. Jangan menyalin alamat localhost atau domain `.test` menjadi alamat SEO produksi.
