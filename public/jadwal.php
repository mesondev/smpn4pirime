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
$schoolYear = trim(mb_substr((string)($_GET['tahun_ajaran'] ?? ''), 0, 20, 'UTF-8'));
$day = (string)($_GET['hari'] ?? '');
$allowedDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
if (!in_array($day, $allowedDays, true)) $day = '';

$items = [];
$yearOptions = [];
if ($ready) {
    $yearOptions = array_column(portal_rows($pdo, "SELECT DISTINCT tahun_ajaran FROM portal_jadwal_publik WHERE status='Terbit' ORDER BY tahun_ajaran DESC"), 'tahun_ajaran');
    if ($schoolYear !== '' && !in_array($schoolYear, $yearOptions, true)) $schoolYear = '';
    $sql = "SELECT tahun_ajaran, tingkat, hari, jam_mulai, jam_selesai, mata_pelajaran
        FROM portal_jadwal_publik
        WHERE status='Terbit'";
    $params = [];
    if ($schoolYear !== '') { $sql .= ' AND tahun_ajaran=?'; $params[] = $schoolYear; }
    if ($grade !== '') {
        $sql .= ' AND tingkat=?';
        $params[] = $grade;
    }
    if ($day !== '') { $sql .= ' AND hari=?'; $params[] = $day; }
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
                <div class="schedule-filter-field"><label for="schoolYear">Tahun ajaran</label><select id="schoolYear" name="tahun_ajaran"><option value="">Semua tahun</option><?php foreach ($yearOptions as $yearOption): ?><option value="<?= e($yearOption) ?>" <?= $schoolYear === $yearOption ? 'selected' : '' ?>><?= e($yearOption) ?></option><?php endforeach; ?></select></div>
                <div class="schedule-filter-field"><label for="grade">Tingkat</label><select id="grade" name="tingkat">
                    <option value="">Semua tingkat</option>
                    <?php foreach (['7', '8', '9'] as $level): ?>
                        <option value="<?= e($level) ?>" <?= $grade === $level ? 'selected' : '' ?>>Kelas <?= e($level) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="schedule-filter-field"><label for="scheduleDay">Hari</label><select id="scheduleDay" name="hari"><option value="">Semua hari</option><?php foreach ($allowedDays as $dayOption): ?><option value="<?= e($dayOption) ?>" <?= $day === $dayOption ? 'selected' : '' ?>><?= e($dayOption) ?></option><?php endforeach; ?></select></div>
                <button class="btn-primary" type="submit">Tampilkan</button>
            </form>
            <p class="listing-summary" aria-live="polite">Jadwal terbit yang cocok: <?= number_format(count($items), 0, ',', '.') ?> baris.</p>

            <?php if (!$items): ?>
                <div class="public-empty-state public-empty-state--wide public-empty-state--text">
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
