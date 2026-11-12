<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = 'Kebijakan Privasi';
$activePage = 'privasi';
require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Perlindungan informasi</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Penjelasan ringkas mengenai informasi yang diproses saat Anda menggunakan portal sekolah.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap privacy-content">
        <article class="card public-feature-card">
            <h2>Informasi pengunjung</h2>
            <p>Halaman informasi dapat dibaca tanpa membuat akun. Formulir kontak hanya meminta nama, alamat email, dan isi pesan agar sekolah dapat menindaklanjuti pertanyaan. Mohon tidak mengirimkan kata sandi, nomor identitas, atau data sensitif siswa melalui formulir tersebut.</p>

            <h2>Statistik kunjungan portal</h2>
            <p>Portal menampilkan perkiraan jumlah pengunjung harian berdasarkan sesi browser. Satu sesi dihitung paling banyak sekali per hari; orang yang menggunakan perangkat atau browser berbeda, atau kembali pada hari lain, dapat dihitung kembali. Statistik disimpan sebagai jumlah agregat per tanggal dan portal tidak menyimpan alamat IP untuk penghitung ini. Angka tersebut merupakan perkiraan kunjungan, bukan jumlah orang unik yang dapat dipastikan.</p>

            <h2>Permohonan PPDB</h2>
            <p>Jika pendaftaran daring dibuka, data yang diminta digunakan untuk memproses permohonan dan komunikasi terkait. Akses data dibatasi untuk petugas berwenang dan hanya disimpan sesuai kebutuhan layanan serta kebijakan sekolah. Dokumen pribadi tidak ditampilkan sebagai unduhan publik.</p>

            <h2>Penggunaan dan keamanan</h2>
            <p>Data tidak dijual atau dipakai untuk iklan. Sekolah menerapkan pembatasan akses dan langkah teknis yang wajar untuk melindungi data. Pengiriman melalui internet tetap memiliki risiko; hubungi sekolah untuk memperbarui atau menanyakan data yang Anda kirim.</p>

            <h2>Kontak</h2>
            <p>Untuk pertanyaan terkait privasi, silakan gunakan <a href="kontak.php">informasi kontak resmi sekolah</a>.</p>
            <p class="privacy-updated">Diperbarui <?= e(date('d F Y')) ?>.</p>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
