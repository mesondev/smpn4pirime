<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();

if (current_role() !== 'admin') {
    http_response_code(403);
    exit('Hanya Super Admin yang dapat menjalankan backup.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
verify_csrf();

set_time_limit(0);
ignore_user_abort(true);

$projectRoot = realpath(__DIR__);
$backupRoot = trim((string)(getenv('SIMS_BACKUP_DIR') ?: ''));
if ($backupRoot === '' && PHP_OS_FAMILY === 'Windows') {
    $userProfile = trim((string)(getenv('USERPROFILE') ?: ''));
    if ($userProfile !== '') $backupRoot = $userProfile . DIRECTORY_SEPARATOR . 'Documents' . DIRECTORY_SEPARATOR . 'SIMS_Backups';
}
$phpCli = trim((string)(getenv('SIMS_PHP_CLI') ?: 'C:\\xampp\\php\\php.exe'));
$dumpTool = trim((string)(getenv('SIMS_MYSQLDUMP') ?: 'C:\\xampp\\mysql\\bin\\mysqldump.exe'));
$backupScript = __DIR__ . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php';

$error = '';
if ($projectRoot === false || !is_file($backupScript)) $error = 'Lokasi proyek atau skrip backup tidak ditemukan.';
elseif ($backupRoot === '') $error = 'Lokasi backup belum dikonfigurasi. Atur SIMS_BACKUP_DIR atau USERPROFILE.';
elseif (!is_file($phpCli)) $error = 'PHP CLI XAMPP tidak ditemukan pada ' . $phpCli . '.';
elseif (!is_file($dumpTool)) $error = 'mysqldump XAMPP tidak ditemukan pada ' . $dumpTool . '.';
elseif (!function_exists('proc_open')) $error = 'Fitur proc_open tidak aktif pada konfigurasi PHP XAMPP.';

if ($error !== '') {
    app_log('error', 'Dashboard backup could not start', ['reason' => $error]);
    flash($error, 'danger');
    redirect('index.php#backup');
}

$environment = getenv();
if (!is_array($environment)) $environment = [];
$environment['SIMS_APP_ENV'] = (string)(getenv('SIMS_APP_ENV') ?: 'local');
$environment['SIMS_BACKUP_DIR'] = $backupRoot;
$environment['SIMS_MYSQLDUMP'] = $dumpTool;
$environment['SIMS_DB_HOST'] = $host;
$environment['SIMS_DB_PORT'] = (string)$port;
$environment['SIMS_DB_NAME'] = $db;
$environment['SIMS_DB_USER'] = $user;
$environment['SIMS_DB_PASS'] = $pass;

$pipes = [];
try {
    $process = proc_open(
        [$phpCli, $backupScript],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $projectRoot,
        $environment,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) throw new RuntimeException('Proses CLI backup tidak dapat dimulai.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        app_log('error', 'Dashboard backup process failed', ['exit_code' => $exitCode]);
        flash('Backup gagal dijalankan. Periksa hak akses folder backup dan log aplikasi, lalu coba kembali.', 'danger');
        redirect('index.php#backup');
    }

    $folderName = '';
    if (preg_match('/Backup berhasil\. Simpan folder ini dengan aman:\s*([\s\S]+?)\R/u', (string)$stdout, $match)) {
        $folderName = basename(trim($match[1]));
    }
    $message = 'Backup database dan berkas berhasil dibuat.';
    if (preg_match('/^sims_[A-Za-z0-9_-]+$/', $folderName)) $message .= ' Folder: ' . $folderName . '.';
    flash($message, 'success');
    record_activity($pdo, 'buat backup manual', 'backup', null, 'Backup database dan berkas dibuat dari Dashboard Super Admin.');
} catch (Throwable $exception) {
    if (isset($process) && is_resource($process)) proc_terminate($process);
    foreach ([1, 2] as $pipeIndex) if (isset($pipes[$pipeIndex]) && is_resource($pipes[$pipeIndex])) fclose($pipes[$pipeIndex]);
    app_log('error', 'Dashboard backup process raised an exception', ['type' => get_class($exception)]);
    flash('Backup gagal dijalankan. Periksa hak akses folder backup dan log aplikasi.', 'danger');
}

redirect('index.php#backup');
