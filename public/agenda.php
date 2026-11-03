<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Agenda Akademik';
$activePage = 'agenda';
$year = max((int)date('Y') - 1, min((int)($_GET['tahun'] ?? date('Y')), (int)date('Y') + 5));
$items = portal_rows($pdo, "SELECT judul,kategori,tanggal_mulai,tanggal_selesai,deskripsi,status FROM kalender_akademik WHERE tampil_publik=1 AND YEAR(tanggal_mulai)=? AND status<>'Dibatalkan' ORDER BY tanggal_mulai", [$year]);
require __DIR__ . '/_header.php'; ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Tanggal penting</span>
        <h1>Kalender Akademik</h1>
        <p>Agenda dan kegiatan sekolah yang tercatat untuk tahun <?= $year ?>.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <form class="search-form" method="get">
            <label for="year">Tahun</label>
            <select id="year" name="tahun" style="border:1px solid var(--line);border-radius:10px;padding:10px;font:inherit">
                <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 5; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>
                        <?= $y ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="submit">Tampilkan
            </button>
        </form>
        <div class="card">
            <?php if (!$items): ?>
                <?php public_empty_panel('calendar2-event', 'Belum ada agenda untuk tahun ini', 'Agenda akademik akan ditampilkan setelah tanggal dan kegiatannya dikonfirmasi sekolah.'); ?>
                <?php else: foreach ($items as $ev): ?>
                    <div class="agenda-row">
                        <div class="agenda-date">
                            <?= e(portal_date($ev['tanggal_mulai'], 'd M')) ?>
                            <?= $ev['tanggal_selesai'] ? ' – ' . e(portal_date($ev['tanggal_selesai'], 'd M')) : '' ?></div>
                        <div><span class="date-label">
                                <?= e($ev['kategori']) ?> ·
                                <?= e($ev['status']) ?>
                            </span>
                            <h3><?= e($ev['judul']) ?></h3>
                            <?php if ($ev['deskripsi']): ?>
                                <p>
                                    <?= e($ev['deskripsi']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
