<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (in_array(current_role(), ['guru', 'siswa'], true)) {
    http_response_code(403);
    exit('Pencarian data sekolah tidak tersedia untuk peran ini.');
}
$query = trim((string)($_GET['q'] ?? ''));
$results = [];
if (mb_strlen($query) >= 2) {
    $like = '%' . $query . '%';
    $searches = [
        'siswa' => ['label' => 'Siswa', 'sql' => "SELECT id,CONCAT(nama,' · NIS ',nis) AS label FROM siswa WHERE nama LIKE ? OR nis LIKE ? ORDER BY nama LIMIT 8"],
        'guru' => ['label' => 'Guru', 'sql' => "SELECT id,CONCAT(nama,' · NIP ',COALESCE(nip,'-')) AS label FROM guru WHERE nama LIKE ? OR nip LIKE ? ORDER BY nama LIMIT 8"],
        'kelas' => ['label' => 'Kelas', 'sql' => "SELECT k.id,CONCAT(k.nama_kelas,' · ',ta.nama) AS label FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id WHERE k.nama_kelas LIKE ? ORDER BY ta.nama DESC,k.nama_kelas LIMIT 8"],
        'mapel' => ['label' => 'Mata Pelajaran', 'sql' => "SELECT id,CONCAT(nama_mapel,' · ',kode_mapel) AS label FROM mapel WHERE nama_mapel LIKE ? OR kode_mapel LIKE ? ORDER BY nama_mapel LIMIT 8"],
    ];
    foreach ($searches as $module => $search) {
        if (!can_view_module($module)) continue;
        $stmt = $pdo->prepare($search['sql']);
        $stmt->execute($module === 'kelas' ? [$like] : [$like, $like]);
        $rows = $stmt->fetchAll();
        if ($rows) $results[$module] = ['label' => $search['label'], 'rows' => $rows];
    }
}
$pageTitle = 'Pencarian';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Hasil Pencarian</h1>
        <p>Pencarian siswa, guru, kelas, dan mata pelajaran.</p>
    </div>
</div>
<div class="card p-3 mb-4">
    <form method="get" class="d-flex gap-2"><input class="form-control" name="q" value="<?= e($query) ?>" minlength="2" placeholder="Masukkan minimal 2 karakter" required><button class="btn btn-primary">Cari</button></form>
</div>
<?php if (mb_strlen($query) < 2): ?><div class="empty-state">Masukkan minimal dua karakter untuk mencari.</div><?php elseif (!$results): ?><div class="empty-state">Tidak ada data yang cocok untuk “<?= e($query) ?>”.</div><?php else: ?>
    <div class="row g-3"><?php foreach ($results as $module => $group): ?><div class="col-lg-6">
                <div class="card p-3">
                    <h2 class="h6"><?= e($group['label']) ?></h2>
                    <div class="list-group list-group-flush"><?php foreach ($group['rows'] as $row): ?><a class="list-group-item list-group-item-action px-0" href="index.php?module=<?= e($module) ?>&q=<?= urlencode($query) ?>"><?= e($row['label']) ?><i class="bi bi-arrow-up-right float-end"></i></a><?php endforeach; ?></div>
                </div>
            </div><?php endforeach; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>