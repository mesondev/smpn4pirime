<?php

declare(strict_types=1);

require_module_access('portal_admin');
require_once __DIR__ . '/public/_common.php';

$pageTitle = 'Portal Publik';
$contentModules = [
    ['module'
    => 'berita_sekolah', 'title'
    => 'Berita sekolah', 'table'
    => 'berita_sekolah', 'icon'
    => 'bi-newspaper'],

    ['module'
    => 'pengumuman', 'title'
    => 'Pengumuman', 'table'
    => 'pengumuman', 'icon'
    => 'bi-megaphone'],

    ['module'
    => 'kalender_akademik', 'title'
    => 'Kalender dan agenda', 'table'
    => 'kalender_akademik', 'icon'
    => 'bi-calendar-event', 'summary_mode'
    => 'calendar'],

    ['module'
    => 'galeri_sekolah', 'title'
    => 'Galeri kegiatan', 'table'
    => 'galeri_sekolah', 'icon'
    => 'bi-images'],

    ['module'
    => 'portal_ekstrakurikuler', 'title'
    => 'Ekstrakurikuler', 'table'
    => 'portal_ekstrakurikuler', 'icon'
    => 'bi-stars'],

    ['module'
    => 'portal_fasilitas', 'title'
    => 'Fasilitas', 'table'
    => 'portal_fasilitas', 'icon'
    => 'bi-buildings'],

    ['module'
    => 'portal_struktur_sekolah', 'title'
    => 'Struktur organisasi', 'table'
    => 'portal_struktur_sekolah', 'icon'
    => 'bi-diagram-3'],

    ['module'
    => 'portal_prestasi_publik', 'title'
    => 'Prestasi publik', 'table'
    => 'portal_prestasi_publik', 'icon'
    => 'bi-award'],

    ['module'
    => 'portal_jadwal_publik', 'title'
    => 'Jadwal publik', 'table'
    => 'portal_jadwal_publik', 'icon'
    => 'bi-calendar-week'],

    ['module'
    => 'portal_dokumen_publik', 'title'
    => 'Dokumen unduhan', 'table'
    => 'portal_dokumen_publik', 'icon'
    => 'bi-file-earmark-pdf'],
    ['module'
    => 'portal_tautan_sosial', 'title'
    => 'Media sosial', 'table'
    => 'portal_tautan_sosial', 'icon'
    => 'bi-share'],
];

$contentSummary = [];
$totalDrafts = 0;
foreach ($contentModules as $module) {
    if ($module['table'] === 'portal_tautan_sosial') {
        $ready = portal_schema_ready($pdo, $module['table'], ['platform', 'url', 'status']);
        $row = $ready ? portal_row($pdo, "SELECT COUNT(*) AS total,SUM(status='aktif') AS terbit FROM `{$module['table']}`") : [];
    } elseif (($module['summary_mode'] ?? '') === 'calendar') {
        $ready = portal_schema_ready($pdo, $module['table'], ['id', 'tampil_publik', 'status', 'tanggal_mulai', 'tanggal_selesai']);
        $row = $ready ? portal_row($pdo, "SELECT COUNT(*) AS total,SUM(tampil_publik=1 AND status<>'Dibatalkan' AND COALESCE(tanggal_selesai,tanggal_mulai)>=CURDATE()) AS terbit,SUM(tampil_publik=0 AND status<>'Dibatalkan') AS draf FROM `{$module['table']}`") : [];
    } else {
        $ready = portal_schema_ready($pdo, $module['table'], ['id', 'status']);
        $row = $ready ? portal_row($pdo, "SELECT COUNT(*) AS total,SUM(status='Terbit') AS terbit,SUM(status='Draft') AS draf FROM `{$module['table']}`") : [];
    }
    $item = [
        ...$module,
        'ready' => $ready,
        'total' => (int) ($row['total'] ?? 0),
        'terbit' => (int) ($row['terbit'] ?? 0),
        'draf' => (int) ($row['draf'] ?? 0),
        'active_label' => ($module['summary_mode'] ?? '') === 'calendar' ? 'agenda publik' : 'aktif',
        'draft_label' => ($module['summary_mode'] ?? '') === 'calendar' ? 'belum ditampilkan' : 'draf',
    ];
    $contentSummary[] = $item;
    $totalDrafts += $item['draf'];
}
$pendingMessages = portal_schema_ready($pdo, 'pesan_kontak', ['status'])
    ? (int) $pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE status IN ('baru','dibaca','diproses')")->fetchColumn()
    : null;
$pendingApplicants = portal_schema_ready($pdo, 'ppdb_pendaftar', ['status'])
    ? (int) $pdo->query("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status='Dalam verifikasi'")->fetchColumn()
    : null;
$activeAdmissionPeriods = portal_schema_ready($pdo, 'ppdb_periode', ['status', 'mulai', 'selesai'])
    ? (int) $pdo->query("SELECT COUNT(*) FROM ppdb_periode WHERE status='Terbit' AND mulai<=CURDATE() AND selesai>=CURDATE()")->fetchColumn()
    : null;

require __DIR__ . '/includes/header.php';
?>
<div class="portal-admin-page">
    <header class="portal-admin-heading">
        <div><span class="portal-admin-eyebrow">Administrasi website sekolah</span>
            <h1>Portal Publik</h1>
            <p>Kelola konten dan lihat tampilan website publik dari satu ruang kerja.</p>
        </div>
        <a class="btn btn-outline-primary" href="public/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Buka website publik</a>
    </header>

    <nav class="portal-admin-anchor-nav" aria-label="Navigasi Portal Publik">
        <a href="#ringkasan-portal"><i class="bi bi-speedometer2" aria-hidden="true"></i> Ringkasan</a>
        <a href="#pengelolaan-portal"><i class="bi bi-grid" aria-hidden="true"></i> Pengelolaan</a>
        <a href="#pratinjau-portal"><i class="bi bi-window" aria-hidden="true"></i> Pratinjau website</a>
    </nav>

    <section class="portal-admin-kpis" id="ringkasan-portal" aria-label="Ringkasan layanan portal">
        <article><span><i class="bi bi-pencil-square" aria-hidden="true"></i> Konten draf</span><strong><?= number_format($totalDrafts) ?></strong><small>Belum diterbitkan</small></article>
        <article><span><i class="bi bi-chat-left-text" aria-hidden="true"></i> Pesan ditindaklanjuti</span><strong><?= $pendingMessages === null ? '—' : number_format($pendingMessages) ?></strong><small>Masuk, dibaca, atau diproses</small><a href="index.php?module=pesan_masuk">Buka pesan masuk</a></article>
        <article><span><i class="bi bi-person-check" aria-hidden="true"></i> PPDB menunggu</span><strong><?= $pendingApplicants === null ? '—' : number_format($pendingApplicants) ?></strong><small>Dalam verifikasi</small><a href="index.php?module=ppdb_admin">Buka pengelolaan PPDB</a></article>
        <article><span><i class="bi bi-calendar2-check" aria-hidden="true"></i> Periode PPDB aktif</span><strong><?= $activeAdmissionPeriods === null ? '—' : number_format($activeAdmissionPeriods) ?></strong><small>Periksa tanggal dan status</small><a href="index.php?module=ppdb_admin&tab=periode">Kelola periode</a></article>
    </section>

    <section class="portal-admin-section portal-admin-workspace" id="pengelolaan-portal" aria-labelledby="portalContentTitle">
        <div class="portal-admin-section-heading">
            <div><span class="portal-admin-eyebrow">Semua menu dalam satu halaman</span>
                <h2 id="portalContentTitle">Pengelolaan konten publik</h2>
                <p>Pilih jenis konten untuk membuka daftar dan formulir pengelolaan. Perubahan tetap memakai validasi dan izin akses yang sama.</p>
            </div>
        </div>
        <div class="portal-admin-quick-actions" aria-label="Pengelolaan utama">
            <a id="portal-school" href="index.php?module=profil_sekolah"><span><i class="bi bi-building" aria-hidden="true"></i></span>
                <div><strong>Profil sekolah</strong><small>Identitas, visi-misi, sambutan, dan kontak</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
            <a id="portal-ppdb" href="index.php?module=ppdb_admin"><span><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                <div><strong>PPDB / SPMB</strong><small>Periode, pendaftar, status, dan hasil</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
            <a id="portal-messages" href="index.php?module=pesan_masuk"><span><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
                <div><strong>Pesan pengunjung</strong><small>Baca pesan dan kelola tindak lanjut</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="portal-admin-modules">
            <?php foreach ($contentSummary as $item): ?>
                <article class="portal-admin-module" id="portal-module-<?= e($item['module']) ?>">
                    <span class="portal-admin-module-icon"><i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i></span>
                    <div class="portal-admin-module-copy">
                        <h3><?= e($item['title']) ?></h3>
                        <?php if ($item['ready']): ?><p><?= number_format($item['terbit']) ?> <?= e($item['active_label']) ?> · <?= number_format($item['draf']) ?> <?= e($item['draft_label']) ?> · <?= number_format($item['total']) ?> total</p><?php else: ?><p class="text-warning-emphasis">Skema belum tersedia; jalankan migrasi layanan publik.</p><?php endif; ?>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="index.php?module=<?= e($item['module']) ?>"><?= $item['ready'] ? 'Kelola' : 'Periksa' ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="portal-admin-section portal-admin-preview-section" id="pratinjau-portal" aria-labelledby="portalPreviewTitle">
        <div class="portal-admin-section-heading">
            <div><span class="portal-admin-eyebrow">Tampilan pengunjung</span>
                <h2 id="portalPreviewTitle">Pratinjau website publik</h2>
                <p>Halaman publik ditampilkan langsung di sini. Gunakan tombol di kanan atas untuk membuka ukuran penuh.</p>
            </div><a class="btn btn-sm btn-outline-primary" href="public/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Buka penuh</a>
        </div>
        <div class="portal-admin-preview-frame"><iframe src="public/index.php" title="Pratinjau website publik SMP Negeri 4 Pirime" loading="lazy"></iframe></div>
    </section>

    <aside class="portal-admin-publishing-note"><i class="bi bi-shield-check" aria-hidden="true"></i>
        <p><strong>Periksa sebelum menerbitkan.</strong> Pastikan informasi resmi, foto, nama, dan dokumen telah disetujui. Prestasi memerlukan verifikasi bukti serta izin publikasi. Dokumen PDF hanya untuk informasi umum, bukan berkas pribadi siswa atau pendaftar.</p>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
