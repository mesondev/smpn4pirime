<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$pageTitle = 'Jadwal Pelajaran';
$activePage = 'jadwal';
$ready = portal_schema_ready($pdo, 'portal_jadwal_publik', [
    'tahun_ajaran',
    'tingkat',
    'hari',
    'jam_mulai',
    'jam_selesai',
    'mata_pelajaran',
    'status',
]);
$grade = isset($_GET['tingkat']) && in_array((string)$_GET['tingkat'], ['7', '8', '9'], true)
    ? (string)$_GET['tingkat']
    : '';

$items = [];
if ($ready) {
    $sql = "SELECT tahun_ajaran, tingkat, hari, jam_mulai, jam_selesai, mata_pelajaran
        FROM portal_jadwal_publik
        WHERE status='Terbit'";
    $params = [];
    if ($grade !== '') {
        $sql .= ' AND tingkat=?';
        $params[] = $grade;
    }
    $sql .= " ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jam_mulai, tingkat";
    $items = portal_rows($pdo, $sql, $params);
}

require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Informasi akademik</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Jadwal kelas yang telah ditinjau dan secara khusus disetujui untuk ditampilkan kepada publik.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <?php if (!$ready): ?>
            <?php public_service_notice('Jadwal pelajaran belum dapat dimuat. Jadwal akan ditampilkan setelah ditinjau dan disetujui sekolah.'); ?>
        <?php else: ?>
            <form class="public-form schedule-filter" method="get">
                <label for="grade">Pilih tingkat</label>
                <select id="grade" name="tingkat">
                    <option value="">Semua tingkat</option>
                    <?php foreach (['7', '8', '9'] as $level): ?>
                        <option value="<?= e($level) ?>" <?= $grade === $level ? 'selected' : '' ?>>Kelas <?= e($level) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary" type="submit">Tampilkan</button>
            </form>

            <?php if (!$items): ?>
                <div class="public-empty-state public-empty-state--wide">
                    <span class="public-empty-icon"><i class="bi bi-clock" aria-hidden="true"></i></span>
                    <div class="public-empty-copy">
                        <strong>Belum ada jadwal publik</strong>
                        <p>Jadwal yang telah ditinjau sekolah akan ditampilkan di sini.</p>
                        <div class="public-empty-actions"><a class="text-link" href="kontak.php">Tanyakan ke sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a><a class="text-link" href="akademik.php">Lihat informasi akademik <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="public-schedule-table">
                        <caption>Jadwal yang disetujui untuk publikasi</caption>
                        <thead>
                            <tr>
                                <th scope="col">Tahun ajaran</th>
                                <th scope="col">Tingkat</th>
                                <th scope="col">Hari</th>
                                <th scope="col">Waktu</th>
                                <th scope="col">Mata pelajaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['tahun_ajaran']) ?></td>
                                    <td><?= e($item['tingkat']) ?></td>
                                    <td><?= e($item['hari']) ?></td>
                                    <td><?= e(substr($item['jam_mulai'], 0, 5)) ?>–<?= e(substr($item['jam_selesai'], 0, 5)) ?></td>
                                    <td><?= e($item['mata_pelajaran']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>