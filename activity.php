<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
require_module_access('log_aktivitas');
$q = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['aksi'] ?? ''));
$entity = trim((string)($_GET['entitas'] ?? ''));
$from = trim((string)($_GET['dari'] ?? ''));
$to = trim((string)($_GET['sampai'] ?? ''));
$validDate = static function (string $d): bool {
    if ($d === '') return true;
    $v = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $v !== false && $v->format('Y-m-d') === $d;
};
$error = '';
if (!$validDate($from) || !$validDate($to) || ($from !== '' && $to !== '' && $from > $to)) $error = 'Rentang tanggal tidak valid.';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR l.detail LIKE ?)';
    for ($i = 0; $i < 3; $i++) $params[] = '%' . $q . '%';
}
if ($action !== '') {
    $where[] = 'l.aksi=?';
    $params[] = $action;
}
if ($entity !== '') {
    $where[] = 'l.entitas=?';
    $params[] = $entity;
}
if ($from !== '') {
    $where[] = 'l.created_at>=?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'l.created_at<=?';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id' . $whereSql);
$count->execute($params);
$total = (int)$count->fetchColumn();
$perPage = 40;
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $perPage;
$sql = 'SELECT l.id,l.aksi,l.entitas,l.record_id,l.detail,l.created_at,u.nama_lengkap,u.username FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id' . $whereSql . ' ORDER BY l.created_at DESC,l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    $csv = $pdo->prepare('SELECT l.created_at,COALESCE(u.nama_lengkap,u.username,\'Akun dihapus\') AS pengguna,l.aksi,l.entitas,l.record_id,l.detail FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id' . $whereSql . ' ORDER BY l.created_at DESC,l.id DESC');
    $csv->execute($params);
    header('Content-Type:text/csv; charset=utf-8');
    header('Content-Disposition:attachment; filename="sims-log-aktivitas-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Waktu', 'Pengguna', 'Tindakan', 'Modul', 'ID data', 'Ringkasan']);
    foreach ($csv as $line) {
        $line = array_map(static function ($v) {
            $s = (string)($v ?? '');
            return preg_match('/^[=+@\-\t\r]/', $s) ? "'" . $s : $s;
        }, $line);
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
$actions = $pdo->query('SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi')->fetchAll(PDO::FETCH_COLUMN);
$entities = $pdo->query('SELECT DISTINCT entitas FROM log_aktivitas ORDER BY entitas')->fetchAll(PDO::FETCH_COLUMN);
$filters = ['q' => $q, 'aksi' => $action, 'entitas' => $entity, 'dari' => $from, 'sampai' => $to];
$pageTitle = 'Log Aktivitas';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Log Aktivitas</h1>
        <p>Riwayat tindakan akun dan perubahan penting. Informasi autentikasi rahasia tidak ditampilkan.</p>
    </div><div class="d-flex gap-2"><a class="btn btn-outline-primary no-print" href="audit.php">Audit Perubahan</a><a class="btn btn-outline-success no-print" href="activity.php?<?= e(http_build_query(array_merge($filters, ['export' => 'csv']))) ?>"><i class="bi bi-download me-1"></i>Ekspor CSV</a></div>
</div>
<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?>
    </div>
<?php endif; ?>
<div class="card p-3 p-md-4">
    <form method="get" class="row g-2 align-items-end mb-3 no-print">
        <div class="col-12 col-md-4">
            <label class="form-label">Cari pengguna atau detail</label>
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nama, username, ringkasan">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Tindakan</label>
            <select class="form-select" name="aksi">
                <option value="">Semua</option>
                <?php foreach ($actions as $v): ?>
                    <option value="<?= e($v) ?>" <?= $action === $v ? 'selected' : '' ?>>
                        <?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Modul</label>
            <select class="form-select" name="entitas">
                <option value="">Semua</option>
                <?php foreach ($entities as $v): ?>
                    <option value="<?= e($v) ?>"
                        <?= $entity === $v ? 'selected' : '' ?>>
                        <?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Dari</label>
            <input type="date" class="form-control" name="dari" value="<?= e($from) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Sampai</label>
            <input type="date" class="form-control" name="sampai" value="<?= e($to) ?>">
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Terapkan</button>
            <a class="btn btn-outline-secondary" href="activity.php">Reset</a>
        </div>
    </form>

    <div class="d-flex justify-content-between mb-2">
        <span class="small text-secondary">Catatan aktivitas</span>
        <strong><?= number_format($total, 0, ',', '.') ?> hasil</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Tindakan</th>
                    <th>Modul / ID</th>
                    <th>Ringkasan</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$rows): ?><tr>
                        <td colspan="5" class="text-center text-secondary">Belum ada aktivitas yang sesuai.</td>
                    </tr><?php else: foreach ($rows as $row): ?><tr>
                            <td class="text-nowrap">
                                <?= e($row['created_at']) ?>
                            </td>
                            <td><?= e($row['nama_lengkap'] ?: $row['username'] ?: 'Akun dihapus') ?>
                                <br>
                                <small class="text-secondary">
                                    <?= e($row['username']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge text-bg-light"><?= e($row['aksi']) ?>
                                </span>
                            </td>
                            <td><?= e($row['entitas']) ?>
                                <?= $row['record_id'] ? ' · #' . (int)$row['record_id'] : '' ?>
                            </td>
                            <td>
                                <?= e($row['detail']) ?>
                            </td>
                        </tr>
                <?php endforeach;
                        endif; ?>
            </tbody>
        </table>

    </div>
    <?php if ($pages > 1): ?>
        <nav class="no-print">
            <ul class="pagination pagination-sm">
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="activity.php?<?= e(http_build_query(array_merge($filters, ['page' => $p]))) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
