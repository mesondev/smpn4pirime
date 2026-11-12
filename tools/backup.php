<?php

declare(strict_types=1);

/**
 * Create a protected, timestamped backup of the configured SIMS database,
 * uploads, and storage files. Run this script from the command line only.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script backup hanya dapat dijalankan melalui CLI.\n");
}

date_default_timezone_set('Asia/Jayapura');

$projectRoot = realpath(dirname(__DIR__));
$backupPath = trim((string)(getenv('SIMS_BACKUP_DIR') ?: ''));
if ($backupPath === '' && PHP_OS_FAMILY === 'Windows') {
    $userProfile = trim((string)(getenv('USERPROFILE') ?: ''));
    if ($userProfile !== '') $backupPath = $userProfile . DIRECTORY_SEPARATOR . 'Documents' . DIRECTORY_SEPARATOR . 'SIMS_Backups';
}
$environment = strtolower((string)(getenv('SIMS_APP_ENV') ?: 'local'));
$databaseHost = (string)(getenv('SIMS_DB_HOST') ?: '127.0.0.1');
$databasePort = (int)(getenv('SIMS_DB_PORT') ?: 3306);
$databaseName = (string)(getenv('SIMS_DB_NAME') ?: 'sims_db');
$databaseUser = (string)(getenv('SIMS_DB_USER') ?: 'root');
$databasePassword = (string)(getenv('SIMS_DB_PASS') ?: '');
$zipAvailable = class_exists(ZipArchive::class);

$fail = static function (string $message, int $exitCode = 1): never {
    fwrite(STDERR, "Gagal: {$message}\n");
    exit($exitCode);
};

if ($projectRoot === false) $fail('Folder proyek tidak dapat ditentukan.');
if ($backupPath === '') $fail('Atur SIMS_BACKUP_DIR ke folder privat di luar document root.');
if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) $fail('Nama database pada konfigurasi tidak valid.');
if (!filter_var($databasePort, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
    $fail('Port database tidak valid.');
}
if (!$zipAvailable) $fail('Aktifkan ekstensi PHP zip pada instalasi PHP XAMPP.');

if (!is_dir($backupPath) && !mkdir($backupPath, 0700, true) && !is_dir($backupPath)) {
    $fail('Folder backup tidak dapat dibuat. Periksa SIMS_BACKUP_DIR dan hak aksesnya.');
}
$backupRoot = realpath($backupPath);
if ($backupRoot === false || !is_writable($backupRoot)) $fail('Folder backup tidak dapat ditulis.');

$projectPrefix = strtolower(rtrim(str_replace('\\', '/', $projectRoot), '/') . '/');
$backupNormalized = strtolower(rtrim(str_replace('\\', '/', $backupRoot), '/') . '/');
if (str_starts_with($backupNormalized, $projectPrefix)) {
    $fail('Folder backup harus berada di luar folder proyek dan document root.');
}

$dumpPath = trim((string)(getenv('SIMS_MYSQLDUMP') ?: ''));
if ($dumpPath === '') {
    $candidates = glob('C:/xampp/mysql/bin/mysqldump.exe') ?: [];
    if (!$candidates) $candidates = glob('C:/laragon/bin/mysql/mysql-*/bin/mysqldump.exe') ?: [];
    rsort($candidates, SORT_NATURAL);
    $dumpPath = (string)($candidates[0] ?? '');
}
if ($dumpPath === '' || !is_file($dumpPath)) {
    $fail('mysqldump tidak ditemukan. Atur SIMS_MYSQLDUMP ke executable XAMPP atau MySQL yang aktif.');
}

try {
    $pdo = new PDO(
        "mysql:host={$databaseHost};port={$databasePort};dbname={$databaseName};charset=utf8mb4",
        $databaseUser,
        $databasePassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $pdo->query('SELECT 1');
} catch (Throwable $exception) {
    $fail('Koneksi ke database yang dikonfigurasi gagal. Periksa layanan MySQL XAMPP dan SIMS_DB_*.', 2);
}

if (in_array('--check', $argv, true)) {
    fwrite(STDOUT, "Pemeriksaan backup lulus.\nDatabase: {$databaseName}\nFolder backup: {$backupRoot}\nmysqldump: {$dumpPath}\n" );
    exit(0);
}

$backupLockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sims_backup_' . hash('sha256', strtolower($backupRoot)) . '.lock';
$backupLock = @fopen($backupLockPath, 'c');
if (!is_resource($backupLock) || !flock($backupLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($backupLock)) fclose($backupLock);
    $fail('Backup lain sedang berjalan. Tunggu sampai selesai, lalu coba kembali.', 4);
}
@chmod($backupLockPath, 0600);

$timestamp = date('Ymd_His');
$runDirectory = $backupRoot . DIRECTORY_SEPARATOR . 'sims_' . $timestamp . '_' . bin2hex(random_bytes(3));
if (!mkdir($runDirectory, 0700)) $fail('Folder untuk hasil backup tidak dapat dibuat.');

$sqlPartial = $runDirectory . DIRECTORY_SEPARATOR . 'database.sql.partial';
$zipPartial = $runDirectory . DIRECTORY_SEPARATOR . 'files.zip.partial';
$sqlFile = $runDirectory . DIRECTORY_SEPARATOR . 'database.sql';
$zipFile = $runDirectory . DIRECTORY_SEPARATOR . 'files.zip';
$manifestFile = $runDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
$optionFile = tempnam(sys_get_temp_dir(), 'sims-db-');
if ($optionFile === false) {
    @rmdir($runDirectory);
    $fail('File konfigurasi sementara tidak dapat dibuat.');
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

    $command = [
        $dumpPath,
        '--defaults-extra-file=' . $optionFile,
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--no-tablespaces',
        '--default-character-set=utf8mb4',
        $databaseName,
    ];
    $pipes = [];
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['file', $sqlPartial, 'wb'], 2 => ['pipe', 'w']],
        $pipes,
        $projectRoot,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) throw new RuntimeException('mysqldump tidak dapat dijalankan.');
    fclose($pipes[0]);
    $dumpError = trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[2]);
    $dumpExitCode = proc_close($process);
    if ($dumpExitCode !== 0 || !is_file($sqlPartial) || filesize($sqlPartial) < 1) {
        throw new RuntimeException('Backup database gagal.' . ($dumpError !== '' ? ' Periksa log CLI.' : ''));
    }
    if (!rename($sqlPartial, $sqlFile)) throw new RuntimeException('File database backup tidak dapat diselesaikan.');

    $archive = new ZipArchive();
    if ($archive->open($zipPartial, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Arsip berkas backup tidak dapat dibuat.');
    }
    $fileCount = 0;
    foreach (['uploads', 'storage'] as $sourceName) {
        $sourceRoot = $projectRoot . DIRECTORY_SEPARATOR . $sourceName;
        if (!is_dir($sourceRoot)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->isLink()) continue;
            $relativePath = substr($fileInfo->getPathname(), strlen($projectRoot) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            if (!$archive->addFile($fileInfo->getPathname(), $relativePath)) {
                throw new RuntimeException('Berkas gagal ditambahkan ke arsip: ' . $relativePath);
            }
            $fileCount++;
        }
    }
    if (!$archive->close()) throw new RuntimeException('Arsip berkas backup gagal ditutup.');
    if (!is_file($zipPartial) || filesize($zipPartial) < 1) throw new RuntimeException('Arsip berkas backup kosong.');
    if (!rename($zipPartial, $zipFile)) throw new RuntimeException('Arsip berkas backup tidak dapat diselesaikan.');

    $manifest = [
        'created_at' => date(DATE_ATOM),
        'database' => $databaseName,
        'environment' => $environment,
        'database_file' => basename($sqlFile),
        'database_sha256' => hash_file('sha256', $sqlFile),
        'database_bytes' => filesize($sqlFile),
        'files_archive' => basename($zipFile),
        'files_sha256' => hash_file('sha256', $zipFile),
        'files_bytes' => filesize($zipFile),
        'archived_file_count' => $fileCount,
    ];
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($manifestFile, $manifestJson . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Manifest backup tidak dapat ditulis.');
    }
    @chmod($sqlFile, 0600);
    @chmod($zipFile, 0600);
    @chmod($manifestFile, 0600);

    fwrite(STDOUT, "Backup berhasil. Simpan folder ini dengan aman:\n{$runDirectory}\n" );
    fwrite(STDOUT, "Database: " . number_format((int)$manifest['database_bytes']) . " byte\n" );
    fwrite(STDOUT, "Arsip berkas: " . number_format((int)$manifest['files_bytes']) . " byte ({$fileCount} file)\n" );
    fwrite(STDOUT, "SHA-256 tercatat di manifest.json.\n" );
} catch (Throwable $exception) {
    if (isset($archive) && $archive instanceof ZipArchive) @$archive->close();
    foreach ([$sqlPartial, $zipPartial, $sqlFile, $zipFile, $manifestFile] as $partialFile) {
        if (is_file($partialFile)) @unlink($partialFile);
    }
    @rmdir($runDirectory);
    fwrite(STDERR, 'Backup gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(3);
} finally {
    if (is_file($optionFile)) @unlink($optionFile);
    if (is_resource($backupLock)) {
        flock($backupLock, LOCK_UN);
        fclose($backupLock);
    }
}
