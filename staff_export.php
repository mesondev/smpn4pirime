<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_module_access('staf', true);

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$jabatan = trim((string)($_GET['jabatan'] ?? ''));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(nip LIKE ? OR nama LIKE ? OR jabatan LIKE ? OR unit_kerja LIKE ?)';
    array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
}
if (in_array($status, ['aktif','nonaktif','pensiun'], true)) { $where[] = 'status=?'; $params[] = $status; }
if ($jabatan !== '') { $where[] = 'jabatan=?'; $params[] = $jabatan; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare('SELECT nip,nama,jenis_kelamin,jabatan,unit_kerja,no_hp,email,alamat,tanggal_masuk,status,created_at,updated_at FROM staff' . $whereSql . ' ORDER BY nama,id');
$stmt->execute($params);
$rows = $stmt->fetchAll();
record_activity($pdo, 'ekspor CSV', 'staff', null, count($rows) . ' data');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan-tenaga-kependidikan-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store, private');
$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['NIP','Nama','Jenis Kelamin','Jabatan','Unit Kerja','Nomor HP','Email','Alamat','Tanggal Masuk','Status','Dibuat','Diperbarui'], ';');
foreach ($rows as $row) {
    $values = array_values($row);
    foreach ($values as &$value) {
        $value = (string)($value ?? '');
        if (preg_match('/^[\s]*[=+@-]/u', $value)) $value = "'" . $value;
    }
    unset($value);
    fputcsv($output, $values, ';');
}
fclose($output);
exit;
