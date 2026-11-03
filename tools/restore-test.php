<?php

declare(strict_types=1);

/**
 * Verify a SIMS backup and restore it into a new, isolated local test database.
 * The script never drops databases and never targets the live SIMS database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Uji pemulihan hanya dapat dijalankan melalui CLI.\n");
}

date_default_timezone_set('Asia/Jayapura');
$projectRoot = realpath(dirname(__DIR__));
$backupDirectory = realpath((string)($argv[1] ?? ''));
$environment = strtolower((string)(getenv('SIMS_APP_ENV') ?: 'local'));
$databaseHost = strtolower((string)(getenv('SIMS_DB_HOST') ?: '127.0.0.1'));
$databasePort = (int)(getenv('SIMS_DB_PORT') ?: 3306);
$databaseUser = (string)(getenv('SIMS_DB_USER') ?: 'root');
$databasePassword = (string)(getenv('SIMS_DB_PASS') ?: '');

$fail = static function (string $message, int $exitCode = 1): never {
    fwrite(STDERR, "Gagal: {$message}\n");
    exit($exitCode);
};

if ($projectRoot === false || $backupDirectory === false || !is_dir($backupDirectory)) {
    $fail('Berikan path folder backup yang valid.');
}
if ($environment === 'production') $fail('Uji restore dinonaktifkan pada environment production.');
if (!in_array($databaseHost, ['localhost', '127.0.0.1', '::1'], true)) {
    $fail('Uji restore hanya diizinkan terhadap database lokal.');
}
if (!class_exists(ZipArchive::class)) $fail('Aktifkan ekstensi PHP zip pada PHP Laragon.');

$projectPrefix = strtolower(rtrim(str_replace('\\', '/', $projectRoot), '/') . '/');
$backupNormalized = strtolower(rtrim(str_replace('\\', '/', $backupDirectory), '/') . '/');
if (str_starts_with($backupNormalized, $projectPrefix)) {
    $fail('Folder backup harus berada di luar folder proyek.');
}

$manifestPath = $backupDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
if (!is_file($manifestPath)) $fail('manifest.json tidak ditemukan.');
try {
    $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $fail('manifest.json tidak valid.');
}
if (!is_array($manifest)) $fail('Isi manifest.json harus berupa objek JSON.');

$databaseFile = basename((string)($manifest['database_file'] ?? ''));
$archiveFile = basename((string)($manifest['files_archive'] ?? ''));
if ($databaseFile !== 'database.sql' || $archiveFile !== 'files.zip') {
    $fail('Nama berkas pada manifest tidak sesuai format backup SIMS.');
}
$sqlPath = $backupDirectory . DIRECTORY_SEPARATOR . $databaseFile;
$zipPath = $backupDirectory . DIRECTORY_SEPARATOR . $archiveFile;
if (!is_file($sqlPath) || !is_readable($sqlPath) || !is_file($zipPath) || !is_readable($zipPath)) {
    $fail('File database atau arsip tidak ditemukan atau tidak dapat dibaca.');
}
$databaseHash = hash_file('sha256', $sqlPath);
$archiveHash = hash_file('sha256', $zipPath);
if (!is_string($databaseHash) || !is_string($archiveHash)) $fail('Checksum backup tidak dapat dihitung.');
if (!hash_equals((string)($manifest['database_sha256'] ?? ''), $databaseHash)) {
    $fail('Checksum database berbeda dari manifest.');
}
if (!hash_equals((string)($manifest['files_sha256'] ?? ''), $archiveHash)) {
    $fail('Checksum arsip berkas berbeda dari manifest.');
}

$archive = new ZipArchive();
if ($archive->open($zipPath, ZipArchive::RDONLY) !== true) $fail('Arsip berkas tidak dapat dibuka.');
for ($index = 0; $index < $archive->numFiles; $index++) {
    $entry = (string)$archive->getNameIndex($index);
    $normalizedEntry = str_replace('\\', '/', $entry);
    $segments = explode('/', trim($normalizedEntry, '/'));
    if (
        $entry === ''
        || str_starts_with($normalizedEntry, '/')
        || str_contains($normalizedEntry, ':')
        || str_contains($normalizedEntry, "\0")
        || str_contains($entry, '\\')
        || array_intersect($segments, ['.', '..'])
        || !preg_match('~^(?:uploads|storage)/~', $normalizedEntry)
    ) {
        $archive->close();
        $fail('Arsip berisi jalur file yang tidak diizinkan.');
    }
    $opsys = 0;
    $attributes = 0;
    if ($archive->getExternalAttributesIndex($index, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
        $fileType = ($attributes >> 16) & 0170000;
        if ($fileType === 0120000) {
            $archive->close();
            $fail('Arsip berisi symbolic link yang tidak diizinkan.');
        }
    }
}

if (in_array('--verify-only', $argv, true)) {
    $archive->close();
    fwrite(STDOUT, "Verifikasi backup lulus. Checksum database dan arsip cocok; jalur file aman.\n");
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', (string)($manifest['database'] ?? ''))) {
    $archive->close();
    $fail('Nama database sumber pada manifest tidak valid.');
}
$mysqlPath = trim((string)(getenv('SIMS_MYSQL') ?: ''));
if ($mysqlPath === '') {
    $dumpCandidates = glob('C:/laragon/bin/mysql/mysql-*/bin/mysqldump.exe') ?: [];
    rsort($dumpCandidates, SORT_NATURAL);
    $mysqlPath = isset($dumpCandidates[0]) ? dirname($dumpCandidates[0]) . '/mysql.exe' : '';
}
if ($mysqlPath === '' || !is_file($mysqlPath)) {
    $archive->close();
    $fail('mysql.exe tidak ditemukan. Atur SIMS_MYSQL ke executable MySQL Laragon.');
}

$optionFile = tempnam(sys_get_temp_dir(), 'sims-restore-');
if ($optionFile === false) {
    $archive->close();
    $fail('File konfigurasi sementara tidak dapat dibuat.');
}
$targetDatabase = 'sims_restore_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$restoreRoot = $backupDirectory . DIRECTORY_SEPARATOR . 'restore-test-' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
if (!mkdir($restoreRoot, 0700)) {
    $archive->close();
    @unlink($optionFile);
    $fail('Folder hasil uji restore tidak dapat dibuat.');
}
$escapeOption = static function (string $value): string {
    if (str_contains($value, "\0") || str_contains($value, "\n") || str_contains($value, "\r")) {
        throw new RuntimeException('Konfigurasi database mengandung karakter baris yang tidak didukung.');
    }
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
};

try {
    $optionContents = "[client]\n"
        . 'host=' . $escapeOption($databaseHost) . "\n"
        . 'port=' . $databasePort . "\n"
        . 'user=' . $escapeOption($databaseUser) . "\n"
        . 'password=' . $escapeOption($databasePassword) . "\n";
    if (file_put_contents($optionFile, $optionContents, LOCK_EX) === false) {
        throw new RuntimeException('Konfigurasi sementara database tidak dapat ditulis.');
    }
    @chmod($optionFile, 0600);

    $server = new PDO(
        "mysql:host={$databaseHost};port={$databasePort};charset=utf8mb4",
        $databaseUser,
        $databasePassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pipes = [];
    $process = proc_open(
        [$mysqlPath, '--defaults-extra-file=' . $optionFile, '--default-character-set=utf8mb4', $targetDatabase],
        [0 => ['file', $sqlPath, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $projectRoot,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) throw new RuntimeException('mysql.exe tidak dapat dijalankan.');
    $output = trim((string)stream_get_contents($pipes[1]));
    $errorOutput = trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $importExitCode = proc_close($process);
    if ($importExitCode !== 0) throw new RuntimeException('Import database uji gagal. Database uji dipertahankan untuk pemeriksaan.');

    $restoredArchive = $archive->extractTo($restoreRoot);
    $archive->close();
    if (!$restoredArchive) throw new RuntimeException('Ekstraksi file uji gagal. Database uji dipertahankan.');

    $restored = new PDO(
        "mysql:host={$databaseHost};port={$databasePort};dbname={$targetDatabase};charset=utf8mb4",
        $databaseUser,
        $databasePassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $tables = (int)$restored->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
    if ($tables < 1) throw new RuntimeException('Database hasil restore tidak memiliki tabel. Database uji dipertahankan.');
    $userTableReady = (int)$restored->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='users'")->fetchColumn() === 1;
    if (!$userTableReady) throw new RuntimeException('Tabel users tidak ditemukan setelah restore. Database uji dipertahankan.');
    $restoredUsers = (int)$restored->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $restoredFileCount = 0;
    $restoredFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($restoreRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($restoredFiles as $restoredFile) {
        if ($restoredFile->isFile()) $restoredFileCount++;
    }
    if ($restoredFileCount !== (int)($manifest['archived_file_count'] ?? -1)) {
        throw new RuntimeException('Jumlah file hasil ekstraksi tidak sesuai manifest. Hasil uji dipertahankan.');
    }

    fwrite(STDOUT, "Uji restore berhasil.\nDatabase uji: {$targetDatabase}\nBerkas uji: {$restoreRoot}\nJumlah tabel: {$tables}\nJumlah akun terpulihkan: {$restoredUsers}\nJumlah file terpulihkan: {$restoredFileCount}\n" );
    fwrite(STDOUT, "Database utama tidak diubah. Setelah pemeriksaan manual, hapus database uji dan folder ini sesuai prosedur admin.\n" );
} catch (Throwable $exception) {
    if (isset($archive) && $archive instanceof ZipArchive) @$archive->close();
    fwrite(STDERR, 'Uji restore gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(3);
} finally {
    if (is_file($optionFile)) @unlink($optionFile);
}
