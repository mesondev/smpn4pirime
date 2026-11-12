<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_module_access('staf', true);
$pageTitle = 'Impor Tenaga Kependidikan';
$errors = [];
$flashMessage = take_flash();

if (($_GET['template'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template-impor-staf-sims.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['nip', 'nama', 'jenis_kelamin', 'jabatan', 'unit_kerja', 'no_hp', 'email', 'alamat', 'tanggal_masuk', 'status'], ';');
    fputcsv($output, ['', 'Contoh Nama', 'L', 'Tenaga Administrasi', 'Tata Usaha', '', '', '', '', 'aktif'], ';');
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? '');
    if ($postAction === 'cancel') {
        $token = (string)($_POST['token'] ?? '');
        if ($token !== '') unset($_SESSION['_staff_import_staging'][$token]);
        flash('Pratinjau impor dibatalkan. Tidak ada data yang disimpan.');
        redirect('index.php?module=staf');
    }
    if ($postAction === 'confirm') {
        $token = (string)($_POST['token'] ?? '');
        $stage = $_SESSION['_staff_import_staging'][$token] ?? null;
        if (!preg_match('/^[a-f0-9]{32}$/', $token) || !is_array($stage) || (int)($stage['user_id'] ?? 0) !== (int)$_SESSION['user_id'] || (int)($stage['expires_at'] ?? 0) < time()) {
            unset($_SESSION['_staff_import_staging'][$token]);
            flash('Pratinjau impor tidak ditemukan atau sudah kedaluwarsa. Unggah ulang file CSV.', 'warning');
            redirect('staff_import.php');
        }
        $validRows = array_values(array_filter($stage['rows'], static fn(array $row): bool => !empty($row['valid'])));
        $inserted = 0;
        $skipped = 0;
        try {
            $pdo->beginTransaction();
            $duplicateCheck = $pdo->prepare('SELECT id FROM staff WHERE nip=? LIMIT 1');
            $insert = $pdo->prepare('INSERT INTO staff (nip,nama,jenis_kelamin,jabatan,unit_kerja,no_hp,email,alamat,tanggal_masuk,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach ($validRows as $row) {
                $data = $row['data'];
                if ($data['nip'] !== '') {
                    $duplicateCheck->execute([$data['nip']]);
                    if ($duplicateCheck->fetchColumn()) {
                        $skipped++;
                        continue;
                    }
                }
                $insert->execute([
                    $data['nip'] !== '' ? $data['nip'] : null,
                    $data['nama'],
                    $data['jenis_kelamin'] !== '' ? $data['jenis_kelamin'] : null,
                    $data['jabatan'],
                    $data['unit_kerja'] !== '' ? $data['unit_kerja'] : null,
                    $data['no_hp'] !== '' ? $data['no_hp'] : null,
                    $data['email'] !== '' ? $data['email'] : null,
                    $data['alamat'] !== '' ? $data['alamat'] : null,
                    $data['tanggal_masuk'] !== '' ? $data['tanggal_masuk'] : null,
                    $data['status'],
                ]);
                $inserted++;
            }
            $pdo->commit();
            if ($inserted > 0) record_activity($pdo, 'impor', 'staff', null, $inserted . ' data baru');
            unset($_SESSION['_staff_import_staging'][$token]);
            flash("Impor selesai: {$inserted} data ditambahkan, {$skipped} data NIP yang sudah tercatat dilewati.");
            redirect('index.php?module=staf');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log('error', 'Staff CSV import transaction failed', ['type' => get_class($exception)]);
            flash('Impor dibatalkan karena penyimpanan gagal. Periksa database dan coba lagi.', 'danger');
            redirect('staff_import.php?token=' . rawurlencode($token));
        }
    }
    if ($postAction === 'upload') {
        $upload = $_FILES['csv_file'] ?? null;
        if (!$upload || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Pilih file CSV yang berhasil diunggah.';
        } elseif (!is_uploaded_file((string)$upload['tmp_name']) || (int)$upload['size'] < 1 || (int)$upload['size'] > 5 * 1024 * 1024 || strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $errors[] = 'File harus CSV valid dan berukuran maksimal 5 MB.';
        } else {
            $handle = fopen((string)$upload['tmp_name'], 'rb');
            if (!$handle) {
                $errors[] = 'File CSV tidak dapat dibaca.';
            } else {
                $firstLine = (string)fgets($handle);
                $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
                rewind($handle);
                $header = fgetcsv($handle, 0, $delimiter, '"', '');
                if (!is_array($header) || !$header) {
                    $errors[] = 'Header CSV tidak ditemukan.';
                    fclose($handle);
                } else {
                    $aliases = ['nip'=>'nip','nama'=>'nama','namalengkap'=>'nama','jeniskelamin'=>'jenis_kelamin','jk'=>'jenis_kelamin','jabatan'=>'jabatan','unitkerja'=>'unit_kerja','nohp'=>'no_hp','nomorhp'=>'no_hp','telepon'=>'no_hp','email'=>'email','alamat'=>'alamat','tanggalmasuk'=>'tanggal_masuk','status'=>'status'];
                    $headerMap = [];
                    foreach ($header as $index => $label) {
                        $normalized = mb_strtolower((string)preg_replace('/[\s_-]+/u', '', preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$label))), 'UTF-8');
                        if (isset($aliases[$normalized])) $headerMap[$aliases[$normalized]] = $index;
                    }
                    if (!isset($headerMap['nama'], $headerMap['jabatan'])) $errors[] = 'Header wajib memuat kolom nama dan jabatan. Unduh template untuk melihat format.';
                    $rows = [];
                    $line = 1;
                    $tooMany = false;
                    while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                        $line++;
                        if (!array_filter($values, static fn($value): bool => trim((string)$value) !== '')) continue;
                        if (count($rows) >= 1000) { $tooMany = true; break; }
                        $rowErrors = [];
                        $raw = array_map(static fn($value): string => trim((string)$value), $values);
                        $data = [];
                        foreach (['nip','nama','jenis_kelamin','jabatan','unit_kerja','no_hp','email','alamat','tanggal_masuk','status'] as $key) $data[$key] = isset($headerMap[$key]) ? trim((string)($raw[$headerMap[$key]] ?? '')) : '';
                        if (count($values) !== count($header)) $rowErrors[] = 'Jumlah kolom tidak sesuai header.';
                        $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
                        if ($data['nama'] === '' || $length($data['nama']) > 150) $rowErrors[] = 'Nama wajib diisi dan maksimal 150 karakter.';
                        if ($data['jabatan'] === '' || $length($data['jabatan']) > 100) $rowErrors[] = 'Jabatan wajib diisi dan maksimal 100 karakter.';
                        if ($data['nip'] !== '' && $length($data['nip']) > 50) $rowErrors[] = 'NIP maksimal 50 karakter.';
                        if ($data['jenis_kelamin'] !== '') {
                            $data['jenis_kelamin'] = strtoupper($data['jenis_kelamin']);
                            if (!in_array($data['jenis_kelamin'], ['L','P'], true)) $rowErrors[] = 'Jenis kelamin harus L atau P.';
                        }
                        if ($length($data['unit_kerja']) > 100 || $length($data['no_hp']) > 30 || $length($data['email']) > 150) $rowErrors[] = 'Unit kerja, nomor HP, atau email melebihi batas kolom.';
                        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Format email tidak valid.';
                        if ($data['tanggal_masuk'] !== '') {
                            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['tanggal_masuk']);
                            if (!$date || $date->format('Y-m-d') !== $data['tanggal_masuk']) $rowErrors[] = 'Tanggal masuk harus berformat YYYY-MM-DD.';
                        }
                        $statusAliases = ['aktif'=>'aktif','active'=>'aktif','nonaktif'=>'nonaktif','non-aktif'=>'nonaktif','pensiun'=>'pensiun'];
                        $statusKey = mb_strtolower($data['status'], 'UTF-8');
                        $data['status'] = $data['status'] === '' ? 'aktif' : ($statusAliases[$statusKey] ?? '');
                        if ($data['status'] === '') $rowErrors[] = 'Status harus aktif, nonaktif, atau pensiun.';
                        $rows[] = ['line'=>$line,'data'=>$data,'valid'=>!$rowErrors,'errors'=>$rowErrors];
                    }
                    fclose($handle);
                    if ($tooMany) $errors[] = 'CSV melebihi batas 1.000 baris. Tidak ada data yang disimpan.';
                    if (!$errors && $rows) {
                        $nips = array_values(array_unique(array_filter(array_column(array_column($rows, 'data'), 'nip'))));
                        $existingNips = [];
                        if ($nips) {
                            $check = $pdo->prepare('SELECT nip FROM staff WHERE nip IN (' . implode(',', array_fill(0, count($nips), '?')) . ')');
                            $check->execute($nips);
                            $existingNips = array_fill_keys(array_map('strval', $check->fetchAll(PDO::FETCH_COLUMN)), true);
                        }
                        $seenNips = [];
                        foreach ($rows as &$row) {
                            $nip = $row['data']['nip'];
                            if ($nip === '') continue;
                            if (isset($existingNips[$nip])) { $row['valid'] = false; $row['errors'][] = 'NIP sudah terdaftar di database.'; }
                            elseif (isset($seenNips[$nip])) { $row['valid'] = false; $row['errors'][] = 'NIP duplikat di dalam file.'; }
                            else $seenNips[$nip] = true;
                        }
                        unset($row);
                        $token = bin2hex(random_bytes(16));
                        $_SESSION['_staff_import_staging'][$token] = ['user_id'=>(int)$_SESSION['user_id'],'created_at'=>time(),'expires_at'=>time()+1800,'rows'=>$rows];
                        redirect('staff_import.php?token=' . $token);
                    } elseif (!$errors) $errors[] = 'CSV tidak berisi baris data.';
                }
            }
        }
    }
}

$token = (string)($_GET['token'] ?? '');
$stage = preg_match('/^[a-f0-9]{32}$/', $token) ? ($_SESSION['_staff_import_staging'][$token] ?? null) : null;
if (is_array($stage) && ((int)($stage['user_id'] ?? 0) !== (int)$_SESSION['user_id'] || (int)($stage['expires_at'] ?? 0) < time())) {
    unset($_SESSION['_staff_import_staging'][$token]);
    $stage = null;
    $errors[] = 'Pratinjau impor sudah kedaluwarsa. Unggah kembali file CSV.';
}
$rows = is_array($stage) ? $stage['rows'] : [];
$validCount = count(array_filter($rows, static fn(array $row): bool => !empty($row['valid'])));
$invalidCount = count($rows) - $validCount;
$previewPage = max(1, (int)($_GET['halaman'] ?? 1));
$previewPerPage = 50;
$previewPages = max(1, (int)ceil(count($rows) / $previewPerPage));
$previewPage = min($previewPage, $previewPages);
$previewRows = array_slice($rows, ($previewPage - 1) * $previewPerPage, $previewPerPage);
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><p class="text-secondary mb-1">Tenaga Kependidikan</p><h1 class="h3 mb-0">Impor CSV</h1></div><a class="btn btn-outline-secondary" href="index.php?module=staf"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke daftar staf</a></div>
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type'] ?? 'success') ?>" role="status"><?= e($flashMessage['message'] ?? '') ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if (!$stage): ?><section class="card p-3 p-md-4"><p>Unduh template, isi maksimal 1.000 baris, lalu unggah CSV UTF-8 berukuran maksimal 5 MB. Unggah hanya menyiapkan pratinjau dan tidak langsung menyimpan data.</p><a class="btn btn-sm btn-outline-primary align-self-start mb-3" href="staff_import.php?template=1"><i class="bi bi-download me-1" aria-hidden="true"></i>Unduh template CSV</a><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload"><div class="col-12"><label class="form-label" for="staffCsv">File CSV</label><input class="form-control" type="file" name="csv_file" id="staffCsv" accept=".csv,text/csv" required><div class="form-text">Kolom wajib: nama dan jabatan. NIP ganda akan ditandai sebelum penyimpanan.</div></div><div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Validasi dan tampilkan pratinjau</button></div></form></section>
<?php else: ?><section class="card p-3 p-md-4 mb-3"><div class="d-flex flex-wrap gap-2"><span class="badge text-bg-success"><?= $validCount ?> baris siap diimpor</span><span class="badge text-bg-warning"><?= $invalidCount ?> baris perlu diperbaiki/dilewati</span></div><p class="small text-secondary mt-2 mb-0">Pratinjau berlaku 30 menit dan terikat ke sesi akun ini. Baris bermasalah tidak akan disimpan.</p></section><section class="card p-3"><div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead class="table-light"><tr><th>Baris CSV</th><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Unit</th><th>Status</th><th>Validasi</th></tr></thead><tbody><?php foreach ($previewRows as $row): ?><tr class="<?= $row['valid'] ? '' : 'table-warning' ?>"><td><?= (int)$row['line'] ?></td><td><?= e($row['data']['nip'] ?: '—') ?></td><td><?= e($row['data']['nama']) ?></td><td><?= e($row['data']['jabatan']) ?></td><td><?= e($row['data']['unit_kerja'] ?: '—') ?></td><td><?= e($row['data']['status']) ?></td><td><?= $row['valid'] ? '<span class="badge text-bg-success">Valid</span>' : e(implode(' ', $row['errors'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($previewPages > 1): ?><nav aria-label="Halaman pratinjau"><ul class="pagination pagination-sm flex-wrap"><?php for ($n=max(1,$previewPage-2);$n<=min($previewPages,$previewPage+2);$n++): ?><li class="page-item<?= $n===$previewPage?' active':'' ?>"><a class="page-link" href="?token=<?= e($token) ?>&amp;halaman=<?= $n ?>"><?= $n ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<div class="d-flex flex-wrap gap-2 mt-2"><form method="post" onsubmit="return confirm('Simpan <?= $validCount ?> baris valid ke database? Baris tidak valid akan dilewati.');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-primary" type="submit" <?= $validCount ? '' : 'disabled' ?>><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Impor <?= $validCount ?> baris valid</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-outline-secondary" type="submit">Batalkan pratinjau</button></form></div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
