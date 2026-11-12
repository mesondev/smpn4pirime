<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Hasil Seleksi';
$activePage = 'ppdb';
require __DIR__ . '/_header.php';
public_page_hero(
    'Informasi pribadi pendaftar',
    $pageTitle . ' PPDB / SPMB',
    'Periksa perkembangan permohonan melalui kanal resmi dengan data akses yang diberikan setelah pendaftaran.',
    $activePage
);
?>
<section class="content selection-page">
    <div class="wrap">
        <div class="public-empty-state public-empty-state--wide public-empty-state--text selection-privacy-note" role="status">
            <span class="public-empty-icon"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></span>
            <div class="public-empty-copy"><strong>Hasil tidak ditampilkan sebagai daftar umum</strong><p>Informasi pendaftar bersifat pribadi. Portal tidak memuat nama calon siswa atau hasil seleksi terbuka. Pengumuman dan keputusan resmi tetap mengikuti pemberitahuan dari sekolah.</p><div class="public-empty-actions"><a class="text-link" href="cek_pendaftaran.php">Buka pemeriksaan status <i class="bi bi-arrow-right" aria-hidden="true"></i></a><a class="text-link" href="privasi.php">Baca kebijakan privasi <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
        </div>

        <section class="selection-guide" aria-labelledby="selectionGuideTitle">
            <div class="selection-guide-heading"><span class="kicker">Panduan pendaftar</span><h2 id="selectionGuideTitle">Cara memeriksa hasil permohonan</h2><p>Siapkan nomor pendaftaran dan kunci status yang ditampilkan satu kali setelah permohonan berhasil dikirim.</p></div>
            <ol class="selection-steps">
                <li><span class="selection-step-number">01</span><div><h3>Temukan bukti pendaftaran</h3><p>Gunakan nomor pendaftaran dan kunci status yang Anda simpan saat mengirim formulir. Keduanya diperlukan untuk membuka hasil.</p></div></li>
                <li><span class="selection-step-number">02</span><div><h3>Masukkan data akses</h3><p>Buka halaman pemeriksaan, ketik kedua informasi dengan teliti, lalu pilih <strong>Tampilkan status</strong>. Data tersebut tidak perlu dibagikan kepada orang lain.</p></div></li>
                <li><span class="selection-step-number">03</span><div><h3>Baca status dan catatan</h3><p>Jika sekolah sudah memperbarui permohonan, portal menampilkan status dan catatan publik yang diberikan petugas. Ikuti arahan resmi pada catatan tersebut.</p></div></li>
                <li><span class="selection-step-number">04</span><div><h3>Hubungi sekolah bila perlu</h3><p>Jika nomor atau kunci tidak ditemukan, atau catatan belum jelas, hubungi sekolah melalui alamat kontak resmi dengan menyebut nomor pendaftaran saja.</p></div></li>
            </ol>
        </section>

        <div class="cards selection-support-grid">
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-key-fill" aria-hidden="true"></i></span><h2>Simpan kunci status</h2><p>Kunci status dibuat khusus untuk setiap permohonan dan tidak dapat ditampilkan ulang dari halaman publik. Simpan di tempat pribadi yang aman.</p></article>
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-calendar2-check-fill" aria-hidden="true"></i></span><h2>Periode pendaftaran</h2><p>Jadwal, persyaratan, dan pengumuman hanya berlaku jika telah diterbitkan oleh sekolah. Periksa halaman PPDB untuk informasi periode terbaru.</p><a class="text-link" href="ppdb.php">Informasi PPDB / SPMB <i class="bi bi-arrow-right" aria-hidden="true"></i></a></article>
        </div>

        <div class="admission-lookup-callout"><div><strong>Sudah memiliki nomor dan kunci status?</strong><span>Pemeriksaan status dapat dilakukan kapan saja melalui formulir privat.</span></div><a class="btn-light" href="cek_pendaftaran.php">Cek status <i class="bi bi-search" aria-hidden="true"></i></a></div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
