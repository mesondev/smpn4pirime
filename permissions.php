<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/modules/config.php';
require_login();
require_once __DIR__ . '/config/database.php';
if (current_role() !== 'admin') {
    http_response_code(403);
    exit('Hanya Super Admin yang dapat mengatur hak akses.');
}

$schemaReady = db_has_column($pdo, 'roles', 'slug')
    && db_has_column($pdo, 'permissions', 'module_key')
    && db_has_column($pdo, 'role_permissions', 'granted');
if (!$schemaReady) {
    http_response_code(503);
    exit('Skema hak akses belum tersedia. Jalankan migrasi database setelah backup terverifikasi.');
}

$catalog = [];
foreach ($modules as $key => $config) $catalog[$key] = (string)($config['title'] ?? field_label($key));
foreach ([
    'pengguna' => 'Manajemen Pengguna',
    'log_aktivitas' => 'Log Aktivitas',
    'profil' => 'Profil Pengguna',
    'moderasi_konten' => 'Moderasi Konten',
    'portal_admin' => 'Ringkasan Portal',
    'ppdb_admin' => 'Pengelolaan PPDB',
    'pesan_masuk' => 'Pesan Masuk',
    'permissions' => 'Pengaturan Hak Akses',
] as $key => $label) $catalog[$key] = $label;
asort($catalog, SORT_NATURAL | SORT_FLAG_CASE);

$roles = $pdo->query("SELECT id,slug,nama,system_locked FROM roles WHERE slug<>'admin' ORDER BY id")->fetchAll();
$roleIds = [];
foreach ($roles as $role) $roleIds[(string)$role['slug']] = (int)$role['id'];
$lockedModules = ['pengguna', 'permissions', 'profil'];
$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $requested = $_POST['grants'] ?? [];
        if (!is_array($requested)) throw new InvalidArgumentException('Format izin tidak valid.');
        $pdo->beginTransaction();
        foreach ($roleIds as $roleSlug => $roleId) {
            $roleGrants = $requested[$roleSlug] ?? [];
            if (!is_array($roleGrants)) throw new InvalidArgumentException('Data izin peran tidak valid.');
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id=? AND permission_id IN (SELECT id FROM permissions WHERE module_key NOT IN ('pengguna','permissions','profil'))")->execute([$roleId]);
            foreach ($catalog as $moduleKey => $_label) {
                if (in_array($moduleKey, $lockedModules, true)) continue;
                $selected = $roleGrants[$moduleKey] ?? [];
                if (!is_array($selected)) continue;
                $canView = isset($selected['view']) && (string)$selected['view'] === '1';
                $canManage = isset($selected['manage']) && (string)$selected['manage'] === '1';
                if ($canManage) $canView = true;
                foreach (['view' => $canView, 'manage' => $canManage] as $action => $granted) {
                    if (!$granted) continue;
                    $insert = $pdo->prepare('INSERT INTO role_permissions (role_id,permission_id,granted) SELECT ?,id,1 FROM permissions WHERE module_key=? AND action_name=?');
                    $insert->execute([$roleId, $moduleKey, $action]);
                }
            }
        }
        $pdo->commit();
        record_activity($pdo, 'mengubah hak akses peran', 'role_permissions', null, 'Hak akses peran diperbarui dari halaman pengaturan.');
        $flashMessage = ['type' => 'success', 'message' => 'Hak akses berhasil disimpan. Perubahan berlaku pada permintaan berikutnya.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!$exception instanceof PDOException && !$exception instanceof InvalidArgumentException) app_log('error', 'RBAC update failed', ['type' => get_class($exception)]);
        $flashMessage = ['type' => 'danger', 'message' => 'Hak akses gagal disimpan. Periksa kembali dan coba lagi.'];
    }
}

$grantRows = $pdo->query('SELECT r.slug,p.module_key,p.action_name FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE rp.granted=1')->fetchAll();
$grants = [];
foreach ($grantRows as $grant) $grants[$grant['slug']][$grant['module_key']][$grant['action_name']] = true;
$pageTitle = 'Hak Akses Dinamis';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">Hak Akses Dinamis</h1><p class="text-secondary mb-0">Atur hak baca dan kelola per modul untuk setiap peran SIMS.</p></div>
    <span class="badge rounded-pill text-bg-primary py-2 px-3">Super Admin tetap memiliki akses penuh</span>
</div>
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
<div class="alert alert-info"><strong>Arti izin:</strong> <em>Lihat</em> membuka modul baca saja; <em>Kelola</em> mengizinkan tambah, edit, dan hapus. Izin Kelola otomatis mencakup Lihat. Rute yang khusus dilindungi tetap mengikuti pemeriksaan keamanan aplikasinya.</div>
<form method="post" class="card p-3 p-md-4">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="table-responsive">
        <table class="table table-hover align-middle permission-matrix">
            <thead class="table-light"><tr><th scope="col">Modul</th><?php foreach ($roles as $role): ?><th scope="col" class="text-center"><?= e($role['nama']) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($catalog as $moduleKey => $label): ?><tr>
                <th scope="row" class="text-nowrap"><?= e($label) ?><small class="d-block text-secondary fw-normal"><?= e($moduleKey) ?></small></th>
                <?php foreach ($roles as $role): $slug = (string)$role['slug']; ?>
                    <td data-role="<?= e($role['nama']) ?>"><span class="permission-role-name"><?= e($role['nama']) ?></span><?php if (in_array($moduleKey, $lockedModules, true)): ?><span class="small text-secondary">Khusus Super Admin / akun sendiri</span><?php else: ?><div class="d-flex justify-content-center gap-3 flex-wrap">
                        <?php foreach (['view' => 'Lihat', 'manage' => 'Kelola'] as $action => $actionLabel): ?><label class="form-check-label d-inline-flex align-items-center gap-1"><input class="form-check-input mt-0" type="checkbox" name="grants[<?= e($slug) ?>][<?= e($moduleKey) ?>][<?= e($action) ?>]" value="1" <?= !empty($grants[$slug][$moduleKey][$action]) ? 'checked' : '' ?> aria-label="<?= e($actionLabel . ' ' . $label . ' untuk ' . $role['nama']) ?>"><span><?= e($actionLabel) ?></span></label><?php endforeach; ?>
                    </div><?php endif; ?></td>
                <?php endforeach; ?>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="small text-secondary">Hak Admin terkunci untuk mencegah sistem kehilangan akun pengelola.</span><button class="btn btn-primary"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Simpan Hak Akses</button></div>
</form>
<style>
.permission-role-name{display:block;margin-bottom:.2rem;text-align:center;font-size:.72rem;font-weight:600;color:var(--bs-secondary-color)}
@media (max-width: 767.98px) {
    .permission-matrix,.permission-matrix tbody{display:block;width:100%}
    .permission-matrix thead{display:none}
    .permission-matrix tr{display:block;border:1px solid var(--bs-border-color);border-radius:12px;margin-bottom:.8rem;padding:.35rem .65rem}
    .permission-matrix th,.permission-matrix td{display:block;border:0;padding:.45rem .25rem}
    .permission-matrix td{display:flex;justify-content:space-between;align-items:center;gap:.5rem;border-top:1px solid var(--bs-border-color-translucent)}
    .permission-role-name{flex:0 0 auto;margin-bottom:0;text-align:left}
    .permission-matrix td>div{width:auto;flex:0 0 auto;justify-content:flex-end!important}
}
</style>
<script>
document.querySelectorAll('input[name$="[manage]"]').forEach((manage) => manage.addEventListener('change', () => {
    if (!manage.checked) return;
    const viewName = manage.name.replace('[manage]', '[view]');
    const view = document.querySelector(`input[name="${CSS.escape(viewName)}"]`);
    if (view) view.checked = true;
}));
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
