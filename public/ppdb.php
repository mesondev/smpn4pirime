<?php require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Informasi PPDB';
$activePage = 'ppdb';
require __DIR__ . '/_header.php'; ?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Penerimaan peserta didik baru</span>
        <h1>Informasi PPDB</h1>
        <p>Informasi penerimaan peserta didik baru disampaikan melalui kanal resmi sekolah.</p>
            <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap two-col">
        <div>
            <span class="kicker">Informasi resmi</span>
            <h2>Hubungi sekolah untuk informasi terbaru</h2>
            <p>Jadwal, persyaratan, dan prosedur PPDB mengikuti ketentuan tahun berjalan. Silakan menghubungi sekolah untuk mendapatkan informasi resmi sebelum menyiapkan berkas.</p>

            <div class="button-row">
                <a class="btn-primary" style="background:var(--navy);color:white" href="kontak.php">Kontak sekolah
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <aside class="tile">
            <span class="tile-icon">
                <i class="bi bi-info-circle"></i>
            </span>
            <h3>Perhatian</h3>
            <p>Halaman ini belum memuat jadwal atau formulir pendaftaran daring. Waspadai informasi pendaftaran dari sumber yang tidak resmi.</p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
