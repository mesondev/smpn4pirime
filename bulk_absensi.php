<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
require_module_access('absensi', true);

$classes = $pdo->query("SELECT k.id,CONCAT(k.nama_kelas,' · ',ta.nama) AS label FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id ORDER BY ta.nama DESC,k.tingkat,k.nama_kelas")->fetchAll(PDO::FETCH_KEY_PAIR);
$classId = (int)($_POST['kelas_id'] ?? $_GET['kelas_id'] ?? 0);
$date = trim((string)($_POST['tanggal'] ?? date('Y-m-d')));
$error = '';
$notice = '';
$students = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!isset($classes[(string)$classId])) {
        $error = 'Pilih kelas yang tersedia.';
    } elseif (!$validDate || $validDate->format('Y-m-d') !== $date) {
        $error = 'Tanggal absensi tidak valid.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM siswa WHERE kelas_id=? AND status='Aktif' ORDER BY nama");
        $stmt->execute([$classId]);
        $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $statuses = $_POST['status'] ?? [];
        foreach ($studentIds as $studentId) {
            if (!isset($statuses[$studentId]) || !in_array($statuses[$studentId], ['Hadir', 'Izin', 'Sakit', 'Alpa'], true)) {
                $error = 'Pilih status kehadiran untuk setiap siswa sebelum menyimpan.';
                break;
            }
        }
        if ($error === '' && !$studentIds) $error = 'Kelas ini belum memiliki siswa aktif.';
        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT IGNORE INTO absensi (siswa_id,tanggal,status,keterangan) VALUES (?,?,?,?)');
                $saved = 0;
                foreach ($studentIds as $studentId) {
                    $insert->execute([$studentId, $date, $statuses[$studentId], null]);
                    $saved += $insert->rowCount();
                }
                $duplicates = count($studentIds) - $saved;
                $pdo->commit();
                record_activity($pdo, 'absensi massal', 'absensi', null, 'kelas ' . $classId . ' · ' . $date . ' · ' . $saved . ' disimpan');
                $notice = $saved . ' catatan berhasil disimpan.' . ($duplicates ? ' ' . $duplicates . ' catatan yang sudah ada dilewati agar tidak dobel.' : '');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Absensi gagal disimpan. Periksa koneksi dan coba lagi.';
            }
        }
    }
}

if ($classId > 0 && isset($classes[(string)$classId])) {
    $stmt = $pdo->prepare("SELECT id,nis,nama FROM siswa WHERE kelas_id=? AND status='Aktif' ORDER BY nama");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();
}
$pageTitle = 'Absensi Siswa Massal';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Absensi Siswa Massal</h1>
        <p>Catat kehadiran satu kelas untuk satu tanggal. Catatan yang sudah ada tidak akan ditimpa.</p>
    </div><a class="btn btn-outline-secondary" href="index.php?module=absensi">Kembali ke Absensi</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<div class="card p-3 p-md-4">
    <form method="get" class="row g-3 align-items-end mb-4 no-print">
        <div class="col-md-5"><label class="form-label" for="kelas_id">Kelas</label><select class="form-select" id="kelas_id" name="kelas_id" required>
                <option value="">-- Pilih Kelas --</option><?php foreach ($classes as $id => $label): ?><option value="<?= e($id) ?>" <?= $classId === (int)$id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label" for="tanggal">Tanggal</label><input class="form-control" id="tanggal" type="date" name="tanggal" value="<?= e($date) ?>" required></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Tampilkan Siswa</button></div>
    </form>
    <?php if ($students): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="kelas_id" value="<?= $classId ?>"><input type="hidden" name="tanggal" value="<?= e($date) ?>">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($students as $i => $student): $oldStatus = (string)($_POST['status'][$student['id']] ?? ''); ?><tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($student['nis']) ?></td>
                                <td><?= e($student['nama']) ?></td>
                                <td><select class="form-select" name="status[<?= (int)$student['id'] ?>]" required>
                                        <option value="">-- Pilih --</option><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $status): ?><option value="<?= e($status) ?>" <?= $oldStatus === $status ? ' selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                                    </select></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div><button class="btn btn-primary">Simpan Absensi Kelas</button>
        </form><?php elseif ($classId > 0): ?><div class="empty-state">Tidak ada siswa aktif di kelas ini. Pastikan kelas dan data siswa sudah diisi.</div><?php else: ?><div class="empty-state">Pilih kelas dan tanggal untuk menampilkan daftar siswa.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>