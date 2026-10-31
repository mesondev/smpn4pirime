<?php

declare(strict_types=1);

// Modul khusus guru; route dan autentikasi tetap melalui index.php.
require_module_access('guru');

$schemaRows = $pdo->query('SHOW COLUMNS FROM guru')->fetchAll();
$schema = array_column($schemaRows, null, 'Field');
$requiredColumns = ['id', 'nama', 'jenis_kelamin'];
$missingColumns = array_values(array_diff($requiredColumns, array_keys($schema)));
$supportedFields = [
    'nip' => ['label' => 'NIP', 'type' => 'text', 'max' => 40],
    'nama' => ['label' => 'Nama Lengkap', 'type' => 'text', 'required' => true, 'max' => 120],
    'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'type' => 'select', 'required' => true, 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
    'telepon' => ['label' => 'Nomor Telepon', 'type' => 'text', 'max' => 30],
    'email' => ['label' => 'Email', 'type' => 'email', 'max' => 120],
    'alamat' => ['label' => 'Alamat', 'type' => 'textarea'],
    'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif']],
];
$fields = array_intersect_key($supportedFields, $schema);
$fieldColumns = array_keys($fields);
$hasPhotoColumn = isset($schema['foto']);
$hasPublicColumn = isset($schema['tampil_publik']);
$canManage = can_manage_module('guru');
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) require_once $composerAutoload;
$spreadsheetAvailable = extension_loaded('zip') && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
$action = (string)($_GET['action'] ?? 'list');
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_form_action'] ?? '') === 'save') $action = 'create';
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?module=guru&open_add=1');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$pageTitle = 'Data Guru';
$errors = [];
$importReport = null;

if ($missingColumns) {
    $pageTitle = 'Data Guru';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">Struktur tabel guru belum sesuai. Kolom wajib yang tidak ditemukan: ' . e(implode(', ', $missingColumns)) . '. Tidak ada data yang diubah.</div>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

function guru_normalize_gender(string $value): ?string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    return match ($value) {
        'l', 'laki-laki', 'laki laki', 'pria' => 'L',
        'p', 'perempuan', 'wanita' => 'P',
        default => null,
    };
}

function guru_validate_values(array $input, array $fields, array $schema): array
{
    $values = [];
    $errors = [];
    foreach ($fields as $key => $field) {
        $raw = trim((string)($input[$key] ?? ''));
        if (!empty($field['required']) && $raw === '') {
            $errors[] = $field['label'] . ' wajib diisi.';
            continue;
        }
        if ($key === 'jenis_kelamin' && $raw !== '') {
            $gender = guru_normalize_gender($raw);
            if ($gender === null) $errors[] = 'Jenis kelamin harus L/laki-laki atau P/perempuan.';
            $values[$key] = $gender;
            continue;
        }
        if (($field['type'] ?? '') === 'select' && $raw !== '' && !array_key_exists($raw, $field['options'])) {
            $errors[] = $field['label'] . ' tidak valid.';
            continue;
        }
        if (($field['type'] ?? '') === 'email' && $raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
            continue;
        }
        if (isset($field['max']) && mb_strlen($raw, 'UTF-8') > $field['max']) {
            $errors[] = $field['label'] . ' maksimal ' . $field['max'] . ' karakter.';
            continue;
        }
        $values[$key] = $raw === '' ? null : $raw;
    }
    if (isset($fields['status']) && ($values['status'] ?? null) === null) {
        $values['status'] = ($schema['status']['Default'] ?? '') === 'Tidak Aktif' ? 'Tidak Aktif' : 'Aktif';
    }
    return [$values, $errors];
}

function guru_duplicate_nip(PDO $pdo, ?string $nip, int $exceptId = 0): bool
{
    if ($nip === null || $nip === '') return false;
    $sql = 'SELECT id FROM guru WHERE nip=?' . ($exceptId > 0 ? ' AND id<>?' : '') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($exceptId > 0 ? [$nip, $exceptId] : [$nip]);
    return (bool)$stmt->fetchColumn();
}

function guru_sql_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) throw new InvalidArgumentException('Nama kolom database tidak valid.');
    return '`' . $name . '`';
}

function guru_display_value(string $key, mixed $value): string
{
    if ($key === 'jenis_kelamin') return ['L' => 'Laki-laki', 'P' => 'Perempuan'][(string)$value] ?? (string)$value;
    return (string)($value ?? '-');
}

function guru_photo_src(mixed $path, int $id): ?string
{
    $path = (string)$path;
    return $id > 0 && preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $path) ? 'media/guru.php?id=' . $id : null;
}

function guru_store_photo(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, 'Unggahan foto tidak berhasil.'];
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if (!is_uploaded_file($tmp) || $size < 1 || $size > 2 * 1024 * 1024) return [null, 'Foto harus berupa berkas unggahan valid dengan ukuran maksimal 2 MB.'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $image = @getimagesize($tmp);
    if (!isset($formats[$mime]) || !$image || ($image['mime'] ?? '') !== $mime) return [null, 'Foto harus berupa gambar JPG, PNG, atau WebP yang valid.'];
    if ((int)$image[0] < 64 || (int)$image[1] < 64 || (int)$image[0] > 5000 || (int)$image[1] > 5000) return [null, 'Dimensi foto minimal 64 × 64 dan maksimal 5.000 × 5.000 piksel.'];
    $directory = dirname(__DIR__) . '/uploads/guru';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Folder penyimpanan foto tidak dapat dibuat.'];
    $filename = bin2hex(random_bytes(20)) . '.' . $formats[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) return [null, 'Foto gagal disimpan. Periksa izin folder uploads/guru.'];
    return ['uploads/guru/' . $filename, null];
}

function guru_remove_photo_file(mixed $relativePath): void
{
    $relativePath = (string)$relativePath;
    if (!preg_match('~^uploads/guru/([a-f0-9-]+\.(?:jpg|png|webp))$~i', $relativePath, $match)) return;
    $directory = realpath(dirname(__DIR__) . '/uploads/guru');
    $file = $directory ? realpath($directory . DIRECTORY_SEPARATOR . $match[1]) : false;
    if ($directory && $file && str_starts_with($file, $directory . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}

if ($action === 'export') {
    require_module_access('guru', true);
    $exportColumns = array_values(array_intersect(['nip', 'nama', 'jenis_kelamin', 'telepon', 'email', 'alamat', 'status'], $fieldColumns));
    $exportRows = $pdo->query('SELECT ' . implode(',', array_map('guru_sql_identifier', $exportColumns)) . ' FROM guru ORDER BY nama,id')->fetchAll(PDO::FETCH_NUM);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data-guru-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $exportColumns);
    foreach ($exportRows as $exportRow) {
        $safeRow = array_map(static function ($value) {
            $value = (string)($value ?? '');
            return preg_match('/^[=+@\-\t\r]/', $value) ? "'" . $value : $value;
        }, $exportRow);
        fputcsv($output, $safeRow);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_module_access('guru', true);
    verify_csrf();
    $postAction = (string)($_POST['_form_action'] ?? '');
    if ($postAction === 'save' && in_array($action, ['create', 'edit'], true)) {
        $recordId = $action === 'edit' ? $id : 0;
        $record = [];
        if ($action === 'edit') {
            $stmt = $pdo->prepare('SELECT * FROM guru WHERE id=?');
            $stmt->execute([$recordId]);
            $record = $stmt->fetch() ?: [];
            if (!$record) {
                http_response_code(404);
                exit('Data guru tidak ditemukan.');
            }
        }
        [$values, $errors] = guru_validate_values($_POST, $fields, $schema);
        if ($action === 'edit' && current_role() !== 'admin' && ($values['status'] ?? 'Aktif') !== 'Aktif') {
            $linkedActive = $pdo->prepare("SELECT id FROM users WHERE guru_id=? AND role='guru' AND aktif=1 LIMIT 1");
            $linkedActive->execute([$recordId]);
            if ($linkedActive->fetchColumn()) $errors[] = 'Hanya Super Admin yang dapat menonaktifkan profil Guru yang memiliki akun aktif. Minta Super Admin menonaktifkan akun terlebih dahulu.';
        }
        $newPhoto = null;
        $oldPhoto = $record['foto'] ?? null;
        $photoUpload = $_FILES['foto'] ?? null;
        $photoWasUploaded = $photoUpload && ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removePhoto = isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1';
        if (($photoWasUploaded || $removePhoto) && !$hasPhotoColumn) $errors[] = 'Fitur foto belum aktif karena kolom foto belum tersedia.';
        if (isset($_POST['tampil_publik']) && !$hasPublicColumn) $errors[] = 'Kolom izin publikasi belum tersedia. Terapkan migrasi database terlebih dahulu.';
        if ($hasPublicColumn) $values['tampil_publik'] = isset($_POST['tampil_publik']) && $_POST['tampil_publik'] === '1' ? 1 : 0;
        if ($photoWasUploaded && $hasPhotoColumn) {
            [$newPhoto, $photoError] = guru_store_photo($photoUpload);
            if ($photoError) $errors[] = $photoError;
            else $values['foto'] = $newPhoto;
        } elseif ($removePhoto && $hasPhotoColumn) {
            $values['foto'] = null;
        }
        if (!$errors && guru_duplicate_nip($pdo, $values['nip'] ?? null, $recordId)) {
            $errors[] = 'NIP tersebut sudah terdaftar. Setiap NIP harus unik.';
        }
        if (!$errors) {
            try {
                if ($action === 'create') {
                    $names = array_map('guru_sql_identifier', array_keys($values));
                    $stmt = $pdo->prepare('INSERT INTO guru (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
                    $stmt->execute(array_values($values));
                    $recordId = (int)$pdo->lastInsertId();
                    record_activity($pdo, 'tambah', 'guru', $recordId, $values['nama'] ?? null);
                    flash('Data guru berhasil ditambahkan.');
                } else {
                    $pdo->beginTransaction();
                    $sets = array_map(static fn(string $column): string => guru_sql_identifier($column) . '=?', array_keys($values));
                    $stmt = $pdo->prepare('UPDATE guru SET ' . implode(',', $sets) . ' WHERE id=?');
                    $stmt->execute([...array_values($values), $recordId]);
                    if (array_key_exists('nama', $values)) {
                        $syncAccount = $pdo->prepare("UPDATE users SET nama_lengkap=? WHERE guru_id=? AND role='guru'");
                        $syncAccount->execute([(string)$values['nama'], $recordId]);
                    }
                    if (($values['status'] ?? 'Aktif') !== 'Aktif') {
                        $deactivateAccount = $pdo->prepare("UPDATE users SET aktif=0 WHERE guru_id=? AND role='guru' AND aktif=1");
                        $deactivateAccount->execute([$recordId]);
                    }
                    record_activity($pdo, 'ubah', 'guru', $recordId, $values['nama'] ?? null);
                    $pdo->commit();
                    flash(($values['status'] ?? 'Aktif') !== 'Aktif' ? 'Data guru diperbarui. Akun SIMS yang tertaut ikut dinonaktifkan; aktifkan profil terlebih dahulu sebelum akun dapat diaktifkan kembali.' : 'Data guru berhasil diperbarui.');
                }
                if ($action === 'edit' && ($newPhoto !== null || $removePhoto)) guru_remove_photo_file($oldPhoto);
                redirect('index.php?module=guru');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($newPhoto !== null) guru_remove_photo_file($newPhoto);
                $errors[] = 'Data gagal disimpan. Periksa kembali NIP dan kolom yang wajib.';
            }
        } elseif ($newPhoto !== null) {
            guru_remove_photo_file($newPhoto);
        }
        $record = array_merge($record, $values);
        $pageTitle = $action === 'create' ? 'Tambah Guru' : 'Edit Guru';
        if ($action === 'create' && $errors) {
            flash(implode(' ', $errors), 'danger');
            redirect('index.php?module=guru&open_add=1');
        }
    } elseif ($postAction === 'delete' && $action === 'delete' && $id > 0) {
        try {
            $pdo->beginTransaction();
            $deleteColumns = $hasPhotoColumn ? 'id,nama,foto' : 'id,nama';
            $lock = $pdo->prepare('SELECT ' . $deleteColumns . ' FROM guru WHERE id=? FOR UPDATE');
            $lock->execute([$id]);
            $teacher = $lock->fetch();
            if (!$teacher) {
                $pdo->rollBack();
                flash('Data guru tidak ditemukan.', 'warning');
                redirect('index.php?module=guru');
            }
            $refs = $pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='guru' AND REFERENCED_COLUMN_NAME='id'");
            $refs->execute();
            $usedBy = [];
            foreach ($refs->fetchAll() as $ref) {
                $tableName = guru_sql_identifier((string)$ref['TABLE_NAME']);
                $columnName = guru_sql_identifier((string)$ref['COLUMN_NAME']);
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE {$columnName}=?");
                $countStmt->execute([$id]);
                if ((int)$countStmt->fetchColumn() > 0) $usedBy[] = (string)$ref['TABLE_NAME'];
            }
            if ($usedBy) {
                $pdo->rollBack();
                flash('Guru masih digunakan oleh data terkait (' . implode(', ', array_unique($usedBy)) . '), sehingga tidak dihapus.', 'warning');
                redirect('index.php?module=guru');
            }
            $delete = $pdo->prepare('DELETE FROM guru WHERE id=?');
            $delete->execute([$id]);
            $pdo->commit();
            if ($hasPhotoColumn) guru_remove_photo_file($teacher['foto'] ?? null);
            record_activity($pdo, 'hapus', 'guru', $id, (string)$teacher['nama']);
            flash('Data guru berhasil dihapus.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Data guru gagal dihapus. Pastikan tidak ada data yang masih terhubung.', 'warning');
        }
        redirect('index.php?module=guru');
    } elseif ($postAction === 'import' && $action === 'import') {
        $importReport = ['success' => 0, 'skipped' => 0, 'failed' => 0, 'messages' => []];
        $upload = $_FILES['csv_file'] ?? null;
        $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $requestedSheetName = trim((string)($_POST['worksheet_name'] ?? ''));
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Pilih berkas CSV atau XLSX terlebih dahulu.';
        } elseif (!is_uploaded_file((string)$upload['tmp_name']) || (int)$upload['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Unggahan tidak valid atau ukurannya melebihi batas 5 MB.';
        } elseif (!in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            $errors[] = 'Format berkas harus CSV, XLS, atau XLSX.';
        } elseif (in_array($extension, ['xls', 'xlsx'], true) && !$spreadsheetAvailable) {
            $errors[] = 'Impor XLS/XLSX belum dapat digunakan: PhpSpreadsheet dan ekstensi PHP ZIP belum tersedia. Gunakan CSV hasil ekspor Excel untuk sementara.';
        } else {
            $rawHeader = null;
            $rawRows = [];
            $tooManyRows = false;
            try {
                if ($extension === 'csv') {
                    $stream = fopen((string)$upload['tmp_name'], 'rb');
                    if (!$stream) throw new RuntimeException('Berkas CSV tidak dapat dibaca.');
                    $firstLine = (string)fgets($stream);
                    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
                    rewind($stream);
                    $rawHeader = fgetcsv($stream, 0, $delimiter, '"', '');
                    $lineNumber = 1;
                    while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                        $lineNumber++;
                        $nonEmpty = array_map(static fn($value): string => trim((string)$value), $row);
                        if (!array_filter($nonEmpty, static fn($value): bool => $value !== '')) continue;
                        if (count($rawRows) >= 1000) {
                            $tooManyRows = true;
                            break;
                        }
                        $rawRows[] = ['line' => $lineNumber, 'values' => $row];
                    }
                    fclose($stream);
                } else {
                    $inputFileType = $extension === 'xls' ? 'Xls' : 'Xlsx';
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    $sheetNames = $reader->listWorksheetNames((string)$upload['tmp_name']);
                    if (!$sheetNames) throw new RuntimeException('Buku kerja tidak memiliki worksheet yang dapat dibaca.');
                    $sheetName = $requestedSheetName !== '' ? $requestedSheetName : (string)$sheetNames[0];
                    if (!in_array($sheetName, $sheetNames, true)) {
                        throw new DomainException('Nama sheet tidak ditemukan. Sheet tersedia: ' . implode(', ', $sheetNames));
                    }
                    $reader->setLoadSheetsOnly($sheetName);
                    $reader->setReadDataOnly(true);
                    if (method_exists($reader, 'setReadEmptyCells')) $reader->setReadEmptyCells(false);
                    if (method_exists($reader, 'setIgnoreRowsWithNoCells')) $reader->setIgnoreRowsWithNoCells(true);
                    $workbook = $reader->load((string)$upload['tmp_name']);
                    $worksheet = $workbook->getSheetByName($sheetName);
                    if (!$worksheet) throw new RuntimeException('Sheet yang dipilih tidak dapat dimuat.');
                    foreach ($worksheet->getRowIterator() as $sheetRow) {
                        $rowValues = $sheetRow->toArray(null, true, false, false);
                        $nonEmpty = array_map(static fn($value): string => trim((string)$value), $rowValues);
                        if (!array_filter($nonEmpty, static fn($value): bool => $value !== '')) continue;
                        if ($rawHeader === null) {
                            $rawHeader = $rowValues;
                            continue;
                        }
                        if (count($rawRows) >= 1000) {
                            $tooManyRows = true;
                            break;
                        }
                        $rawRows[] = ['line' => $sheetRow->getRowIndex(), 'values' => $rowValues];
                    }
                    $workbook->disconnectWorksheets();
                    unset($workbook, $worksheet, $reader);
                }
            } catch (Throwable $e) {
                $errors[] = $e instanceof DomainException ? $e->getMessage() : 'Berkas tidak dapat dibaca. Pastikan format dan isi file valid.';
                $rawHeader = null;
                $rawRows = [];
            }

            $aliases = [
                'nip' => 'nip',
                'nama' => 'nama',
                'namalengkap' => 'nama',
                'jeniskelamin' => 'jenis_kelamin',
                'jk' => 'jenis_kelamin',
                'telepon' => 'telepon',
                'nohp' => 'telepon',
                'nomorhp' => 'telepon',
                'email' => 'email',
                'alamat' => 'alamat',
                'status' => 'status',
            ];
            $headerMap = [];
            if (is_array($rawHeader)) {
                foreach ($rawHeader as $index => $label) {
                    $label = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$label));
                    $key = mb_strtolower((string)preg_replace('/[\s_\-]+/u', '', $label), 'UTF-8');
                    if (isset($aliases[$key]) && isset($fields[$aliases[$key]])) $headerMap[$aliases[$key]] = $index;
                }
            }
            if (!$errors && !isset($headerMap['nama'], $headerMap['jenis_kelamin'])) {
                $errors[] = 'Header wajib memuat kolom Nama dan Jenis Kelamin (atau JK). Data tidak diimpor.';
            }
            if (!$errors && $tooManyRows) {
                $errors[] = 'Berkas melebihi batas 1.000 baris data. Tidak ada baris yang disimpan.';
            }
            if (!$errors) {
                $rowsToInsert = [];
                foreach ($rawRows as $rawRow) {
                    $row = array_map(static fn($value): string => trim((string)$value), $rawRow['values']);
                    if (!array_filter($row, static fn($value): bool => $value !== '')) continue;
                    $input = [];
                    foreach ($headerMap as $fieldKey => $columnIndex) $input[$fieldKey] = $row[$columnIndex] ?? '';
                    [$values, $rowErrors] = guru_validate_values($input, $fields, $schema);
                    if ($rowErrors) {
                        $importReport['failed']++;
                        if (count($importReport['messages']) < 20) $importReport['messages'][] = 'Baris ' . $rawRow['line'] . ': ' . implode(' ', $rowErrors);
                        continue;
                    }
                    $rowsToInsert[] = ['line' => $rawRow['line'], 'values' => $values];
                }
                if ($rowsToInsert) {
                    try {
                        $pdo->beginTransaction();
                        $quotedColumns = array_map('guru_sql_identifier', $fieldColumns);
                        $insert = $pdo->prepare('INSERT INTO guru (' . implode(',', $quotedColumns) . ') VALUES (' . implode(',', array_fill(0, count($fieldColumns), '?')) . ')');
                        foreach ($rowsToInsert as $rowData) {
                            $values = $rowData['values'];
                            if (guru_duplicate_nip($pdo, $values['nip'] ?? null)) {
                                $importReport['skipped']++;
                                if (count($importReport['messages']) < 20) $importReport['messages'][] = 'Baris ' . $rowData['line'] . ': NIP sudah terdaftar, dilewati.';
                                continue;
                            }
                            try {
                                $insert->execute(array_map(static fn($key) => $values[$key] ?? null, $fieldColumns));
                                $importReport['success']++;
                            } catch (PDOException $e) {
                                $importReport['failed']++;
                                if (count($importReport['messages']) < 20) $importReport['messages'][] = 'Baris ' . $rowData['line'] . ': gagal disimpan karena konflik atau batasan database.';
                            }
                        }
                        $pdo->commit();
                        if ($importReport['success'] > 0) record_activity($pdo, 'impor', 'guru', null, $importReport['success'] . ' data baru');
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors[] = 'Impor dibatalkan karena kesalahan database. Tidak ada perubahan impor yang disimpan.';
                        $importReport['success'] = 0;
                        $importReport['skipped'] = 0;
                    }
                }
            }
        }
        $pageTitle = 'Impor Data Guru';
    } else {
        http_response_code(400);
        exit('Permintaan modul guru tidak valid.');
    }
} elseif (in_array($action, ['create', 'edit'], true)) {
    if (!$canManage) require_module_access('guru', true);
    $record = [];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM guru WHERE id=?');
        $stmt->execute([$id]);
        $record = $stmt->fetch() ?: [];
        if (!$record) {
            http_response_code(404);
            exit('Data guru tidak ditemukan.');
        }
    }
    $pageTitle = $action === 'create' ? 'Tambah Guru' : 'Edit Guru';
} elseif ($action === 'detail') {
    $stmt = $pdo->prepare('SELECT * FROM guru WHERE id=?');
    $stmt->execute([$id]);
    $detail = $stmt->fetch();
    if (!$detail) {
        http_response_code(404);
        exit('Data guru tidak ditemukan.');
    }
    $pageTitle = 'Detail Guru';
} elseif ($action === 'import' && !$canManage) {
    require_module_access('guru', true);
} elseif (!in_array($action, ['list', 'import'], true)) {
    http_response_code(404);
    exit('Halaman Data Guru tidak ditemukan.');
}
if ($action === 'import') $pageTitle = 'Impor Data Guru';

$q = trim((string)($_GET['q'] ?? ''));
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$rows = [];
$total = 0;
if ($action === 'list') {
    $searchColumns = array_values(array_intersect(['nip', 'nama', 'telepon', 'email'], $fieldColumns));
    $whereParts = [];
    $params = [];
    if ($q !== '' && $searchColumns) {
        $whereParts[] = '(' . implode(' OR ', array_map(static fn($column): string => guru_sql_identifier($column) . ' LIKE ?', $searchColumns)) . ')';
        $params = array_fill(0, count($searchColumns), '%' . $q . '%');
    }
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    if ($statusFilter !== '' && isset($fields['status']) && array_key_exists($statusFilter, $fields['status']['options'])) {
        $whereParts[] = '`status`=?';
        $params[] = $statusFilter;
    } else {
        $statusFilter = '';
    }
    $where = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';
    $showAccountLink = current_role() === 'admin';
    $guruFrom = $showAccountLink ? "guru g LEFT JOIN users u ON u.guru_id=g.id AND u.role='guru'" : 'guru';
    $count = $pdo->prepare('SELECT COUNT(*) FROM ' . $guruFrom . $where);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;
    $selectColumns = array_merge(['id'], $fieldColumns, $hasPhotoColumn ? ['foto'] : [], $hasPublicColumn ? ['tampil_publik'] : [], isset($schema['created_at']) ? ['created_at'] : []);
    $qualifiedColumns = array_map(static fn(string $column): string => ($showAccountLink ? 'g.' : '') . guru_sql_identifier($column), array_unique($selectColumns));
    if ($showAccountLink) $qualifiedColumns = array_merge($qualifiedColumns, ['u.id AS akun_user_id', 'u.username AS akun_username', 'u.aktif AS akun_aktif']);
    $sql = 'SELECT ' . implode(',', $qualifiedColumns) . ' FROM ' . $guruFrom . $where . ' ORDER BY ' . ($showAccountLink ? 'g.id' : 'id') . ' DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $list = $pdo->prepare($sql);
    $list->execute($params);
    $rows = $list->fetchAll();
    $pageCount = max(1, (int)ceil($total / $perPage));
}

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-secondary mb-0">Data ini menjadi profil induk Guru; akun login dikelola terpisah dan ditautkan oleh Super Admin.</p>
    </div>
    <?php if ($action === 'list'): ?><div class="d-flex flex-wrap gap-2"><?php if ($hasPublicColumn): ?><a class="btn btn-outline-secondary" href="public/guru.php" target="_blank" rel="noopener">Direktori Publik</a><?php endif; ?><?php if ($canManage): ?><a class="btn btn-outline-primary" href="index.php?module=guru&action=import">Impor <?= $spreadsheetAvailable ? 'CSV / XLS / XLSX' : 'CSV Excel' ?></a><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createGuruModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Guru</button><?php endif; ?></div><?php endif; ?>
</div>

<?php if ($action === 'list' && $canManage): ?>
    <div class="modal fade app-entry-modal" id="createGuruModal" tabindex="-1" aria-labelledby="createGuruTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" action="index.php?module=guru">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="createGuruTitle">Tambah Data Guru</h2>
                            <p class="small text-secondary mb-0">Isi profil guru. Akun login dapat ditautkan melalui Manajemen Pengguna.</p>
                        </div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!empty($flashMessage) && ($flashMessage['type'] ?? '') === 'danger'): ?><div class="alert alert-danger" role="alert"><?= e($flashMessage['message']) ?></div><?php endif; ?>
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="save">
                        <?php if ($hasPhotoColumn): ?><div class="mb-3"><label class="form-label" for="createGuruPhoto">Foto Profil <span class="text-secondary fw-normal">(opsional)</span></label><input class="form-control" id="createGuruPhoto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG, PNG, atau WebP; maksimal 2 MB.</div>
                            </div><?php endif; ?>
                        <div class="row"><?php foreach ($fields as $key => $field): ?><div class="col-md-6 mb-3"><label class="form-label<?= !empty($field['required']) ? ' required' : '' ?>" for="create_guru_<?= e($key) ?>"><?= e($field['label']) ?><?= $key === 'nip' ? ' (opsional)' : '' ?></label><?php if (($field['type'] ?? '') === 'select'): ?><select class="form-select" id="create_guru_<?= e($key) ?>" name="<?= e($key) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                                            <option value="">-- Pilih --</option><?php foreach ($field['options'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $key === 'status' && $value === 'Aktif' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                        </select><?php elseif (($field['type'] ?? '') === 'textarea'): ?><textarea class="form-control" id="create_guru_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"></textarea><?php else: ?><input class="form-control" id="create_guru_<?= e($key) ?>" name="<?= e($key) ?>" type="<?= e($field['type']) ?>" <?= isset($field['max']) ? 'maxlength="' . (int)$field['max'] . '"' : '' ?> <?= !empty($field['required']) ? 'required' : '' ?>><?php endif; ?></div><?php endforeach; ?></div>
                        <?php if ($hasPublicColumn): ?><div class="form-check mb-2"><input type="hidden" name="tampil_publik" value="0"><input class="form-check-input" type="checkbox" id="create_guru_public" name="tampil_publik" value="1"><label class="form-check-label" for="create_guru_public">Izinkan profil tampil di direktori publik</label>
                                <div class="form-text">Centang setelah persetujuan guru diperoleh.</div>
                            </div><?php endif; ?>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Simpan Guru</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        if (<?= isset($_GET['open_add']) ? 'true' : 'false' ?>) {
            window.addEventListener('load', () => {
                const modal = document.getElementById('createGuruModal');
                if (window.bootstrap && modal) bootstrap.Modal.getOrCreateInstance(modal).show()
            })
        }
    </script>
<?php endif; ?>

<?php if (!in_array($action, ['create', 'edit'], true)): foreach ($errors as $message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endforeach;
                                                                                                                                            endif; ?>
<?php if (!$hasPhotoColumn || !$hasPublicColumn): ?><div class="alert alert-warning">Fitur foto dan direktori guru publik belum aktif pada database. Terapkan migrasi tambahan <code>database/migrations/2026_10_27_guru_profile_fields.sql</code> setelah membuat backup. Data lama tidak dihapus oleh migrasi tersebut.</div><?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <?php if (!$canManage): ?><div class="alert alert-warning">Akun ini hanya dapat melihat data guru.</div><?php endif; ?>
    <div class="card p-3 p-md-4">
        <form method="post" enctype="multipart/form-data" action="index.php?module=guru&action=<?= e($action) ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="save">
            <?php if ($errors): ?><div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
                </div><?php endif; ?>
            <?php if ($hasPhotoColumn): $photoSrc = guru_photo_src($record['foto'] ?? null, (int)($record['id'] ?? $id)); ?>
                <div class="row align-items-center mb-3">
                    <div class="col-auto"><?php if ($photoSrc): ?><img id="guruPhotoPreview" class="guru-photo guru-photo-preview" src="<?= e($photoSrc) ?>" alt="Pratinjau foto guru"><?php else: ?><div id="guruPhotoPlaceholder" class="guru-photo guru-photo-placeholder"><i class="bi bi-person-fill"></i></div><img id="guruPhotoPreview" class="guru-photo guru-photo-preview d-none" alt="Pratinjau foto guru"><?php endif; ?></div>
                    <div class="col"><label class="form-label" for="guru_foto">Foto profil</label><input class="form-control" id="guru_foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, atau WebP; maksimal 2 MB. Pratinjau diperbarui sebelum disimpan.</div><?php if ($photoSrc && $action === 'edit'): ?><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="hapus_foto" value="1"><span class="form-check-label">Hapus foto saat menyimpan</span></label><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row">
                <?php foreach ($fields as $key => $field): ?><div class="col-md-6 mb-3"><label class="form-label<?= !empty($field['required']) ? ' required' : '' ?>" for="guru_<?= e($key) ?>"><?= e($field['label']) ?><?= $key === 'nip' ? ' (opsional)' : '' ?></label>
                        <?php if (($field['type'] ?? '') === 'select'): ?><select class="form-select" id="guru_<?= e($key) ?>" name="<?= e($key) ?>" <?= !empty($field['required']) ? ' required' : '' ?>>
                                <option value="">-- Pilih --</option><?php foreach ($field['options'] as $value => $label): ?><option value="<?= e($value) ?>" <?= (string)($record[$key] ?? '') === (string)$value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                        <?php elseif (($field['type'] ?? '') === 'textarea'): ?><textarea class="form-control" id="guru_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($record[$key] ?? '') ?></textarea>
                        <?php else: ?><input class="form-control" id="guru_<?= e($key) ?>" name="<?= e($key) ?>" type="<?= e($field['type']) ?>" value="<?= e($record[$key] ?? '') ?>" <?= isset($field['max']) ? ' maxlength="' . (int)$field['max'] . '"' : '' ?><?= !empty($field['required']) ? ' required' : '' ?>><?php endif; ?>
                    </div><?php endforeach; ?>
            </div>
            <?php if ($hasPublicColumn): ?><div class="form-check mb-3"><input type="hidden" name="tampil_publik" value="0"><input class="form-check-input" type="checkbox" id="guru_tampil_publik" name="tampil_publik" value="1" <?= (int)($record['tampil_publik'] ?? 0) === 1 ? ' checked' : '' ?>><label class="form-check-label" for="guru_tampil_publik">Izinkan profil ini tampil di direktori publik</label>
                    <div class="form-text">Centang hanya setelah persetujuan guru diperoleh. Email, nomor telepon, dan alamat tidak ditampilkan pada halaman publik.</div>
                </div><?php endif; ?>
            <?php if ($errors && isset($values)): ?><input type="hidden" name="_preserve" value="1"><?php endif; ?>
            <button class="btn btn-primary" <?= !$canManage ? ' disabled' : '' ?>>Simpan</button> <a class="btn btn-outline-secondary" href="index.php?module=guru">Batal</a>
        </form>
        <?php if ($hasPhotoColumn): ?><script>
                document.getElementById('guru_foto')?.addEventListener('change', function() {
                    const file = this.files?.[0];
                    const preview = document.getElementById('guruPhotoPreview');
                    const placeholder = document.getElementById('guruPhotoPlaceholder');
                    if (file && preview && file.type.startsWith('image/')) {
                        preview.src = URL.createObjectURL(file);
                        preview.classList.remove('d-none');
                        placeholder?.classList.add('d-none');
                    }
                });
            </script><?php endif; ?>
    </div>
<?php elseif ($action === 'detail'): ?>
    <div class="card p-3 p-md-4">
        <div class="d-flex align-items-center gap-3 mb-3"><?php $detailPhoto = guru_photo_src($detail['foto'] ?? null, $id); ?><?php if ($detailPhoto): ?><img class="guru-photo" src="<?= e($detailPhoto) ?>" alt="Foto <?= e($detail['nama'] ?? 'guru') ?>"><?php else: ?><div class="guru-photo guru-photo-placeholder"><i class="bi bi-person-fill"></i></div><?php endif; ?><div>
            <h2 class="h5 mb-1"><?= e($detail['nama'] ?? '') ?></h2><span class="text-secondary">Biodata Guru</span>
        </div>
        </div>
        <dl class="row mb-0"><?php foreach ($fields as $key => $field): ?><dt class="col-sm-3 py-2"><?= e($field['label']) ?></dt>
                <dd class="col-sm-9 py-2"><?= e(guru_display_value($key, $detail[$key] ?? null)) ?></dd><?php endforeach; ?><?php if ($hasPublicColumn): ?><dt class="col-sm-3 py-2">Publikasi</dt>
                <dd class="col-sm-9 py-2"><?= (int)($detail['tampil_publik'] ?? 0) === 1 ? 'Diizinkan' : 'Tidak ditampilkan' ?></dd><?php endif; ?><?php if (isset($detail['created_at'])): ?><dt class="col-sm-3 py-2">Tanggal dibuat</dt>
                <dd class="col-sm-9 py-2"><?= e($detail['created_at']) ?></dd><?php endif; ?>
        </dl>
        <div class="mt-3"><a class="btn btn-outline-secondary" href="index.php?module=guru">Kembali</a><?php if ($canManage): ?> <a class="btn btn-primary" href="index.php?module=guru&action=edit&id=<?= $id ?>">Edit</a><?php endif; ?></div>
    </div>
<?php elseif ($action === 'import'): ?>
    <?php if (!$canManage): ?><div class="alert alert-warning">Akun ini tidak memiliki izin mengimpor data guru.</div><?php else: ?>
        <div class="card p-3 p-md-4 mb-3">
            <p>Unggah berkas maksimal 5 MB dan 1.000 baris. CSV hasil ekspor Excel selalu didukung. <?= $spreadsheetAvailable ? 'XLS dan XLSX aktif melalui PhpSpreadsheet.' : 'XLS/XLSX tersedia setelah PhpSpreadsheet dan ekstensi PHP ZIP dipasang/diaktifkan.' ?></p>
            <p>Header wajib: <code>nama,jenis_kelamin</code> atau <code>Nama,JK</code>. Kolom opsional: <code>nip,telepon,email,alamat,status</code>. NIP ganda dilewati dan data lama tidak ditimpa.</p>
            <p><a class="btn btn-outline-primary btn-sm" href="outputs/01a17976-f3da-7573-90e5-670290bb3000/Template_Impor_Guru_SIMS.csv" download>Unduh Template CSV</a></p>
            <form method="post" enctype="multipart/form-data" action="index.php?module=guru&action=import"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="import">
                <div class="mb-3"><label class="form-label" for="csv_file">Berkas CSV<?= $spreadsheetAvailable ? ' atau XLS / XLSX' : '' ?></label><input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv<?= $spreadsheetAvailable ? ',.xls,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel' : '' ?>" required></div><?php if ($spreadsheetAvailable): ?><div class="mb-3"><label class="form-label" for="worksheet_name">Nama sheet (opsional)</label><input class="form-control" id="worksheet_name" name="worksheet_name" value="<?= e($_POST['worksheet_name'] ?? '') ?>" placeholder="Kosongkan untuk memakai sheet pertama">
                        <div class="form-text">Sheet harus sama persis dengan nama di berkas Excel.</div>
                    </div><?php endif; ?><button class="btn btn-primary">Validasi dan Impor</button> <a class="btn btn-outline-secondary" href="index.php?module=guru">Kembali</a>
            </form>
        </div>
        <?php if ($importReport): ?><div class="alert alert-info"><strong>Ringkasan impor:</strong> <?= (int)$importReport['success'] ?> berhasil, <?= (int)$importReport['skipped'] ?> dilewati, <?= (int)$importReport['failed'] ?> gagal.</div><?php if ($importReport['messages']): ?><div class="card p-3">
                    <h2 class="h6">Rincian baris</h2>
                    <ul class="mb-0"><?php foreach ($importReport['messages'] as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
                </div><?php endif; ?><?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="card p-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <form class="row g-2 flex-grow-1 no-print" method="get">
                        <input type="hidden" name="module" value="guru">
                        <div class="col-sm-8 col-md-5">
                            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari NIP, nama, telepon, email...">
                        </div><?php if (isset($fields['status'])): ?><div class="col-sm-4 col-md-3"><select class="form-select" name="status" aria-label="Filter status">
                                    <option value="">Semua status</option>
                                    <?php foreach ($fields['status']['options'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $statusFilter === $value ? ' selected' : '' ?>>
                                            <?= e($label) ?></option><?php endforeach; ?>
                                </select></div><?php endif; ?>
                        <div class="col-auto">
                            <button class="btn btn-outline-primary">Cari / Filter</button>
                            <a class="btn btn-outline-secondary" href="index.php?module=guru">Reset</a>
                        </div>
                    </form><?php if ($canManage): ?><a class="btn btn-outline-success no-print" href="index.php?module=guru&action=export"><i class="bi bi-download"></i> Ekspor CSV</a><?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No.</th><?php if ($hasPhotoColumn): ?><th>Foto</th><?php endif; ?><?php foreach ($fields as $key => $field): ?><th><?= e($field['label']) ?></th><?php endforeach; ?><?php if ($hasPublicColumn): ?><th>Publikasi</th><?php endif; ?><?php if ($showAccountLink): ?><th>Akun SIMS</th><?php endif; ?><th class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?><tr>
                                    <td colspan="<?= count($fields) + 2 + (int)$hasPhotoColumn + (int)$hasPublicColumn + (int)$showAccountLink ?>" class="text-center text-secondary py-4">Belum ada data guru.</td>
                                </tr><?php else: foreach ($rows as $index => $row): ?><tr>
                                        <td><?= $offset + $index + 1 ?></td><?php if ($hasPhotoColumn): ?><td><?php $rowPhoto = guru_photo_src($row['foto'] ?? null, (int)$row['id']); ?><?php if ($rowPhoto): ?><img class="guru-photo guru-photo-small" src="<?= e($rowPhoto) ?>" alt="Foto <?= e($row['nama'] ?? 'guru') ?>"><?php else: ?><span class="guru-photo guru-photo-small guru-photo-placeholder"><i class="bi bi-person-fill"></i></span><?php endif; ?></td><?php endif; ?><?php foreach ($fields as $key => $field): ?><td><?= e(guru_display_value($key, $row[$key] ?? null)) ?></td><?php endforeach; ?><?php if ($hasPublicColumn): ?><td><span class="badge <?= (int)($row['tampil_publik'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)($row['tampil_publik'] ?? 0) === 1 ? 'Diizinkan' : 'Privat' ?></span></td><?php endif; ?><?php if ($showAccountLink): ?><td><?php if (!empty($row['akun_user_id'])): ?><a class="btn btn-sm <?= (int)$row['akun_aktif'] === 1 ? 'btn-outline-success' : 'btn-outline-secondary' ?>" href="users.php?q=<?= rawurlencode((string)$row['akun_username']) ?>&role=guru"><i class="bi bi-person-lock me-1"></i><?= (int)$row['akun_aktif'] === 1 ? 'Akun aktif' : 'Akun nonaktif' ?></a>
                                                    <div class="small text-secondary mt-1"><?= e($row['akun_username']) ?></div><?php if (($row['status'] ?? 'Aktif') !== 'Aktif' && (int)$row['akun_aktif'] === 1): ?><div class="small text-warning-emphasis mt-1">Profil nonaktif; periksa status akun.</div><?php endif; ?><?php elseif (($row['status'] ?? 'Aktif') === 'Aktif'): ?><a class="btn btn-sm btn-outline-primary" href="users.php?buat=guru&amp;guru_id=<?= (int)$row['id'] ?>#buat-akun"><i class="bi bi-person-plus me-1"></i>Buat akun</a><?php else: ?><span class="small text-secondary">Aktifkan profil sebelum membuat akun</span><?php endif; ?>
                                            </td><?php endif; ?><td class="no-print text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="index.php?module=guru&action=detail&id=<?= (int)$row['id'] ?>">Detail</a><?php if ($canManage): ?> <a class="btn btn-sm btn-outline-primary" href="index.php?module=guru&action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                                                <form class="d-inline" method="post" action="index.php?module=guru&action=delete&id=<?= (int)$row['id'] ?>" onsubmit="return confirm('Hapus data guru ini? Data yang masih berelasi tidak dapat dihapus.')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="delete"><button class="btn btn-sm btn-outline-danger">Hapus</button></form><?php endif; ?>
                                        </td>
                                    </tr><?php endforeach;
                                    endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small text-secondary">Menampilkan <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> dari <?= $total ?> guru.</div><?php if ($pageCount > 1): ?><nav aria-label="Halaman data guru">
                            <ul class="pagination pagination-sm mb-0"><?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?><li class="page-item<?= $number === $page ? ' active' : '' ?>"><a class="page-link" href="index.php?<?= e(http_build_query(['module' => 'guru', 'q' => $q, 'status' => $statusFilter, 'page' => $number])) ?>"><?= $number ?></a></li><?php endfor; ?></ul>
                        </nav><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php require __DIR__ . '/../includes/footer.php'; ?>