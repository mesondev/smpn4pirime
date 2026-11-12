<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';

$role = current_role();
$isAdmin = $role === 'admin';
if (!$isAdmin && $role !== 'operator') {
    http_response_code(403);
    exit('Halaman ini hanya untuk Super Admin dan Operator.');
}
$userId = (int)$_SESSION['user_id'];
$error = '';
$historyCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='password_reset_history'");
$historyCheck->execute();
$historyReady = (int)$historyCheck->fetchColumn() === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$historyReady) {
        $error = 'Riwayat reset password belum aktif. Jalankan migrasi database setelah membuat backup.';
    } else {
        $targetId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT) ?: 0;
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($targetId < 1 || $targetId === $userId) {
            $error = 'Pilih akun lain yang valid untuk direset.';
        } elseif (mb_strlen($reason) > 255) {
            $error = 'Keterangan reset maksimal 255 karakter.';
        } else {
            try {
                $pdo->beginTransaction();
                $targetStmt = $pdo->prepare('SELECT id,role,aktif FROM users WHERE id=? LIMIT 1 FOR UPDATE');
                $targetStmt->execute([$targetId]);
                $target = $targetStmt->fetch();
                $allowedTarget = $target && ($isAdmin || in_array($target['role'], ['kepala_sekolah', 'guru', 'siswa'], true));
                if (!$allowedTarget) {
                    throw new InvalidArgumentException('Operator hanya dapat mereset akun Guru, Kepala Sekolah, atau Siswa.');
                }
                if ((int)$target['aktif'] !== 1) {
                    throw new InvalidArgumentException('Akun nonaktif tidak dapat direset. Aktifkan akun melalui Super Admin terlebih dahulu.');
                }
                $temporaryPassword = strtoupper(bin2hex(random_bytes(8)));
                $update = $pdo->prepare('UPDATE users SET password_hash=?,password_must_change=1 WHERE id=?');
                $update->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $targetId]);
                $history = $pdo->prepare('INSERT INTO password_reset_history (target_user_id,changed_by,target_role,reason) VALUES (?,?,?,?)');
                $history->execute([$targetId, $userId, $target['role'], $reason !== '' ? mb_substr($reason, 0, 255) : null]);
                record_activity($pdo, 'reset password', 'users', $targetId, $target['role']);
                $pdo->commit();
                flash('Password sementara akun berhasil dibuat: ' . $temporaryPassword . '. Berikan secara pribadi kepada pengguna; password wajib diganti setelah login.', 'success');
                redirect('passwords.php');
            } catch (InvalidArgumentException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                app_log('error', 'Password reset failed', ['target_user_id' => $targetId]);
                $error = 'Reset password gagal. Tidak ada password yang dicatat pada log. Silakan coba kembali.';
            }
        }
    }
}

$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$filterRole = (string)($_GET['role'] ?? '');
$allowedFilterRoles = $isAdmin ? ['operator', 'kepala_sekolah', 'guru', 'siswa', 'admin'] : ['kepala_sekolah', 'guru', 'siswa'];
if (!in_array($filterRole, $allowedFilterRoles, true)) $filterRole = '';
$where = ['u.aktif=1', 'u.id<>?'];
$params = [$userId];
if (!$isAdmin) $where[] = "u.role IN ('kepala_sekolah','guru','siswa')";
if ($filterRole !== '') {
    $where[] = 'u.role=?';
    $params[] = $filterRole;
}
if ($query !== '') {
    $where[] = '(u.username LIKE ? OR u.nama_lengkap LIKE ? OR g.nama LIKE ? OR g.nip LIKE ? OR s.nama LIKE ? OR s.nis LIKE ?)';
    for ($i = 0; $i < 6; $i++) $params[] = '%' . $query . '%';
}
$stmt = $pdo->prepare('SELECT u.id,u.username,u.nama_lengkap,u.role,g.nip,s.nis FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.nama_lengkap LIMIT 300');
$stmt->execute($params);
$accounts = $stmt->fetchAll();
$resetHistory = [];
if ($historyReady) {
    $historySql = 'SELECT h.changed_at,h.target_role,h.reason,t.nama_lengkap AS target_name,a.nama_lengkap AS actor_name FROM password_reset_history h JOIN users t ON t.id=h.target_user_id LEFT JOIN users a ON a.id=h.changed_by';
    if (!$isAdmin) $historySql .= " WHERE h.target_role IN ('kepala_sekolah','guru','siswa')";
    $historySql .= ' ORDER BY h.changed_at DESC,h.id DESC LIMIT 30';
    $resetHistory = $pdo->query($historySql)->fetchAll();
}
$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Reset Password</h1>
        <p>Terbitkan password sementara untuk akun yang memerlukan bantuan masuk.</p>
    </div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$historyReady): ?><div class="alert alert-warning" role="alert"><strong>Audit reset belum aktif.</strong> Setelah backup database dibuat dan diperiksa, jalankan <code>php database/migrate.php</code>. Reset dinonaktifkan sampai riwayat audit tersedia.</div><?php endif; ?>
<section class="card p-3 p-md-4">
    <p class="text-secondary">Password baru dibuat acak dan tidak dapat dilihat kembali setelah halaman ditutup. Sampaikan kepada pengguna lewat saluran pribadi. Sistem mewajibkan password diganti saat login selanjutnya. Password tidak disimpan ke riwayat reset.</p>
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-6"><label class="form-label" for="accountSearch">Cari akun</label><input class="form-control" id="accountSearch" name="q" value="<?= e($query) ?>" placeholder="Nama, username, NIS, atau NIP"></div>
        <div class="col-8 col-md-3"><label class="form-label" for="accountRole">Peran</label><select class="form-select" id="accountRole" name="role">
                <option value="">Semua peran yang diizinkan</option><?php foreach ($allowedFilterRoles as $item): ?><option value="<?= e($item) ?>" <?= $filterRole === $item ? 'selected' : '' ?>><?= e(role_label($item)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-4 col-md-3 d-flex gap-2"><button class="btn btn-outline-primary">Cari</button><a class="btn btn-outline-secondary" href="passwords.php">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Identitas sekolah</th>
                    <th>Peran</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$accounts): ?><tr>
                        <td colspan="4" class="text-center text-secondary py-4">Tidak ada akun aktif yang sesuai dengan pencarian.</td>
                    </tr><?php endif; ?>
                <?php foreach ($accounts as $account): ?><tr>
                        <td><strong><?= e($account['nama_lengkap']) ?></strong>
                            <div class="small text-secondary"><?= e($account['username']) ?></div>
                        </td>
                        <td><?= e($account['nis'] ?: ($account['nip'] ?: '—')) ?></td>
                        <td><?= e(role_label($account['role'])) ?></td>
                        <td>
                            <form method="post" class="d-flex flex-wrap gap-2 align-items-center" onsubmit="return confirm('Buat password sementara baru untuk akun ini? Password lama tidak dapat digunakan setelah reset.')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="target_user_id" value="<?= (int)$account['id'] ?>"><input class="form-control form-control-sm" style="max-width:220px" name="reason" maxlength="255" placeholder="Keterangan (opsional)" aria-label="Keterangan reset untuk <?= e($account['nama_lengkap']) ?>"><button class="btn btn-sm btn-outline-primary" type="submit" <?= !$historyReady ? 'disabled' : '' ?>><i class="bi bi-key me-1" aria-hidden="true"></i>Reset Password</button></form>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($historyReady): ?><section class="card p-3 p-md-4 mt-3">
        <h2 class="h5">Riwayat reset terbaru</h2>
        <p class="small text-secondary">Riwayat mencatat siapa yang meminta reset dan kapan; password tidak pernah ditampilkan atau disimpan di sini.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Akun</th>
                        <th>Peran</th>
                        <th>Direset oleh</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody><?php if (!$resetHistory): ?><tr>
                            <td colspan="5" class="text-center text-secondary">Belum ada reset password.</td>
                        </tr><?php endif; ?><?php foreach ($resetHistory as $entry): ?><tr>
                            <td><?= e($entry['changed_at']) ?></td>
                            <td><?= e($entry['target_name']) ?></td>
                            <td><?= e(role_label($entry['target_role'])) ?></td>
                            <td><?= e($entry['actor_name'] ?? 'Akun dihapus') ?></td>
                            <td><?= e($entry['reason'] ?: '—') ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>