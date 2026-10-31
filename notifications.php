<?php

declare(strict_types=1);

require_module_access('notifikasi');
$userId = (int)($_SESSION['user_id'] ?? 0);
$canSeeLegacy = in_array(current_role(), ['admin', 'operator'], true);
$hasRecipient = db_has_column($pdo, 'notifikasi', 'recipient_user_id');
$hasArchive = db_has_column($pdo, 'notifikasi', 'archived_at');
$scopeSql = $hasRecipient ? '(recipient_user_id=?' . ($canSeeLegacy ? ' OR recipient_user_id IS NULL' : '') . ')' : ($canSeeLegacy ? '1=1' : '1=0');
$scopeParams = $hasRecipient ? [$userId] : [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $noticeId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['notice_action'] ?? '');
    if (!$noticeId || !in_array($action, ['read', 'unread', 'archive', 'restore'], true) || (!$hasArchive && in_array($action, ['archive', 'restore'], true))) {
        $error = 'Tindakan notifikasi tidak valid.';
    } else {
        $stateSql = match ($action) {
            'read' => 'dibaca=1',
            'unread' => 'dibaca=0',
            'archive' => 'archived_at=NOW()',
            'restore' => 'archived_at=NULL',
        };
        $update = $pdo->prepare("UPDATE notifikasi SET $stateSql WHERE id=? AND $scopeSql");
        $update->execute([$noticeId, ...$scopeParams]);
        if ($update->rowCount() < 1) $error = 'Notifikasi tidak ditemukan atau bukan milik akun ini.';
        else {
            record_activity($pdo, 'ubah status notifikasi', 'notifikasi', $noticeId, $action);
            redirect('index.php?module=notifikasi&status=' . rawurlencode((string)($_GET['status'] ?? 'all')) . '&q=' . rawurlencode((string)($_GET['q'] ?? '')) . '&page=' . max(1, (int)($_GET['page'] ?? 1)));
        }
    }
}

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'unread', 'read', 'archived'], true)) $status = 'all';
$archiveUnavailable = !$hasArchive && $status === 'archived';
if ($archiveUnavailable) $status = 'all';
$query = trim((string)($_GET['q'] ?? ''));
$where = [$scopeSql];
$params = $scopeParams;
if ($hasArchive) {
    if ($status === 'archived') $where[] = 'archived_at IS NOT NULL';
    else $where[] = 'archived_at IS NULL';
}
if ($status !== 'archived') {
    if ($status === 'unread') $where[] = 'dibaca=0';
    if ($status === 'read') $where[] = 'dibaca=1';
}
if ($query !== '') {
    $where[] = '(judul LIKE ? OR pesan LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$perPage = 15;
$pageCount = max(1, (int)ceil($total / $perPage));
$page = min($pageCount, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$archiveSelect = $hasArchive ? 'archived_at' : 'NULL AS archived_at';
$list = $pdo->prepare("SELECT id,judul,pesan,tautan,dibaca,$archiveSelect,created_at FROM notifikasi WHERE $whereSql ORDER BY created_at DESC,id DESC LIMIT $perPage OFFSET $offset");
$list->execute($params);
$notifications = $list->fetchAll();
$pageTitle = 'Notifikasi';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Notifikasi</h1>
        <p>Pesan dan pembaruan yang ditujukan kepada akun Anda.</p>
    </div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-warning" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($archiveUnavailable): ?><div class="alert alert-info" role="status">Fitur arsip belum tersedia pada database ini. Terapkan migrasi notifikasi untuk mengaktifkannya.</div><?php endif; ?>
<div class="card p-3 p-md-4">
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter status notifikasi">
        <?php foreach (['all' => 'Semua', 'unread' => 'Belum dibaca', 'read' => 'Sudah dibaca', 'archived' => 'Diarsipkan'] as $key => $label): if ($key === 'archived' && !$hasArchive) continue; ?><a class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline-primary' ?>" href="index.php?module=notifikasi&status=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <form method="get" class="row g-2 mb-3 no-print"><input type="hidden" name="module" value="notifikasi"><input type="hidden" name="status" value="<?= e($status) ?>">
        <div class="col-sm-9"><label for="notificationSearch" class="visually-hidden">Cari notifikasi</label><input class="form-control" id="notificationSearch" name="q" value="<?= e($query) ?>" placeholder="Cari judul atau isi notifikasi"></div>
        <div class="col-sm-3 d-grid"><button class="btn btn-outline-primary">Cari</button></div>
    </form>
    <p class="small text-secondary">Menampilkan <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> dari <?= number_format($total, 0, ',', '.') ?> notifikasi.</p>
    <?php if (!$notifications): ?><div class="empty-state">Tidak ada notifikasi pada filter ini.</div><?php else: ?><div class="notification-inbox-list">
            <?php foreach ($notifications as $notice): $safeTarget = (string)($notice['tautan'] ?? '');
                                                                                                            $safeTarget = preg_match('~^(?:index\.php|reports\.php|student\.php|teacher\.php|learning\.php|public/[a-zA-Z0-9_.-]+\.php)(?:\?[a-zA-Z0-9_=&%.-]*)?$~', $safeTarget) ? $safeTarget : ''; ?>
                <article class="notification-inbox-item<?= (int)$notice['dibaca'] === 0 && $status !== 'archived' ? ' is-unread' : '' ?>">
                    <div class="notification-inbox-copy">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h2 class="h6 mb-0"><?= e($notice['judul']) ?></h2><?php if ((int)$notice['dibaca'] === 0 && !$notice['archived_at']): ?><span class="badge text-bg-primary">Baru</span><?php endif; ?>
                        </div>
                        <p><?= nl2br(e($notice['pesan'])) ?></p><small><?= e(date('d M Y · H:i', strtotime($notice['created_at']))) ?></small><?php if ($safeTarget !== ''): ?><a class="notification-inbox-target" href="<?= e($safeTarget) ?>">Buka halaman terkait <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                    </div>
                    <div class="notification-inbox-actions no-print">
                        <?php if ($notice['archived_at']): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$notice['id'] ?>"><button class="btn btn-sm btn-outline-primary" name="notice_action" value="restore">Pulihkan</button></form>
                        <?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$notice['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="notice_action" value="<?= (int)$notice['dibaca'] === 1 ? 'unread' : 'read' ?>"><?= (int)$notice['dibaca'] === 1 ? 'Tandai belum dibaca' : 'Tandai dibaca' ?></button></form>
                            <?php if ($hasArchive): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$notice['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="notice_action" value="archive">Arsipkan</button></form><?php endif; ?><?php endif; ?>
                    </div>
                </article><?php endforeach; ?>
        </div><?php endif; ?>
    <?php if ($pageCount > 1): ?><nav class="mt-3" aria-label="Halaman notifikasi">
            <ul class="pagination pagination-sm justify-content-center mb-0"><?php for ($n = 1; $n <= $pageCount; $n++): ?><li class="page-item<?= $n === $page ? ' active' : '' ?>"><a class="page-link" href="index.php?module=notifikasi&status=<?= e($status) ?>&q=<?= rawurlencode($query) ?>&page=<?= $n ?>"><?= $n ?></a></li><?php endfor; ?></ul>
        </nav><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>