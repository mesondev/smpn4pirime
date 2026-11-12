<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Kurikulum';
$activePage = 'kurikulum';
require __DIR__ . '/_header.php';
public_page_hero(
    'Pembelajaran dan pengembangan kompetensi',
    'Kurikulum Sekolah',
    'Panduan untuk memahami informasi kurikulum yang akan diterbitkan secara resmi oleh ' . ($portalProfile['nama'] ?? 'sekolah') . '.',
    $activePage
);
?>
<section class="content curriculum-page">
    <div class="wrap">
        <div class="public-empty-state public-empty-state--wide public-empty-state--text curriculum-status" role="status">
            <span class="public-empty-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
            <div class="public-empty-copy">
                <strong>Rincian kurikulum belum diterbitkan</strong>
                <p>Nama kurikulum yang berlaku, dokumen acuan, dan penerapannya di sekolah masih menunggu konfirmasi serta persetujuan publikasi. Halaman ini tidak menetapkan kebijakan kurikulum atas nama sekolah.</p>
                <div class="public-empty-actions"><a class="text-link" href="kontak.php">Tanyakan informasi terbaru <i class="bi bi-arrow-right" aria-hidden="true"></i></a><a class="text-link" href="akademik.php">Lihat ringkasan akademik <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            </div>
        </div>

        <section class="curriculum-intro" aria-labelledby="curriculumIntroTitle">
            <div><span class="kicker">Panduan informasi</span><h2 id="curriculumIntroTitle">Apa yang akan tersedia di halaman ini?</h2><p>Pengunjung dapat menggunakan daftar berikut untuk mengetahui jenis informasi yang perlu diperiksa. Rincian di bawah adalah kerangka informasi, bukan pernyataan tentang kebijakan atau pelaksanaan kurikulum sekolah.</p></div>
            <span class="curriculum-intro-icon" aria-hidden="true"><i class="bi bi-book-half"></i></span>
        </section>

        <div class="cards curriculum-grid">
            <article class="card public-feature-card curriculum-card"><span class="tile-icon"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i></span><span class="kicker">01 · Acuan pembelajaran</span><h2>Dokumen kurikulum</h2><p>Nama dan versi dokumen acuan, tahun penerapan, serta keputusan atau panduan resmi yang menjadi rujukan sekolah.</p><span class="curriculum-card-status"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Menunggu verifikasi sekolah</span></article>
            <article class="card public-feature-card curriculum-card"><span class="tile-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><span class="kicker">02 · Kegiatan belajar</span><h2>Struktur pembelajaran</h2><p>Ringkasan mata pelajaran, kegiatan intrakurikuler, pengembangan diri, dan pengaturan belajar yang telah disetujui untuk dibagikan.</p><a class="text-link" href="jadwal.php">Lihat jadwal publik <i class="bi bi-arrow-right" aria-hidden="true"></i></a></article>
            <article class="card public-feature-card curriculum-card"><span class="tile-icon"><i class="bi bi-clipboard2-check-fill" aria-hidden="true"></i></span><span class="kicker">03 · Pemantauan belajar</span><h2>Asesmen dan pelaporan</h2><p>Penjelasan umum tentang cara sekolah menyampaikan perkembangan belajar kepada peserta didik dan keluarga, setelah prosedurnya dikonfirmasi.</p><a class="text-link" href="kontak.php">Tanyakan kepada sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a></article>
            <article class="card public-feature-card curriculum-card"><span class="tile-icon"><i class="bi bi-calendar2-week-fill" aria-hidden="true"></i></span><span class="kicker">04 · Pelaksanaan</span><h2>Tahun ajaran dan agenda</h2><p>Periode belajar dan tanggal kegiatan akan mengikuti kalender yang diterbitkan. Perubahan jadwal merujuk pada pengumuman terbaru sekolah.</p><a class="text-link" href="agenda.php">Buka kalender akademik <i class="bi bi-arrow-right" aria-hidden="true"></i></a></article>
        </div>

        <section class="curriculum-next-steps" aria-labelledby="curriculumNextTitle">
            <div class="curriculum-next-heading"><span class="kicker">Jelajahi layanan terkait</span><h2 id="curriculumNextTitle">Lanjutkan ke informasi akademik</h2><p>Gunakan halaman-halaman berikut untuk melihat data publik lain yang sudah tersedia.</p></div>
            <div class="curriculum-link-list"><a href="akademik.php"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span><strong>Ringkasan akademik</strong><small>Tahun ajaran dan angka agregat yang tersedia</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="ekstrakurikuler.php"><i class="bi bi-stars" aria-hidden="true"></i><span><strong>Ekstrakurikuler</strong><small>Kegiatan yang telah dikonfirmasi sekolah</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="unduhan.php"><i class="bi bi-file-earmark-arrow-down-fill" aria-hidden="true"></i><span><strong>Pusat unduhan</strong><small>Dokumen publik yang sudah diterbitkan</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
