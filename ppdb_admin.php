<?php

declare(strict_types=1);

require_module_access('ppdb_admin');

$pageTitle = 'Pengelolaan PPDB';
$allowedStatuses = ['Dalam verifikasi', 'Perlu perbaikan', 'Diterima', 'Tidak diterima', 'Dibatalkan'];
$periodStatuses = ['Draft', 'Terbit', 'Ditutup'];
$error = '';
$tab = (string) ($_GET['tab'] ?? 'pendaftar');
if (!in_array($tab, ['pendaftar', 'periode'], true)) $tab = 'pendaftar';

// Proses perubahan data di sisi server; semua operasi tulis memerlukan CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_module_access('ppdb_admin', true);
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'ubah_status') {
        $applicantId = filter_var($_POST['pendaftar_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $publicNote = trim((string) ($_POST['catatan_publik'] ?? ''));
        if ($applicantId < 1 || !in_array($newStatus, $allowedStatuses, true) || mb_strlen($publicNote, 'UTF-8') > 500
            || (in_array($newStatus, ['Perlu perbaikan', 'Tidak diterima'], true) && $publicNote === '')) {
            $error = 'Periksa kembali status dan catatan. Status Perlu perbaikan dan Tidak diterima wajib disertai catatan; panjang maksimal 500 karakter.';
        } else {
            try {
                $pdo->beginTransaction();
                $find = $pdo->prepare('SELECT id,status FROM ppdb_pendaftar WHERE id=? FOR UPDATE');
                $find->execute([$applicantId]);
                $current = $find->fetch();
                if (!$current) {
                    throw new DomainException('Permohonan PPDB tidak ditemukan.');

                }
                $update = $pdo->prepare('UPDATE ppdb_pendaftar SET status=?,catatan_publik=? WHERE id=?');
                $update->execute([$newStatus, $publicNote !== '' ? $publicNote : null, $applicantId]);
                $history = $pdo->prepare(
                    'INSERT INTO ppdb_status_history (pendaftar_id,status_sebelumnya,status_baru,catatan,actor_user_id)
                     VALUES (?,?,?,?,?)'

                );
                $history->execute([
                    $applicantId,
                    (string) $current['status'],
                    $newStatus,
                    $publicNote !== '' ? $publicNote : null,
                    (int) ($_SESSION['user_id'] ?? 0),
                ]);
                
                $pdo->commit();
                record_activity($pdo, 'memperbarui status permohonan PPDB', 'ppdb_pendaftar', $applicantId, $newStatus);
                flash('Status permohonan dan riwayatnya berhasil disimpan.');
                redirect('index.php?module=ppdb_admin&tab=pendaftar&id=' . $applicantId);
            } catch (DomainException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                app_log('error', 'PPDB status update failed', ['record_id' => $applicantId, 'type' => get_class($exception)]);
                $error = 'Status belum tersimpan. Periksa log aplikasi dan coba kembali.';
            }
        }
        $tab = 'pendaftar';
    } elseif ($action === 'simpan_periode') {
        $periodId = filter_var($_POST['periode_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $year = trim((string) ($_POST['tahun_ajaran'] ?? ''));
        $title = trim((string) ($_POST['judul'] ?? ''));
        $info = trim((string) ($_POST['informasi'] ?? ''));
        $requirements = trim((string) ($_POST['persyaratan'] ?? ''));
        $startDate = trim((string) ($_POST['mulai'] ?? ''));
        $endDate = trim((string) ($_POST['selesai'] ?? ''));
        $periodStatus = trim((string) ($_POST['status'] ?? 'Draft'));
        $isDate = static function (string $value): bool {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };

        if (mb_strlen($year, 'UTF-8') < 4 || mb_strlen($year, 'UTF-8') > 20
            || $title === '' || mb_strlen($title, 'UTF-8') > 180
            || mb_strlen($info, 'UTF-8') > 10000 || mb_strlen($requirements, 'UTF-8') > 10000
            || !$isDate($startDate) || !$isDate($endDate) || $startDate > $endDate
            || !in_array($periodStatus, $periodStatuses, true)) {
            $error = 'Data periode tidak valid. Periksa tahun ajaran, judul, status, dan rentang tanggal.';
        } else {
            try {
                $overlap = $pdo->prepare(
                    "SELECT COUNT(*) FROM ppdb_periode WHERE status='Terbit' AND mulai<=? AND selesai>=? AND id<>?"
                );
                $overlap->execute([$endDate, $startDate, $periodId]);
                if ($periodStatus === 'Terbit' && (int) $overlap->fetchColumn() > 0) {
                    throw new DomainException('Rentang periode Terbit bertumpang tindih dengan periode lain. Tutup atau sesuaikan periode yang sudah ada terlebih dahulu.');
                }
                if ($periodId > 0) {
                    $save = $pdo->prepare('UPDATE ppdb_periode SET tahun_ajaran=?,judul=?,informasi=?,persyaratan=?,mulai=?,selesai=?,status=? WHERE id=?');
                    $save->execute([$year, $title, $info !== '' ? $info : null, $requirements !== '' ? $requirements : null, $startDate, $endDate, $periodStatus, $periodId]);
                    if ($save->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT 1 FROM ppdb_periode WHERE id=?');
                        $exists->execute([$periodId]);
                        if (!$exists->fetchColumn()) throw new DomainException('Periode yang akan diubah tidak ditemukan.');
                    }
                    record_activity($pdo, 'memperbarui periode PPDB', 'ppdb_periode', $periodId, $periodStatus);
                } else {
                    $save = $pdo->prepare('INSERT INTO ppdb_periode (tahun_ajaran,judul,informasi,persyaratan,mulai,selesai,status) VALUES (?,?,?,?,?,?,?)');
                    $save->execute([$year, $title, $info !== '' ? $info : null, $requirements !== '' ? $requirements : null, $startDate, $endDate, $periodStatus]);
                    $periodId = (int) $pdo->lastInsertId();
                    record_activity($pdo, 'membuat periode PPDB', 'ppdb_periode', $periodId, $periodStatus);
                }
                flash('Periode PPDB berhasil disimpan.');
                redirect('index.php?module=ppdb_admin&tab=periode');
            } catch (DomainException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                app_log('error', 'PPDB period save failed', ['record_id' => $periodId, 'type' => get_class($exception)]);
                $error = 'Periode PPDB belum dapat disimpan. Periksa log aplikasi dan coba kembali.';
            }
        }
        $tab = 'periode';
    } else {
        http_response_code(422);
        $error = 'Tindakan PPDB tidak dikenal.';
    }
}

// Ambil ringkasan status yang nyata dari database.
$summary = array_fill_keys($allowedStatuses, 0);
$summary['total'] = 0;
$summary['periode_aktif'] = 0;
foreach ($pdo->query('SELECT status,COUNT(*) AS jumlah FROM ppdb_pendaftar GROUP BY status')->fetchAll() as $row) {
    $statusKey = (string) $row['status'];
    if (array_key_exists($statusKey, $summary)) $summary[$statusKey] = (int) $row['jumlah'];
    $summary['total'] += (int) $row['jumlah'];
}
$summary['periode_aktif'] = (int) $pdo->query("SELECT COUNT(*) FROM ppdb_periode WHERE status='Terbit' AND mulai<=CURDATE() AND selesai>=CURDATE()")->fetchColumn();

$periods = $pdo->query(
    'SELECT per.*, COUNT(p.id) AS jumlah_pendaftar
     FROM ppdb_periode per LEFT JOIN ppdb_pendaftar p ON p.periode_id=per.id
     GROUP BY per.id ORDER BY per.mulai DESC,per.id DESC'
)->fetchAll();
$editPeriodId = filter_var($_GET['edit_periode'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$editPeriod = null;
if ($editPeriodId > 0) {
    $findPeriod = $pdo->prepare('SELECT * FROM ppdb_periode WHERE id=? LIMIT 1');
    $findPeriod->execute([$editPeriodId]);
    $editPeriod = $findPeriod->fetch() ?: null;
}

// Filter dan paginasi daftar pendaftar dengan query terparameter.
$filterStatus = trim((string) ($_GET['status'] ?? ''));
if ($filterStatus !== '' && !in_array($filterStatus, $allowedStatuses, true)) $filterStatus = '';
$filterPeriod = filter_var($_GET['periode'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$search = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($search, 'UTF-8') > 100) $search = mb_substr($search, 0, 100, 'UTF-8');
$page = max(1, filter_var($_GET['halaman'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$pageSize = 20;
$where = [];
$params = [];
if ($filterStatus !== '') { $where[] = 'p.status=?'; $params[] = $filterStatus; }
if ($filterPeriod > 0) { $where[] = 'p.periode_id=?'; $params[] = $filterPeriod; }
if ($search !== '') {
    $where[] = '(p.nomor_pendaftaran LIKE ? OR p.nama_calon LIKE ? OR p.nama_wali LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM ppdb_pendaftar p' . $whereSql);
$count->execute($params);
$totalRows = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$list = $pdo->prepare(
    'SELECT p.id,p.nomor_pendaftaran,p.nama_calon,p.nama_wali,p.telepon_wali,p.status,p.dibuat_pada,per.judul AS periode_judul
     FROM ppdb_pendaftar p JOIN ppdb_periode per ON per.id=p.periode_id'
     . $whereSql . ' ORDER BY p.dibuat_pada DESC,p.id DESC LIMIT ' . $pageSize . ' OFFSET ' . $offset
);
$list->execute($params);
$applicants = $list->fetchAll();

$selectedId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$selectedApplicant = null;
$statusHistory = [];
if ($selectedId > 0) {
    $detail = $pdo->prepare(
        'SELECT p.*,per.judul AS periode_judul FROM ppdb_pendaftar p
         JOIN ppdb_periode per ON per.id=p.periode_id WHERE p.id=? LIMIT 1'
    );
    $detail->execute([$selectedId]);
    $selectedApplicant = $detail->fetch() ?: null;
    if ($selectedApplicant) {
        $historyQuery = $pdo->prepare(
            'SELECT h.*,u.nama_lengkap AS nama_petugas FROM ppdb_status_history h
             LEFT JOIN users u ON u.id=h.actor_user_id WHERE h.pendaftar_id=? ORDER BY h.dibuat_pada DESC,h.id DESC'
        );
        $historyQuery->execute([$selectedId]);
        $statusHistory = $historyQuery->fetchAll();
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="ppdb-admin-page">
    <div class="ppdb-admin-heading">
        <div>
            <span class="ppdb-admin-eyebrow">Layanan publik · akses Super Admin</span>
            <h1>Pengelolaan PPDB</h1>
            <p>Atur periode pendaftaran dan tindak lanjuti permohonan secara tercatat.</p>
        </div>
        <a class="btn btn-outline-primary" href="public/ppdb.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Lihat halaman PPDB</a>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

    <section class="ppdb-summary-grid" aria-label="Ringkasan permohonan PPDB">
        <article class="ppdb-summary-card"><span>Total permohonan</span><strong><?= number_format($summary['total']) ?></strong><small>Semua periode</small></article>
        <article class="ppdb-summary-card"><span>Periode pendaftaran aktif</span><strong><?= number_format($summary['periode_aktif']) ?></strong><small>Status terbit dan tanggal berlaku</small></article>
        <article class="ppdb-summary-card"><span>Dalam verifikasi</span><strong><?= number_format($summary['Dalam verifikasi']) ?></strong><small>Menunggu tindak lanjut</small></article>
        <article class="ppdb-summary-card"><span>Perlu perbaikan</span><strong><?= number_format($summary['Perlu perbaikan']) ?></strong><small>Catatan dapat dilihat pendaftar</small></article>
    </section>

    <nav class="ppdb-admin-tabs" aria-label="Menu pengelolaan PPDB">
        <a class="<?= $tab === 'pendaftar' ? 'is-active' : '' ?>" href="index.php?module=ppdb_admin&tab=pendaftar"><i class="bi bi-people" aria-hidden="true"></i>Permohonan <span><?= number_format($summary['total']) ?></span></a>
        <a class="<?= $tab === 'periode' ? 'is-active' : '' ?>" href="index.php?module=ppdb_admin&tab=periode"><i class="bi bi-calendar-range" aria-hidden="true"></i>Periode pendaftaran</a>
    </nav>

    <?php if ($tab === 'periode'): ?>
        <div class="ppdb-admin-columns">
            <section class="card ppdb-admin-panel">
                <div class="ppdb-panel-heading"><div><h2><?= $editPeriod ? 'Ubah periode' : 'Tambah periode' ?></h2><p>Periode publik aktif jika status Terbit dan tanggal hari ini berada dalam rentang.</p></div></div>
                <form method="post" class="ppdb-period-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="simpan_periode">
                    <input type="hidden" name="periode_id" value="<?= (int) ($editPeriod['id'] ?? 0) ?>">
                    <label>Tahun ajaran<input class="form-control" name="tahun_ajaran" required maxlength="20" value="<?= e($editPeriod['tahun_ajaran'] ?? '') ?>" placeholder="2026/2027"></label>
                    <label>Nama periode<input class="form-control" name="judul" required maxlength="180" value="<?= e($editPeriod['judul'] ?? '') ?>" placeholder="Penerimaan Siswa Baru"></label>
                    <div class="ppdb-date-grid"><label>Tanggal mulai<input class="form-control" type="date" name="mulai" required value="<?= e($editPeriod['mulai'] ?? '') ?>"></label><label>Tanggal selesai<input class="form-control" type="date" name="selesai" required value="<?= e($editPeriod['selesai'] ?? '') ?>"></label></div>
                    <label>Status periode<select class="form-select" name="status"><?php foreach ($periodStatuses as $option): ?><option value="<?= e($option) ?>" <?= ($editPeriod['status'] ?? 'Draft') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                    <label>Informasi pendaftaran<textarea class="form-control" name="informasi" rows="3" maxlength="10000"><?= e($editPeriod['informasi'] ?? '') ?></textarea></label>
                    <label>Persyaratan<textarea class="form-control" name="persyaratan" rows="4" maxlength="10000"><?= e($editPeriod['persyaratan'] ?? '') ?></textarea></label>
                    <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Simpan periode</button><?php if ($editPeriod): ?><a class="btn btn-outline-secondary" href="index.php?module=ppdb_admin&tab=periode">Batal ubah</a><?php endif; ?></div>
                </form>
            </section>
            <section class="card ppdb-admin-panel">
                <div class="ppdb-panel-heading"><div><h2>Daftar periode</h2><p>Periode lama tetap disimpan untuk menjaga riwayat pendaftar.</p></div></div>
                <div class="ppdb-period-list">
                    <?php if (!$periods): ?><div class="ppdb-empty-state">Belum ada periode PPDB. Tambahkan periode yang sudah disahkan sekolah.</div><?php endif; ?>
                    <?php foreach ($periods as $period): ?>
                        <article class="ppdb-period-item">
                            <div class="d-flex justify-content-between gap-2"><strong><?= e($period['judul']) ?></strong><span class="badge <?= $period['status'] === 'Terbit' ? 'text-bg-success' : ($period['status'] === 'Ditutup' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= e($period['status']) ?></span></div>
                            <small><?= e($period['tahun_ajaran']) ?> · <?= e(date('d M Y', strtotime($period['mulai']))) ?> – <?= e(date('d M Y', strtotime($period['selesai']))) ?></small>
                            <small><?= number_format((int) $period['jumlah_pendaftar']) ?> permohonan</small>
                            <a class="btn btn-sm btn-outline-primary align-self-start" href="index.php?module=ppdb_admin&tab=periode&edit_periode=<?= (int) $period['id'] ?>">Ubah periode</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    <?php else: ?>
        <section class="card ppdb-admin-panel mb-3">
            <form class="ppdb-filter-form" method="get">
                <input type="hidden" name="module" value="ppdb_admin"><input type="hidden" name="tab" value="pendaftar">
                <label class="ppdb-search-field">Cari nomor, calon, atau wali<input class="form-control" type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Ketik kata kunci"></label>
                <label>Status<select class="form-select" name="status"><option value="">Semua status</option><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $filterStatus === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                <label>Periode<select class="form-select" name="periode"><option value="">Semua periode</option><?php foreach ($periods as $period): ?><option value="<?= (int) $period['id'] ?>" <?= $filterPeriod === (int) $period['id'] ? 'selected' : '' ?>><?= e($period['judul']) ?> · <?= e($period['tahun_ajaran']) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Terapkan</button>
            </form>
        </section>

        <?php if ($selectedApplicant): ?>
            <section class="card ppdb-admin-panel ppdb-applicant-detail" id="detailPendaftar">
                <div class="ppdb-panel-heading"><div><span class="ppdb-admin-eyebrow">Detail pendaftar · <?= e($selectedApplicant['nomor_pendaftaran']) ?></span><h2><?= e($selectedApplicant['nama_calon']) ?></h2><p><?= e($selectedApplicant['periode_judul']) ?> · <?= e(date('d M Y H:i', strtotime($selectedApplicant['dibuat_pada']))) ?></p></div><a class="btn btn-sm btn-outline-secondary" href="index.php?module=ppdb_admin&tab=pendaftar">Tutup detail</a></div>
                <div class="ppdb-private-data-grid"><div><span>NISN</span><strong><?= e($selectedApplicant['nisn'] ?: 'Tidak diisi') ?></strong></div><div><span>Nama wali</span><strong><?= e($selectedApplicant['nama_wali']) ?></strong></div><div><span>Telepon wali</span><strong><?= e($selectedApplicant['telepon_wali']) ?></strong></div></div>
                <div class="ppdb-detail-columns">
                    <form method="post" class="ppdb-status-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="ubah_status"><input type="hidden" name="pendaftar_id" value="<?= (int) $selectedApplicant['id'] ?>">
                        <label>Status permohonan<select class="form-select" name="status" required><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option) ?>" <?= $selectedApplicant['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                        <label>Catatan yang dapat dilihat pendaftar<textarea class="form-control" name="catatan_publik" rows="3" maxlength="500" placeholder="Wajib untuk status Perlu perbaikan / Tidak diterima; jangan tulis informasi internal"><?= e($selectedApplicant['catatan_publik'] ?? '') ?></textarea><small class="text-secondary">Catatan ini dapat dibaca pemegang nomor dan kunci status.</small></label>
                        <button class="btn btn-primary" type="submit">Simpan status dan catatan</button>
                    </form>
                    <div class="ppdb-history"><h3>Riwayat tindak lanjut</h3><?php if (!$statusHistory): ?><p class="text-secondary">Belum ada riwayat perubahan yang tercatat.</p><?php else: ?><ol><?php foreach ($statusHistory as $history): ?><li><strong><?= e($history['status_baru']) ?></strong><small><?= e(date('d M Y H:i', strtotime($history['dibuat_pada']))) ?> · <?= e($history['nama_petugas'] ?: 'Riwayat awal') ?></small><?php if ($history['status_sebelumnya']): ?><small>Sebelumnya: <?= e($history['status_sebelumnya']) ?></small><?php endif; ?><?php if ($history['catatan']): ?><p><?= e($history['catatan']) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></div>
                </div>
            </section>
        <?php elseif ($selectedId > 0): ?>
            <div class="alert alert-warning" role="status">Permohonan yang dipilih tidak ditemukan.</div>
        <?php endif; ?>

        <section class="card ppdb-admin-panel">
            <div class="ppdb-panel-heading"><div><h2>Daftar permohonan</h2><p>Menampilkan <?= number_format(count($applicants)) ?> dari <?= number_format($totalRows) ?> data yang sesuai filter.</p></div></div>
            <?php if (!$applicants): ?><div class="ppdb-empty-state">Tidak ada permohonan yang cocok. Coba ubah filter atau kata kunci.</div><?php else: ?>
                <div class="table-responsive"><table class="table align-middle ppdb-applicant-table"><thead><tr><th>Nomor / waktu</th><th>Calon siswa</th><th>Periode</th><th>Wali</th><th>Status</th><th><span class="visually-hidden">Tindakan</span></th></tr></thead><tbody>
                    <?php foreach ($applicants as $applicant): ?><tr><td><strong><?= e($applicant['nomor_pendaftaran']) ?></strong><small><?= e(date('d M Y H:i', strtotime($applicant['dibuat_pada']))) ?></small></td><td><?= e($applicant['nama_calon']) ?></td><td><?= e($applicant['periode_judul']) ?></td><td><?= e($applicant['nama_wali']) ?><small><?= e($applicant['telepon_wali']) ?></small></td><td><span class="badge text-bg-<?= $applicant['status'] === 'Diterima' ? 'success' : ($applicant['status'] === 'Perlu perbaikan' ? 'warning' : ($applicant['status'] === 'Tidak diterima' || $applicant['status'] === 'Dibatalkan' ? 'secondary' : 'primary')) ?>"><?= e($applicant['status']) ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?module=ppdb_admin&tab=pendaftar&id=<?= (int) $applicant['id'] ?><?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>">Tinjau</a></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php if ($totalPages > 1): ?><nav class="ppdb-pagination" aria-label="Halaman permohonan"><span>Halaman <?= $page ?> dari <?= $totalPages ?></span><div><?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('index.php?' . http_build_query(['module' => 'ppdb_admin', 'tab' => 'pendaftar', 'q' => $search, 'status' => $filterStatus, 'periode' => $filterPeriod ?: '', 'halaman' => $page - 1]))) ?>">Sebelumnya</a><?php endif; ?><?php if ($page < $totalPages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('index.php?' . http_build_query(['module' => 'ppdb_admin', 'tab' => 'pendaftar', 'q' => $search, 'status' => $filterStatus, 'periode' => $filterPeriod ?: '', 'halaman' => $page + 1]))) ?>">Berikutnya</a><?php endif; ?></div></nav><?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <p class="ppdb-privacy-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Data pendaftar hanya ditampilkan kepada Super Admin. Catatan status pada formulir dapat dilihat oleh pemilik nomor pendaftaran dan kunci status.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
