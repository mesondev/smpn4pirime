<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../database/migration_helpers.php';

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;
$skipped = 0;

$report = static function (string $status, string $message) use (&$passed, &$failed, &$skipped): void {
    if ($status === 'PASS') $passed++;
    elseif ($status === 'FAIL') $failed++;
    else $skipped++;
    echo "[{$status}] {$message}\n";
};

$test = static function (string $name, callable $callback) use ($report): void {
    try {
        $callback();
        $report('PASS', $name);
    } catch (Throwable $exception) {
        $report('FAIL', $name . ' — ' . $exception->getMessage());
    }
};

$assert = static function (bool $condition, string $message = 'assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};

$process = static function (array $command, string $workingDirectory) use ($assert): array {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory);
    if (!is_resource($process)) throw new RuntimeException('could not start ' . $command[0]);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, trim((string)$stdout), trim((string)$stderr)];
};

$test('HTML output is escaped', static function () use ($assert): void {
    $assert(e('<tag attr="x">&') === '&lt;tag attr=&quot;x&quot;&gt;&amp;', 'HTML escaping output differs');
    $assert(e(null) === '', 'null should render as an empty string');
});

$test('role labels and access rules are consistent', static function () use ($assert): void {
    $roles = ['admin', 'operator', 'guru', 'siswa', 'kepala_sekolah'];
    foreach ($roles as $role) {
        $_SESSION['role'] = $role;
        $assert(can_view_module('notifikasi'), "{$role} cannot read notifications");
    }
    $_SESSION['role'] = 'admin';
    $assert(role_label() === 'Super Admin' && can_manage_module('siswa'), 'admin access or label incorrect');
    $assert(can_view_module('ppdb_admin'), 'admin should access PPDB management');
    $assert(can_view_module('portal_admin') && can_view_module('portal_fasilitas'), 'admin should access public portal management');
    $_SESSION['role'] = 'operator';
    $assert(can_manage_module('siswa') && !can_manage_module('pengguna'), 'operator permissions incorrect');
    $assert(!can_view_module('ppdb_admin'), 'operator should not access restricted PPDB applicant records');
    $assert(!can_view_module('portal_admin') && !can_view_module('portal_dokumen_publik'), 'operator should not access restricted portal publishing');
    foreach (['guru', 'siswa', 'kepala_sekolah', 'unknown'] as $role) {
        $_SESSION['role'] = $role;
        $assert(!can_manage_module('siswa'), "{$role} unexpectedly manages student records");
    }
    $_SESSION['role'] = 'kepala_sekolah';
    $assert(can_view_module('kelas') && !can_view_module('pengguna'), 'principal read permissions incorrect');
});

$test('display preferences reject invalid values and normalize sidebar state', static function () use ($assert): void {
    $preferences = normalize_user_display_preferences([
        'tema' => 'invalid',
        'ukuran_teks' => 'besar',
        'kerapatan_tabel' => 'nyaman',
        'sidebar_ciut' => '1',
        'periode_grafik' => '6months',
    ]);
    $assert($preferences['tema'] === 'sistem', 'invalid theme should use the safe default');
    $assert($preferences['ukuran_teks'] === 'besar', 'valid text size was not retained');
    $assert($preferences['kerapatan_tabel'] === 'nyaman', 'valid table density was not retained');
    $assert($preferences['sidebar_ciut'] === 1, 'sidebar preference was not normalized');
    $assert($preferences['periode_grafik'] === '6months', 'valid chart period was not retained');
});

$test('CSRF token is session-bound and valid tokens pass', static function () use ($assert): void {
    $_SESSION = [];
    $token = csrf_token();
    $assert((bool)preg_match('/^[a-f0-9]{64}$/', $token), 'CSRF token should contain 256 random bits');
    $_POST = ['_csrf' => $token];
    verify_csrf();
    $_POST = [];
});

$test('application URLs work at root, subfolder, and API paths', static function () use ($assert): void {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $assert(app_url('login.php') === '/login.php', 'root URL incorrect');
    $_SERVER['SCRIPT_NAME'] = '/sims_smpn4/index.php';
    $assert(app_url('login.php') === '/sims_smpn4/login.php', 'subfolder URL incorrect');
    $_SERVER['SCRIPT_NAME'] = '/sims_smpn4/api/notification_count.php';
    $assert(app_url('login.php') === '/sims_smpn4/login.php', 'API URL base incorrect');
});

$test('teacher import templates are tracked project assets with supported headers', static function () use ($assert, $root): void {
    $csvPath = $root . '/assets/templates/Template_Impor_Guru_SIMS.csv';
    $xlsxPath = $root . '/assets/templates/Template_Data_Guru_SMPN4_Pirime.xlsx';
    $modulePath = $root . '/modules/guru.php';
    $assert(is_file($csvPath), 'CSV import template is missing');
    $assert(is_file($xlsxPath), 'Excel import template is missing');
    $csvHeader = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)file_get_contents($csvPath))) ?? '';
    $assert($csvHeader === 'nip,nama,jenis_kelamin,telepon,email,alamat,status', 'CSV headers do not match the teacher import fields');
    $assert(str_starts_with((string)file_get_contents($xlsxPath), "PK\x03\x04"), 'Excel import template is not a valid XLSX archive');
    $module = (string)file_get_contents($modulePath);
    $assert(str_contains($module, 'assets/templates/Template_Impor_Guru_SIMS.csv'), 'CSV template link is missing');
    $assert(str_contains($module, 'assets/templates/Template_Data_Guru_SMPN4_Pirime.xlsx'), 'Excel template link is missing');
});

$test('public carousel can be paused and long-form content is not hidden automatically', static function () use ($assert, $root): void {
    $javascript = (string)file_get_contents($root . '/assets/public.js');
    $homepage = (string)file_get_contents($root . '/public/index.php');
    $assert(str_contains($javascript, '[data-hero-toggle]'), 'carousel pause control is not wired');
    $assert(str_contains($javascript, 'isPaused || reducedMotion.matches'), 'carousel does not respect pause and reduced-motion settings');
    $assert(str_contains($homepage, 'data-hero-toggle'), 'carousel pause control is missing from the page');
    $assert(!str_contains($javascript, "querySelectorAll( 'main p' )"), 'long paragraphs are still collapsed automatically');
    $assert(!str_contains($javascript, "querySelectorAll( 'main ul, main ol' )"), 'long lists are still truncated automatically');
    $assert(!str_contains($javascript, "querySelectorAll( 'main table tbody' )"), 'table rows are still hidden automatically');
});

$test('backup tooling is CLI-only and restore tests cannot target or drop the live database', static function () use ($assert, $root): void {
    $backup = (string)file_get_contents($root . '/tools/backup.php');
    $restore = (string)file_get_contents($root . '/tools/restore-test.php');
    $assert(str_contains($backup, "PHP_SAPI !== 'cli'"), 'backup is not restricted to CLI');
    $assert(str_contains($backup, 'Folder backup harus berada di luar folder proyek'), 'backup path is not kept outside the project');
    $assert(str_contains($backup, 'SIMS_BACKUP_DIR'), 'backup output directory is not configurable');
    $assert(str_contains($restore, "PHP_SAPI !== 'cli'"), 'restore test is not restricted to CLI');
    $assert(str_contains($restore, "'sims_restore_test_'"), 'restore test database name is not isolated');
    $assert(str_contains($restore, 'CREATE DATABASE'), 'restore test does not create an isolated database');
    $assert(!preg_match('/DROP\s+DATABASE/i', $restore), 'restore test must never drop a database');
    $assert(str_contains($restore, "SELECT COUNT(*) FROM users"), 'restore test does not validate restored account rows');
    $assert(str_contains($restore, 'archived_file_count'), 'restore test does not validate restored file count');
    $assert(str_contains($restore, '--verify-only'), 'backup verification mode is missing');
    $assert(is_file($root . '/docs/backup-restore.md'), 'backup and restore procedure is not documented');
});

$test('migration SQL parser respects quoted strings and comments', static function () use ($assert): void {
    $sql = "-- skip ;\nSELECT 'a;b'; /* block ; */ SELECT \"c;d\"; # tail ;\nSELECT `semi;colon`;";
    $statements = migration_statements($sql);
    $assert(count($statements) === 3, 'expected three SQL statements');
    $assert($statements[0] === "SELECT 'a;b'", 'single-quoted semicolon was split');
    $assert($statements[1] === 'SELECT "c;d"', 'double-quoted semicolon was split');
    $assert($statements[2] === 'SELECT `semi;colon`', 'backtick-quoted semicolon was split');
});

$test('all SQL migration files can be split safely', static function () use ($assert, $root): void {
    $files = glob($root . '/database/migrations/*.sql') ?: [];
    $assert(count($files) > 0, 'no SQL migration files found');
    foreach ($files as $file) {
        $statements = migration_statements((string)file_get_contents($file));
        $assert($statements !== [], basename($file) . ' contains no SQL statements');
    }
});

$test('migration dependencies are ordered and cycles are rejected', static function () use ($assert): void {
    $files = ['03.sql', '02.sql', '01.sql'];
    $ordered = array_map('basename', order_migrations($files, ['02.sql' => ['01.sql'], '03.sql' => ['02.sql']]));
    $assert($ordered === ['01.sql', '02.sql', '03.sql'], 'dependent migrations are out of order');
    try {
        order_migrations(['a.sql', 'b.sql'], ['a.sql' => ['b.sql'], 'b.sql' => ['a.sql']]);
        throw new RuntimeException('migration cycle was accepted');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'siklus'), 'unexpected error for migration cycle');
    }
    try {
        order_migrations(['a.sql'], ['a.sql' => ['missing.sql']]);
        throw new RuntimeException('missing migration dependency was accepted');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'tidak ditemukan'), 'unexpected error for missing dependency');
    }
});

$phpFiles = [];
$jsFiles = [];
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (preg_match('~^(?:\.git|vendor|node_modules|uploads|outputs|storage|tmp)[\\\\/]~i', $relative)) continue;
    $extension = strtolower($file->getExtension());
    if ($extension === 'php') $phpFiles[] = $file->getPathname();
    elseif ($extension === 'js') $jsFiles[] = $file->getPathname();
    elseif ($extension === 'css') $cssFiles[] = $file->getPathname();
}
sort($phpFiles);
sort($jsFiles);
sort($cssFiles);

$test('all project PHP files pass php -l', static function () use ($assert, $process, $phpFiles, $root): void {
    foreach ($phpFiles as $file) {
        [$code,, $stderr] = $process([PHP_BINARY, '-l', $file], $root);
        $assert($code === 0, basename($file) . ': ' . $stderr);
    }
});

$nodeAvailable = false;
try {
    [$nodeCode] = $process(['node', '--version'], $root);
    $nodeAvailable = $nodeCode === 0;
} catch (Throwable) {
    $nodeAvailable = false;
}
if ($nodeAvailable) {
    $test('all project JavaScript files pass node --check', static function () use ($assert, $process, $jsFiles, $root): void {
        foreach ($jsFiles as $file) {
            [$code,, $stderr] = $process(['node', '--check', $file], $root);
            $assert($code === 0, basename($file) . ': ' . $stderr);
        }
    });
} else {
    $report('SKIP', 'JavaScript syntax checks require Node.js');
}

$test('project CSS files have balanced braces', static function () use ($assert, $cssFiles): void {
    foreach ($cssFiles as $file) {
        $css = (string)file_get_contents($file);
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
        $css = preg_replace('/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/s', '', $css) ?? $css;
        $depth = 0;
        foreach (str_split($css) as $char) {
            if ($char === '{') $depth++;
            elseif ($char === '}') $depth--;
            $assert($depth >= 0, basename($file) . ' has an extra closing brace');
        }
        $assert($depth === 0, basename($file) . ' has unclosed CSS blocks');
    }
});

$httpBase = rtrim((string)(getenv('SIMS_TEST_BASE_URL') ?: 'http://localhost/sims_smpn4'), '/');
$publicRoutes = [
    '/',
    '/login.php',
    '/public/',
    '/public/akademik.php',
    '/public/agenda.php',
    '/public/berita.php',
    '/public/ekstrakurikuler.php',
    '/public/fasilitas.php',
    '/public/galeri.php',
    '/public/guru.php',
    '/public/jadwal.php',
    '/public/kalender.php',
    '/public/kontak.php',
    '/public/kurikulum.php',
    '/public/pencarian.php',
    '/public/pengumuman.php',
    '/public/ppdb.php',
    '/public/prestasi.php',
    '/public/profil.php',
    '/public/sejarah.php',
    '/public/struktur.php',
    '/public/unduhan.php',
    '/public/visi-misi.php',
    '/public/privasi.php',
    '/public/cek_pendaftaran.php',
];
if (!function_exists('curl_init')) {
    if (getenv('SIMS_TEST_HTTP') === '1') {
        $report('FAIL', 'HTTP smoke tests require the PHP cURL extension');
    } else {
        $report('SKIP', 'HTTP smoke tests require the PHP cURL extension');
    }
} else {
    $probe = curl_init($httpBase . '/login.php');

    curl_setopt_array($probe, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 6,
    ]);

    $probeBody = curl_exec($probe);
    $probeCode = (int) curl_getinfo($probe, CURLINFO_RESPONSE_CODE);
    $probeError = curl_error($probe);
    curl_close($probe);

    $serverAvailable = $probeBody !== false && $probeCode > 0;

    if (!$serverAvailable) {
        if (getenv('SIMS_TEST_HTTP') === '1') {
            $report(
                'FAIL',
                'HTTP server unavailable at ' . $httpBase .
                ' — ' . ($probeError ?: 'HTTP ' . $probeCode)
            );
        } else {
            $report('SKIP', 'Local web server unavailable; HTTP checks skipped');
        }
    } else {
        $test('local login and public routes return HTTP 200 without server errors', static function () use ($assert, $httpBase, $publicRoutes): void {
            foreach ($publicRoutes as $route) {
                $curl = curl_init($httpBase . $route);

                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 6,
                ]);

                $body = curl_exec($curl);
                $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $error = curl_error($curl);
                curl_close($curl);

                $assert(
                    $body !== false && $code === 200,
                    $route . ' returned HTTP ' . $code .
                    ($error !== '' ? ': ' . $error : '')
                );

                $assert(
                    !str_contains((string) $body, 'Terjadi kesalahan pada sistem. Silakan coba kembali.'),
                    $route . ' rendered the generic server-error page'
                );

                $assert(
                    !preg_match('/(?:Fatal error|Uncaught Error|Parse error|Warning:|Notice:)/i', (string) $body),
                    $route . ' rendered a PHP error'
                );
            }
        });

        $test('standalone protected pages require login without PHP fatal errors', static function () use ($assert, $httpBase): void {
            foreach ([
                '/activity.php',
                '/biodata.php',
                '/bulk_absensi.php',
                '/learning.php',
                '/moderation.php',
                '/notifications.php',
                '/passwords.php',
                '/pesan_masuk.php',
                '/index.php?module=ppdb_admin',
                '/index.php?module=portal_admin',
                '/profile.php',
                '/reports.php',
                '/search.php',
                '/student.php',
                '/teacher.php',
                '/users.php',
                '/api/notification_count.php',
            ] as $route) {
                $curl = curl_init($httpBase . $route);

                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 6,
                ]);

                $body = curl_exec($curl);
                $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $error = curl_error($curl);
                curl_close($curl);

                $assert(
                    $body !== false && $code === 200,
                    $route . ' returned HTTP ' . $code .
                    ($error !== '' ? ': ' . $error : '')
                );

                $assert(
                    str_contains((string) $body, '<title>Login |'),
                    $route . ' did not redirect an anonymous visitor to login'
                );

                $assert(
                    !preg_match('/(?:Fatal error|Uncaught Error|Parse error|Warning:|Notice:)/i', (string) $body),
                    $route . ' rendered a PHP error'
                );
            }
        });
    }
}
$databaseName = (string)(getenv('SIMS_DB_NAME') ?: 'sims_db');
if (strtolower((string)(getenv('SIMS_APP_ENV') ?: 'local')) === 'production') {
    $report('SKIP', 'Database integrity checks are disabled in production');
} else {
    try {
        $host = (string)(getenv('SIMS_DB_HOST') ?: '127.0.0.1');
        $port = (int)(getenv('SIMS_DB_PORT') ?: 3306);
        $user = (string)(getenv('SIMS_DB_USER') ?: 'root');
        $password = (string)(getenv('SIMS_DB_PASS') ?: '');
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $test('local database has a clean migration ledger and matching checksums', static function () use ($assert, $pdo, $root, $databaseName): void {
            $actual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $assert($actual === $databaseName, 'connected database does not match SIMS_DB_NAME');
            $ledger = $pdo->query("SELECT migration_name,checksum,status FROM schema_migrations")->fetchAll();
            $recorded = [];
            foreach ($ledger as $row) $recorded[(string)$row['migration_name']] = $row;
            $files = glob($root . '/database/migrations/*.sql') ?: [];
            foreach ($files as $file) {
                $name = basename($file);
                $assert(isset($recorded[$name]), "{$name} is missing from schema_migrations");
                $row = $recorded[$name];
                $assert(in_array($row['status'], ['applied', 'reconciled'], true), "{$name} status is not applied");
                $assert(hash_equals((string)$row['checksum'], hash_file('sha256', $file)), "{$name} checksum changed");
            }
            $assert(count($recorded) === count($files), 'migration ledger contains entries without SQL files');
        });
    } catch (Throwable $exception) {
        if (getenv('SIMS_TEST_DB') === '1') $report('FAIL', 'Local database checks — ' . $exception->getMessage());
        else $report('SKIP', 'Local database unavailable; set SIMS_TEST_DB=1 to require it');
    }
}

echo "\nRingkasan tes: {$passed} lulus, {$failed} gagal, {$skipped} dilewati. PHP=" . count($phpFiles) . ", JS=" . count($jsFiles) . ", CSS=" . count($cssFiles) . "\n";
exit($failed > 0 ? 1 : 0);
