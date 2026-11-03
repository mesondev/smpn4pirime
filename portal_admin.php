<?php

declare(strict_types=1);

require_module_access('portal_admin');
require_once __DIR__ . '/public/_common.php';

$pageTitle = 'Ringkasan Portal Publik';
$contentModules = [
    ['module' => 'berita_sekolah', 'title' => 'Berita sekolah', 'table' => 'berita_sekolah', 'icon' => 'bi-newspaper'],
    ['module' => 'pengumuman', 'title' => 'Pengumuman', 'table' => 'pengumuman', 'icon' => 'bi-megaphone'],
    ['module' => 'galeri_sekolah', 'title' => 'Galeri kegiatan', 'table' => 'galeri_sekolah', 'icon' => 'bi-images'],
    ['module' => 'portal_ekstrakurikuler', 'title' => 'Ekstrakurikuler', 'table' => 'portal_ekstrakurikuler', 'icon' => 'bi-stars'],
    ['module' => 'portal_fasilitas', 'title' => 'Fasilitas', 'table' => 'portal_fasilitas', 'icon' => 'bi-buildings'],
    ['module' => 'portal_struktur_sekolah', 'title' => 'Struktur organisasi', 'table' => 'portal_struktur_sekolah', 'icon' => 'bi-diagram-3'],
    ['module' => 'portal_prestasi_publik', 'title' => 'Prestasi publik', 'table' => 'portal_prestasi_publik', 'icon' => 'bi-award'],
    ['module' => 'portal_jadwal_publik', 'title' => 'Jadwal publik', 'table' => 'portal_jadwal_publik', 'icon' => 'bi-calendar-week'],
    ['module' => 'portal_dokumen_publik', 'title' => 'Dokumen unduhan', 'table' => 'portal_dokumen_publik', 'icon' => 'bi-file-earmark-pdf'],
    ['module' => 'portal_tautan_sosial', 'title' => 'Media sosial', 'table' => 'portal_tautan_sosial', 'icon' => 'bi-share'],
];
$contentSummary = [];
$totalDrafts = 0;
foreach ($contentModules as $module) {
    if ($module['table'] === 'portal_tautan_sosial') {
        $ready = portal_schema_ready($pdo, $module['table'], ['platform', 'url', 'status']);
        $row = $ready ? portal_row($pdo, "SELECT COUNT(*) AS total,SUM(status='aktif') AS terbit FROM `{$module['table']}`") : [];
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
        <div><span class="portal-admin-eyebrow">Administrasi website sekolah</span><h1>Ringkasan Portal Publik</h1><p>Periksa status konten dan buka pengelolaan yang sesuai.</p></div>
        <a class="btn btn-outline-primary" href="public/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Buka website publik</a>
    </header>

    <section class="portal-admin-kpis" aria-label="Ringkasan layanan portal">
        <article><span><i class="bi bi-pencil-square" aria-hidden="true"></i> Konten draf</span><strong><?= number_format($totalDrafts) ?></strong><small>Belum diterbitkan</small></article>
        <article><span><i class="bi bi-chat-left-text" aria-hidden="true"></i> Pesan ditindaklanjuti</span><strong><?= $pendingMessages === null ? '—' : number_format($pendingMessages) ?></strong><small>Masuk, dibaca, atau diproses</small><a href="index.php?module=pesan_masuk">Buka pesan masuk</a></article>
        <article><span><i class="bi bi-person-check" aria-hidden="true"></i> PPDB menunggu</span><strong><?= $pendingApplicants === null ? '—' : number_format($pendingApplicants) ?></strong><small>Dalam verifikasi</small><a href="index.php?module=ppdb_admin">Buka pengelolaan PPDB</a></article>
        <article><span><i class="bi bi-calendar2-check" aria-hidden="true"></i> Periode PPDB aktif</span><strong><?= $activeAdmissionPeriods === null ? '—' : number_format($activeAdmissionPeriods) ?></strong><small>Periksa tanggal dan status</small><a href="index.php?module=ppdb_admin&tab=periode">Kelola periode</a></article>
    </section>

    <section class="portal-admin-section" aria-labelledby="portalContentTitle">
        <div class="portal-admin-section-heading"><div><h2 id="portalContentTitle">Konten dan layanan publik</h2><p>Data hanya tampil di website setelah status dan syarat publikasinya terpenuhi.</p></div></div>
        <div class="portal-admin-modules">
            <?php foreach ($contentSummary as $item): ?>
                <article class="portal-admin-module">
                    <span class="portal-admin-module-icon"><i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i></span>
                    <div class="portal-admin-module-copy"><h3><?= e($item['title']) ?></h3>
                        <?php if ($item['ready']): ?><p><?= number_format($item['terbit']) ?> aktif · <?= number_format($item['draf']) ?> draf · <?= number_format($item['total']) ?> total</p><?php else: ?><p class="text-warning-emphasis">Skema belum tersedia; jalankan migrasi layanan publik.</p><?php endif; ?>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="index.php?module=<?= e($item['module']) ?>"><?= $item['ready'] ? 'Kelola' : 'Periksa' ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="portal-admin-publishing-note"><i class="bi bi-shield-check" aria-hidden="true"></i><p><strong>Periksa sebelum menerbitkan.</strong> Pastikan informasi resmi, foto, nama, dan dokumen telah disetujui. Prestasi memerlukan verifikasi bukti serta izin publikasi. Dokumen PDF hanya untuk informasi umum, bukan berkas pribadi siswa atau pendaftar.</p></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
