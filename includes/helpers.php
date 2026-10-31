<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_log(string $level, string $event, array $context = []): string
{
    $reference = bin2hex(random_bytes(6));
    $safeContext = [];
    foreach ($context as $key => $value) {
        if (preg_match('/password|secret|token|authorization|cookie/i', (string)$key)) continue;
        if (is_scalar($value) || $value === null) $safeContext[(string)$key] = $value;
    }
    $entry = [
        'time' => date(DATE_ATOM),
        'level' => strtoupper($level),
        'reference' => $reference,
        'event' => $event,
        'context' => $safeContext,
    ];
    $directory = (string)(getenv('SIMS_LOG_DIR') ?: dirname(__DIR__) . '/storage/logs');
    if (!is_dir($directory)) @mkdir($directory, 0750, true);
    $logFile = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sims-' . date('Y-m-d') . '.log';
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
    if (!is_dir($directory) || @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[SIMS][' . $reference . '] ' . $event);
    }
    return $reference;
}

if (strtolower((string)(getenv('SIMS_APP_ENV') ?: 'local')) === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

set_exception_handler(static function (Throwable $exception): void {
    $reference = app_log('error', 'Unhandled exception', [
        'type' => get_class($exception),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'SIMS error [' . $reference . ']. Check the application log.' . PHP_EOL);
        return;
    }
    if (!headers_sent()) http_response_code(500);
    echo 'Terjadi kesalahan pada sistem. Silakan coba kembali. Kode referensi: ' . e($reference);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    if ($severity >= E_WARNING) {
        app_log('warning', 'PHP runtime warning', [
            'severity' => $severity,
            'file' => basename($file),
            'line' => $line,
        ]);
    }
    return false;
});

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function db_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table . $column)) return false;
    $key = strtolower($table . '.' . $column);
    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    }
    return $cache[$key];
}
function app_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (basename($base) === 'api') $base = rtrim(str_replace('\\', '/', dirname($base)), '/');
    if ($base === '.' || $base === '/') $base = '';
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function verify_csrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi telah kedaluwarsa. Muat ulang halaman.');
    }
}
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}
function take_flash(): ?array
{
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}
function require_login(): void
{
    if (!is_logged_in()) {
        redirect(app_url('login.php'));
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT username,nama_lengkap,role,aktif,guru_id,siswa_id,foto_path,password_must_change FROM users WHERE id=? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $account = $stmt->fetch();
        if (!$account || (int)$account['aktif'] !== 1) {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect('login.php?inactive=1');
        }
        $_SESSION['username'] = $account['username'];
        $_SESSION['full_name'] = $account['nama_lengkap'];
        $_SESSION['role'] = $account['role'];
        $_SESSION['linked_guru_id'] = $account['guru_id'] ? (int)$account['guru_id'] : null;
        $_SESSION['linked_siswa_id'] = $account['siswa_id'] ? (int)$account['siswa_id'] : null;
        $_SESSION['profile_photo'] = $account['foto_path'] ?: null;
        $_SESSION['password_must_change'] = (int)$account['password_must_change'];
    }
    if (!empty($_SESSION['password_must_change']) && basename((string)($_SERVER['PHP_SELF'] ?? '')) !== 'profile.php') {
        redirect(app_url('profile.php?force_password=1'));
    }
    $currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    if ($pdo instanceof PDO) {
        $schema = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'");
        $schema->execute();
        if ((int)$schema->fetchColumn() === 1) {
            $approval = $pdo->prepare('SELECT status_verifikasi FROM biodata_pengguna WHERE user_id=?');
            $approval->execute([(int)$_SESSION['user_id']]);
            $approved = $approval->fetchColumn() === 'terverifikasi';
            $approvalRequired = current_role() !== 'admin';
            if (current_role() === 'admin' && !$approved) {
                $otherApprovedAdmin = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN biodata_pengguna b ON b.user_id=u.id WHERE u.role='admin' AND u.aktif=1 AND u.id<>? AND b.status_verifikasi='terverifikasi'");
                $otherApprovedAdmin->execute([(int)$_SESSION['user_id']]);
                $approvalRequired = (int)$otherApprovedAdmin->fetchColumn() > 0;
            }
            if (!$approved && $approvalRequired) {
                if ($currentPage === 'profile.php' && !empty($_SESSION['password_must_change'])) return;
                if ($currentPage === 'profile.php') redirect(app_url('biodata.php?menunggu=1'));
                if (!in_array($currentPage, ['biodata.php', 'logout.php'], true)) redirect(app_url('biodata.php?menunggu=1'));
            }
        }
    }
}
function current_user(): string
{
    return (string)($_SESSION['full_name'] ?? 'Administrator');
}
function current_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}
function role_label(?string $role = null): string
{
    $role ??= current_role();
    return [
        'admin' => 'Super Admin',
        'operator' => 'Operator',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
        'kepala_sekolah' => 'Kepala Sekolah',
    ][$role] ?? 'Pengguna';
}
function can_view_module(string $module): bool
{
    // Data kelas dan mapel dibatasi untuk pengelola serta Kepala Sekolah; Guru mengakses kelas tugasnya melalui dashboard Guru.
    if (in_array($module, ['kelas', 'mapel'], true)) {
        return in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true);
    }
    if ($module === 'moderasi_konten') return current_role() === 'admin';
    if ($module === 'notifikasi') {
        return in_array(current_role(), ['admin', 'operator', 'guru', 'siswa', 'kepala_sekolah'], true);
    }
    if ($module === 'prestasi') {
        return in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true);
    }
    if ($module === 'rencana_ajar') {
        return in_array(current_role(), ['admin', 'operator', 'guru', 'kepala_sekolah'], true);
    }
    if ($module === 'profil') {
        return in_array(current_role(), ['admin', 'operator', 'guru', 'siswa', 'kepala_sekolah'], true);
    }
    if (in_array(current_role(), ['kepala_sekolah', 'guru', 'siswa'], true)) {
        return false;
    }
    if (in_array($module, ['profil_sekolah', 'pengguna', 'log_aktivitas'], true)) {
        return current_role() === 'admin';
    }
    return in_array(current_role(), ['admin', 'operator', 'guru'], true);
}
function can_manage_module(string $module): bool
{
    if (current_role() === 'admin') {
        return true;
    }
    if (current_role() === 'operator') {
        return in_array($module, [
            'siswa',
            'guru',
            'kelas',
            'mapel',
            // Operator harus dapat mengelola tahun ajaran agar kelas baru bisa ditautkan ke periode yang valid.
            'tahun_ajaran',
            'nilai',
            'absensi',
            'absensi_guru',
            'jadwal',
            'pengumuman',
            'berita_sekolah',
            'galeri_sekolah',
            'kalender_akademik',
            'kelas_berlangsung',
            'kelas_binaan',
            'rencana_ajar',
            'kokurikuler',
            'prestasi',
            'notifikasi'
        ], true);
    }
    return false;
}
function require_module_access(string $module, bool $write = false): void
{
    $allowed = $write ? can_manage_module($module) : can_view_module($module);
    if (!$allowed) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses tindakan ini.');
    }
}
function record_activity(PDO $pdo, string $action, string $entity, ?int $recordId = null, ?string $detail = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO log_aktivitas (user_id,aksi,entitas,record_id,detail) VALUES (?,?,?,?,?)');
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $entity, $recordId, $detail]);
    } catch (PDOException $e) {
        // Pencatatan audit tidak boleh menggagalkan transaksi bisnis.
        app_log('warning', 'Activity log write failed', ['entity' => $entity]);
    }
}
function field_label(string $key): string
{
    return ucwords(str_replace('_', ' ', $key));
}
function store_user_photo(array $upload): array
{
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'Ukuran pasfoto melewati batas unggah server. Batas aplikasi 2 MB.'];
        return [null, 'Unggahan pasfoto tidak berhasil. Pilih gambar kembali.'];
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if (!is_uploaded_file($tmp) || $size < 1 || $size > 2 * 1024 * 1024) return [null, 'Pasfoto harus berupa unggahan valid dengan ukuran maksimal 2 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $image = @getimagesize($tmp);
    if (!isset($formats[$mime]) || !$image || ($image['mime'] ?? '') !== $mime) return [null, 'Pasfoto harus berupa JPG, PNG, atau WebP yang valid.'];
    if ((int)$image[0] < 64 || (int)$image[1] < 64 || (int)$image[0] > 5000 || (int)$image[1] > 5000) return [null, 'Dimensi pasfoto minimal 64 × 64 dan maksimal 5.000 × 5.000 piksel.'];
    $directory = dirname(__DIR__) . '/uploads/profil';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Folder uploads/profil tidak dapat dibuat.'];
    $filename = bin2hex(random_bytes(20)) . '.' . $formats[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) return [null, 'Pasfoto gagal disimpan. Periksa izin folder uploads/profil.'];
    return ['uploads/profil/' . $filename, null];
}
function remove_user_photo(mixed $relativePath): void
{
    $relativePath = (string)$relativePath;
    if (!preg_match('~^uploads/profil/([a-f0-9]{40}\.(?:jpg|png|webp))$~i', $relativePath, $match)) return;
    $root = realpath(dirname(__DIR__) . '/uploads/profil');
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
    if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}
function store_biodata_document(array $upload): array
{
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($uploadError !== UPLOAD_ERR_OK) return [null, 'Unggahan dokumen gagal. Maksimal 5 MB per dokumen.'];
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if (!is_uploaded_file($tmp) || $size < 1 || $size > 5 * 1024 * 1024) return [null, 'Dokumen harus berupa unggahan valid dengan ukuran maksimal 5 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $formats = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($formats[$mime])) return [null, 'Dokumen hanya boleh PDF, JPG, atau PNG.'];
    if ($mime !== 'application/pdf') {
        $image = @getimagesize($tmp);
        if (!$image || ($image['mime'] ?? '') !== $mime) return [null, 'File gambar tidak valid.'];
    } else {
        $handle = @fopen($tmp, 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if ($handle) fclose($handle);
        if ($signature !== '%PDF-') return [null, 'File PDF tidak valid.'];
    }
    $directory = dirname(__DIR__) . '/storage/biodata_documents';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Folder penyimpanan dokumen tidak dapat dibuat.'];
    $filename = bin2hex(random_bytes(20)) . '.' . $formats[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) return [null, 'Dokumen gagal disimpan. Periksa izin folder storage/biodata_documents.'];
    return ['storage/biodata_documents/' . $filename, null];
}
function remove_biodata_document(mixed $relativePath): void
{
    $relativePath = (string)$relativePath;
    if (!preg_match('~^storage/biodata_documents/([a-f0-9]{40}\.(?:pdf|jpg|png))$~i', $relativePath, $match)) return;
    $root = realpath(dirname(__DIR__) . '/storage/biodata_documents');
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
    if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}
