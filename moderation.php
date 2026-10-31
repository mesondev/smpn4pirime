<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
require_module_access('moderasi_konten');

$workflowReady = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='konten_review_log'")->fetchColumn() === 1;
if (!$workflowReady) {
    http_response_code(503);
    exit('Alur persetujuan belum aktif. Jalankan php database/migrate.php dari terminal lokal.');
}

$entities = [
    'pengumuman' => ['table' => 'pengumuman', 'label' => 'Pengumuman'],
    'berita_sekolah' => ['table' => 'berita_sekolah', 'label' => 'Berita'],
    'galeri_sekolah' => ['table' => 'galeri_sekolah', 'label' => 'Galeri'],
    'kalender_akademik' => ['table' => 'kalender_akademik', 'label' => 'Agenda Akademik'],
];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entity = (string)($_POST['entity'] ?? '');
    $recordId = (int)($_POST['record_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $note = trim((string)($_POST['catatan'] ?? ''));
    if (!isset($entities[$entity]) || $recordId < 1 || !in_array($decision, ['approve', 'revision', 'reject'], true)) {
        $error = 'Permintaan persetujuan tidak valid.';
    } elseif ($decision !== 'approve' && $note === '') {
        $error = 'Catatan wajib diisi untuk revisi atau penolakan.';
    } elseif (mb_strlen($note, 'UTF-8') > 500) {
        $error = 'Catatan maksimal 500 karakter.';
    } else {
        $table = $entities[$entity]['table'];
        try {
            $pdo->beginTransaction();
            $lookup = $pdo->prepare("SELECT id,status,review_status FROM `$table` WHERE id=? FOR UPDATE");
            $lookup->execute([$recordId]);
            $item = $lookup->fetch();
            if (!$item || $item['review_status'] !== 'Menunggu Persetujuan') {
                throw new DomainException('Konten tidak lagi menunggu persetujuan. Muat ulang daftar.');
            }
            $targetReviewStatus = match ($decision) {
                'approve' => 'Disetujui',
                'revision' => 'Perlu Revisi',
                default => 'Ditolak',
            };
            if ($entity === 'kalender_akademik') {
                $update = $pdo->prepare("UPDATE `$table` SET tampil_publik=?,review_status=?,reviewed_at=NOW(),reviewed_by=?,review_note=? WHERE id=?");
                $update->execute([$decision === 'approve' ? 1 : 0, $targetReviewStatus, (int)$_SESSION['user_id'], $note !== '' ? $note : null, $recordId]);
            } else {
                $publicStatus = $decision === 'approve' ? 'Terbit' : 'Draft';
                $publishedAt = $decision === 'approve' && $entity === 'berita_sekolah' ? ',published_at=COALESCE(published_at,NOW())' : '';
                $update = $pdo->prepare("UPDATE `$table` SET status=?,review_status=?,reviewed_at=NOW(),reviewed_by=?,review_note=?$publishedAt WHERE id=?");
                $update->execute([$publicStatus, $targetReviewStatus, (int)$_SESSION['user_id'], $note !== '' ? $note : null, $recordId]);
            }
            $log = $pdo->prepare('INSERT INTO konten_review_log (entitas,record_id,status_awal,status_akhir,catatan,user_id) VALUES (?,?,?,?,?,?)');
            $log->execute([$entity, $recordId, $item['review_status'], $targetReviewStatus, $note !== '' ? $note : null, (int)$_SESSION['user_id']]);
            $pdo->commit();
            record_activity($pdo, 'moderasi konten', $entity, $recordId, $targetReviewStatus);
            flash($decision === 'approve' ? 'Konten disetujui dan diterbitkan.' : ($decision === 'revision' ? 'Konten dikembalikan untuk revisi.' : 'Konten ditolak.'));
            redirect('moderation.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log('error', 'Content moderation failed', ['entity' => $entity, 'record_id' => $recordId, 'type' => get_class($exception)]);
            $error = 'Perubahan status konten gagal disimpan. Periksa log aplikasi.';
        }
    }
}

$pending = [];
foreach ($entities as $entity => $metadata) {
    $table = $metadata['table'];
    $titleColumn = 'judul';
    $stmt = $pdo->prepare("SELECT c.id,c.$titleColumn AS judul,c.review_status,c.submitted_at,c.review_note,u.nama_lengkap AS penulis FROM `$table` c LEFT JOIN users u ON u.id=c.created_by WHERE c.review_status='Menunggu Persetujuan' ORDER BY c.submitted_at,c.id");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $row['entitas'] = $entity;
        $row['label'] = $metadata['label'];
        $pending[] = $row;
    }
}
usort($pending, static fn(array $a, array $b): int => strcmp((string)$a['submitted_at'], (string)$b['submitted_at']));
$pageTitle = 'Persetujuan Konten';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Persetujuan Konten</h1>
        <p>Konten draf dan yang menunggu persetujuan tidak ditampilkan pada website publik.</p>
    </div>
</div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="d-flex align-items-center gap-2 mb-3"><span class="badge text-bg-primary"><?= count($pending) ?> menunggu</span><span class="small text-secondary">Setujui untuk menerbitkan, atau berikan catatan revisi/penolakan.</span></div>
<?php if (!$pending): ?>
    <div class="card p-4 text-center text-secondary">Belum ada konten yang menunggu persetujuan.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($pending as $item): ?>
            <div class="col-12 col-xl-6">
                <article class="card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="badge text-bg-secondary"><?= e($item['label']) ?></span><small class="text-secondary"><?= e($item['submitted_at'] ?: 'Waktu tidak tersedia') ?></small></div>
                    <h2 class="h5"><?= e($item['judul']) ?></h2>
                    <p class="small text-secondary">Diajukan oleh <?= e($item['penulis'] ?: 'Akun tidak tersedia') ?></p>
                    <a class="btn btn-sm btn-outline-primary align-self-start mb-3" href="index.php?module=<?= e($item['entitas']) ?>&action=edit&id=<?= (int)$item['id'] ?>">Tinjau konten</a>
                    <form method="post" class="mt-auto">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="entity" value="<?= e($item['entitas']) ?>">
                        <input type="hidden" name="record_id" value="<?= (int)$item['id'] ?>">
                        <label class="form-label" for="review-note-<?= (int)$item['id'] ?>">Catatan pemeriksa</label>
                        <textarea class="form-control mb-3" id="review-note-<?= (int)$item['id'] ?>" name="catatan" rows="2" maxlength="500" placeholder="Wajib untuk revisi atau penolakan"></textarea>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-success" name="decision" value="approve">Setujui & Terbitkan</button>
                            <button class="btn btn-outline-primary" name="decision" value="revision">Minta Revisi</button>
                            <button class="btn btn-outline-danger" name="decision" value="reject">Tolak</button>
                        </div>
                    </form>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>