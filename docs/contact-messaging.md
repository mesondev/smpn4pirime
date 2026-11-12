# Pesan kontak sekolah

Formulir pesan publik tersedia pada halaman `public/kontak.php` setelah migrasi tabel pesan diterapkan. Kiriman masuk ke menu **Layanan Publik → Pesan Masuk** untuk dibaca, dibalas, atau ditutup oleh Super Admin. Balasan tetap tersimpan di aplikasi; aplikasi juga mencoba meneruskannya ke email pengunjung.

## Email balasan

Pengiriman memakai fungsi `mail()` bawaan PHP. Server harus memiliki layanan pengiriman email yang berfungsi (sendmail/MTA atau relay SMTP yang dikonfigurasi oleh administrator server). Tetapkan `SIMS_MAIL_FROM` dan `SIMS_MAIL_FROM_NAME` pada environment PHP. Alamat pengirim harus diizinkan oleh layanan email agar pesan tidak ditolak atau dianggap spam.

Pada XAMPP, email tidak otomatis terkirim hanya karena aplikasi berjalan. Hubungkan PHP ke relay SMTP/sendmail sekolah melalui konfigurasi PHP atau layanan email yang dipercaya, kemudian restart Apache. Jangan simpan kata sandi SMTP di repositori. Balasan aplikasi kini masuk ke antrean; worker CLI baru boleh dijadwalkan setelah alamat pengirim dan relay diuji. Saat transport email belum aktif atau gagal, balasan tetap tersimpan dan status pengiriman ditampilkan pada percakapan; log server mencatat kegagalan tanpa menyimpan isi pesan atau rahasia.

