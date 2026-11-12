<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
require_module_access('log_aktivitas');
if (!db_has_column($pdo, 'audit_logs', 'old_data')) {
    http_response_code(503);
    exit('Tabel audit perubahan belum tersedia. Jalankan migrasi database terlebih dahulu.');
}

$query = trim((string)($_GET['q'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$where = [];
$params = [];
if ($query !== '') {
    $where[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR a.action_name LIKE ? OR a.module_key LIKE ? OR CAST(a.record_id AS CHAR) LIKE ?)';
    for ($i = 0; $i < 5; $i++) $params[] = '%' . $query . '%';
}
if ($module !== '' && preg_match('/^[a-z0-9_]+$/', $module)) {
    $where[] = 'a.module_key=?';
    $params[] = $module;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id' . $whereSql);
$count->execute($params);
$total = (int)$count->fetchColumn();
$perPage = 30;
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare('SELECT a.id,a.action_name,a.module_key,a.record_id,a.old_data,a.new_data,a.ip_address,a.user_agent,a.created_at,u.nama_lengkap,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id' . $whereSql . ' ORDER BY a.created_at DESC,a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$modulesList = $pdo->query('SELECT DISTINCT module_key FROM audit_logs ORDER BY module_key')->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = 'Audit Perubahan Data';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading"><div><h1>Audit Perubahan Data</h1><p>Perubahan record, pelaku, waktu, dan konteks permintaan. Nilai pribadi dan kredensial disaring sebelum disimpan.</p></div><a href="activity.php" class="btn btn-outline-secondary">Log Aktivitas</a></div>
<div class="card p-3 p-md-4">
    <form method="get" class="row g-2 align-items-end mb-3 no-print">
        <div class="col-12 col-md-7"><label for="auditQuery" class="form-label">Cari pelaku, tindakan, modul, atau ID data</label><input class="form-control" id="auditQuery" name="q" value="<?= e($query) ?>" placeholder="Ketik kata pencarian"></div>
        <div class="col-8 col-md-3"><label for="auditModule" class="form-label">Modul</label><select class="form-select" id="auditModule" name="module"><option value="">Semua modul</option><?php foreach ($modulesList as $item): ?><option value="<?= e($item) ?>" <?= $module === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
        <div class="col-4 col-md-2 d-flex gap-2"><button class="btn btn-primary">Cari</button><a class="btn btn-outline-secondary" href="audit.php">Reset</a></div>
    </form>
    <p class="small text-secondary"><?= number_format($total, 0, ',', '.') ?> perubahan tercatat.</p>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Modul / Record</th><th>Konteks</th><th>Perubahan</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada perubahan yang sesuai.</td></tr><?php else: foreach ($rows as $row): ?>
            <tr><td class="text-nowrap"><?= e($row['created_at']) ?></td><td><?= e($row['nama_lengkap'] ?: $row['username'] ?: 'Akun dihapus') ?></td><td><?= e($row['action_name']) ?></td><td><?= e($row['module_key']) ?><br><small class="text-secondary">ID <?= e($row['record_id'] ?? '-') ?></small></td><td><small>IP: <?= e($row['ip_address'] ?? '-') ?><br><?= e($row['user_agent'] ?? '-') ?></small></td><td>
                <?php if ($row['old_data'] === null && $row['new_data'] === null): ?>—<?php else: ?><details><summary>Lihat ringkasan aman</summary><div class="row g-2 mt-1"><div class="col-12"><strong>Nilai sebelumnya</strong><pre class="small bg-body-tertiary border rounded p-2 mb-1 audit-json"><?= e($row['old_data'] ?: '—') ?></pre></div><div class="col-12"><strong>Nilai baru</strong><pre class="small bg-body-tertiary border rounded p-2 mb-0 audit-json"><?= e($row['new_data'] ?: '—') ?></pre></div></div></details><?php endif; ?>
            </td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
    <?php if ($pages > 1): ?><nav aria-label="Halaman audit"><ul class="pagination pagination-sm mb-0"><?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?><li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="audit.php?<?= e(http_build_query(['q' => $query, 'module' => $module, 'page' => $i])) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</div>
<style>.audit-json{max-width:34rem;max-height:14rem;overflow:auto;white-space:pre-wrap;word-break:break-word}</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
