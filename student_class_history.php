<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_module_access('siswa');

$pageTitle = 'Riwayat Kelas Siswa';
$studentId = filter_input(INPUT_GET, 'siswa_id', FILTER_VALIDATE_INT) ?: 0;
$yearId = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT) ?: 0;
$classId = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT) ?: 0;
$page = max(1, (int)($_GET['page'] ?? 1));

if ($studentId < 1 || !db_has_column($pdo, 'student_class_history', 'id')) {
    http_response_code(404);
    exit('Riwayat kelas tidak ditemukan atau belum tersedia.');
}

$studentStmt = $pdo->prepare('SELECT id,nis,nama FROM siswa WHERE id=? LIMIT 1');
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();
if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

$yearStmt = $pdo->prepare('SELECT DISTINCT ta.id,ta.nama FROM student_class_history h JOIN tahun_ajaran ta ON ta.id=h.tahun_ajaran_tujuan_id WHERE h.siswa_id=? ORDER BY ta.nama DESC');
$yearStmt->execute([$studentId]);
$years = $yearStmt->fetchAll();
$classStmt = $pdo->prepare('SELECT DISTINCT k.id,k.nama_kelas FROM student_class_history h JOIN kelas k ON k.id=h.kelas_tujuan_id WHERE h.siswa_id=? ORDER BY k.nama_kelas');
$classStmt->execute([$studentId]);
$classes = $classStmt->fetchAll();

$where = ['h.siswa_id=?'];
$params = [$studentId];
if ($yearId > 0) {
    $where[] = 'h.tahun_ajaran_tujuan_id=?';
    $params[] = $yearId;
}
if ($classId > 0) {
    $where[] = 'h.kelas_tujuan_id=?';
    $params[] = $classId;
}
$whereSql = implode(' AND ', $where);
$historySql = 'SELECT h.id,h.kelas_asal_id,h.tahun_ajaran_asal_id,h.kelas_tujuan_id,h.tahun_ajaran_tujuan_id,h.alasan,h.dibuat_pada,k1.nama_kelas AS kelas_asal,ta1.nama AS tahun_asal,k2.nama_kelas AS kelas_tujuan,ta2.nama AS tahun_tujuan,u.nama_lengkap AS petugas FROM student_class_history h LEFT JOIN kelas k1 ON k1.id=h.kelas_asal_id LEFT JOIN tahun_ajaran ta1 ON ta1.id=h.tahun_ajaran_asal_id JOIN kelas k2 ON k2.id=h.kelas_tujuan_id JOIN tahun_ajaran ta2 ON ta2.id=h.tahun_ajaran_tujuan_id LEFT JOIN users u ON u.id=h.actor_user_id WHERE ' . $whereSql;

if (($_GET['format'] ?? '') === 'csv') {
    $export = $pdo->prepare($historySql . ' ORDER BY h.dibuat_pada DESC,h.id DESC');
    $export->execute($params);
    $rows = $export->fetchAll();
    $filename = 'riwayat-kelas-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)$student['nis']) . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tanggal', 'Tahun ajaran asal', 'Kelas asal', 'Tahun ajaran tujuan', 'Kelas tujuan', 'Alasan', 'Petugas'], ';');
    foreach ($rows as $row) {
        $values = [
            $row['dibuat_pada'],
            $row['tahun_asal'] ?? '',
            $row['kelas_asal'] ?? '',
            $row['tahun_tujuan'],
            $row['kelas_tujuan'],
            $row['alasan'] ?? '',
            $row['petugas'] ?? '',
        ];
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^[\s]*[=+@-]/u', $value)) $value = "'" . $value;
        }
        unset($value);
        fputcsv($out, $values, ';');
    }
    fclose($out);
    exit;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM student_class_history h WHERE ' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$perPage = 25;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$listStmt = $pdo->prepare($historySql . ' ORDER BY h.dibuat_pada DESC,h.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$listStmt->execute($params);
$historyRows = $listStmt->fetchAll();
$queryBase = ['siswa_id' => $studentId, 'tahun_ajaran_id' => $yearId ?: '', 'kelas_id' => $classId ?: ''];

require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <p class="text-secondary mb-1">Riwayat administrasi siswa</p>
        <h1 class="h3 mb-1">Riwayat Kelas</h1>
        <p class="mb-0"><?= e($student['nama']) ?><?= $student['nis'] ? ' · NIS ' . e($student['nis']) : '' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="index.php?module=siswa&amp;action=view&amp;id=<?= $studentId ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke detail</a><a class="btn btn-outline-primary" href="<?= e(app_url('student_class_history.php?' . http_build_query(array_merge($queryBase, ['format' => 'csv'])))) ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Ekspor CSV</a><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak</button></div>
</div>
<section class="card p-3 p-md-4 mb-3 no-print" aria-label="Filter riwayat kelas">
    <form method="get" class="row g-3 align-items-end"><input type="hidden" name="siswa_id" value="<?= $studentId ?>">
        <div class="col-12 col-md-5"><label class="form-label" for="historyYear">Tahun ajaran tujuan</label><select class="form-select" name="tahun_ajaran_id" id="historyYear">
                <option value="">Semua tahun ajaran</option><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= $yearId === (int)$year['id'] ? 'selected' : '' ?>><?= e($year['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-12 col-md-5"><label class="form-label" for="historyClass">Kelas tujuan</label><select class="form-select" name="kelas_id" id="historyClass">
                <option value="">Semua kelas</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= $classId === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['nama_kelas']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="student_class_history.php?siswa_id=<?= $studentId ?>">Reset</a></div>
    </form>
</section>
<section class="card p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Catatan perpindahan</h2>
            <p class="small text-secondary mb-0">Data lama hanya dicatat bila tersedia bukti administrasi resmi.</p>
        </div><span class="badge text-bg-light"><?= number_format($total, 0, ',', '.') ?> catatan</span>
    </div>
    <?php if (!$historyRows): ?><div class="empty-state">Belum ada riwayat yang sesuai dengan filter.</div><?php else: ?><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Periode/Kelas asal</th>
                        <th>Periode/Kelas tujuan</th>
                        <th>Alasan</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($historyRows as $row): ?><tr>
                            <td class="text-nowrap"><?= e(date('d M Y H:i', strtotime($row['dibuat_pada']))) ?></td>
                            <td><?= e(($row['tahun_asal'] ?? '—') . ' · ' . ($row['kelas_asal'] ?? 'Belum tercatat')) ?></td>
                            <td><strong><?= e($row['tahun_tujuan'] . ' · ' . $row['kelas_tujuan']) ?></strong></td>
                            <td><?= e($row['alasan'] ?: '—') ?></td>
                            <td><?= e($row['petugas'] ?: 'Akun tidak tersedia') ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?><nav class="mt-3" aria-label="Halaman riwayat kelas">
                <ul class="pagination pagination-sm flex-wrap mb-0"><?php for ($n = max(1, $page - 2); $n <= min($pages, $page + 2); $n++): ?><li class="page-item<?= $n === $page ? ' active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(array_merge($queryBase, ['page' => $n]))) ?>" <?= $n === $page ? ' aria-current="page"' : '' ?>><?= $n ?></a></li><?php endfor; ?></ul>
            </nav><?php endif; ?>
    <?php endif; ?>
</section>
<p class="small text-secondary mt-3">Pencatatan dimulai setelah fitur riwayat diterapkan. Riwayat sebelum itu tidak direkonstruksi dari perkiraan.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
