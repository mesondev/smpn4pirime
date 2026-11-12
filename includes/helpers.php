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

// Write structured operational logs while excluding credential-like fields from context.
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

// Escape dynamic values before placing them in HTML text or attributes.
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
// Build a project-relative URL that also works when the app is installed in a subfolder.
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
// Reject state-changing requests unless their session-bound token matches.
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
// Refresh authorization data from the database so stale session roles cannot grant access.
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
        if (!$account || (int)$account['aktif'] !== 1 || !is_valid_role((string)($account['role'] ?? ''))) {
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
            $approvalStatus = (string)($approval->fetchColumn() ?: 'belum_lengkap');
            $_SESSION['biodata_status'] = $approvalStatus;
            $approved = $approvalStatus === 'terverifikasi';
            $approvalRequired = current_role() !== 'admin';
            if (current_role() === 'admin' && !$approved) {
                $otherApprovedAdmin = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN biodata_pengguna b ON b.user_id=u.id WHERE u.role='admin' AND u.aktif=1 AND u.id<>? AND b.status_verifikasi='terverifikasi'");
                $otherApprovedAdmin->execute([(int)$_SESSION['user_id']]);
                $approvalRequired = (int)$otherApprovedAdmin->fetchColumn() > 0;
            }
            $pendingStudentDashboard = current_role() === 'siswa'
                && $currentPage === 'student.php'
                && (string)($_GET['view'] ?? 'dashboard') === 'dashboard'
                && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
            if (!$approved && $approvalRequired && !$pendingStudentDashboard) {
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
function is_valid_role(string $role): bool
{
    return in_array($role, ['admin', 'operator', 'kepala_sekolah', 'guru', 'siswa'], true);
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

/** Return safe display defaults for a logged-in account. */
function normalize_user_display_preferences(array $preferences): array
{
    $defaults = [
        'tema' => 'sistem',
        'ukuran_teks' => 'normal',
        'kerapatan_tabel' => 'normal',
        'sidebar_ciut' => 0,
        'periode_grafik' => 'month',
    ];

    $allowed = [
        'tema' => ['sistem', 'terang', 'gelap'],
        'ukuran_teks' => ['kecil', 'normal', 'besar'],
        'kerapatan_tabel' => ['ringkas', 'normal', 'nyaman'],
        'periode_grafik' => ['month', '3months', '6months', '12months'],
    ];

    foreach ($allowed as $key => $values) {
        $candidate = (string) ($preferences[$key] ?? $defaults[$key]);
        $defaults[$key] = in_array($candidate, $values, true) ? $candidate : $defaults[$key];
    }

    $defaults['sidebar_ciut'] = (int) (($preferences['sidebar_ciut'] ?? 0) === 1
        || ($preferences['sidebar_ciut'] ?? 0) === '1');

    return $defaults;
}

/** Read personal display settings; missing rows use safe application defaults. */
function user_display_preferences(PDO $pdo, ?int $userId = null): array
{
    $userId ??= (int) ($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        return normalize_user_display_preferences([]);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT tema, ukuran_teks, kerapatan_tabel, sidebar_ciut, periode_grafik
             FROM preferensi_pengguna
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        return normalize_user_display_preferences($stmt->fetch() ?: []);
    } catch (PDOException) {
        // Halaman tetap dapat dibuka saat skema preferensi belum tersedia.
        return normalize_user_display_preferences([]);
    }
}

/** Persist only validated personal appearance preferences for the current account. */
function save_user_display_preferences(PDO $pdo, int $userId, array $preferences): void
{
    if ($userId < 1) {
        throw new InvalidArgumentException('Akun preferensi tidak valid.');
    }

    $normalized = normalize_user_display_preferences($preferences);
    $stmt = $pdo->prepare(
        'INSERT INTO preferensi_pengguna
            (user_id, tema, ukuran_teks, kerapatan_tabel, sidebar_ciut, periode_grafik)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            tema = VALUES(tema),
            ukuran_teks = VALUES(ukuran_teks),
            kerapatan_tabel = VALUES(kerapatan_tabel),
            sidebar_ciut = VALUES(sidebar_ciut),
            periode_grafik = VALUES(periode_grafik)'
    );
    $stmt->execute([
        $userId,
        $normalized['tema'],
        $normalized['ukuran_teks'],
        $normalized['kerapatan_tabel'],
        $normalized['sidebar_ciut'],
        $normalized['periode_grafik'],
    ]);
}

// Centralize read permissions so navigation visibility and route checks use the same policy.
function can_view_module(string $module): bool
{
    if (!is_valid_role(current_role())) return false;
    if (current_role() === 'admin') return true;
    $dynamicPermission = dynamic_module_permission($module, 'view');
    if ($dynamicPermission !== null) return $dynamicPermission;
    // Data kelas dan mapel dibatasi untuk pengelola serta Kepala Sekolah; Guru mengakses kelas tugasnya melalui dashboard Guru.
    if (in_array($module, ['kelas', 'mapel'], true)) {
        return in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true);
    }
    if ($module === 'moderasi_konten') return current_role() === 'admin';
    if ($module === 'staf') return in_array(current_role(), ['admin', 'operator'], true);
    if ($module === 'notifikasi') {
        return in_array(current_role(), ['admin', 'operator', 'guru', 'siswa', 'kepala_sekolah'], true);
    }
    if ($module === 'pesan_masuk') return current_role() === 'admin';
    if ($module === 'ppdb_admin') return current_role() === 'admin';
    if ($module === 'portal_admin' || str_starts_with($module, 'portal_')) return current_role() === 'admin';
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
// Centralize write permissions separately from read access.
function can_manage_module(string $module): bool
{
    if (!is_valid_role(current_role())) return false;
    if (current_role() === 'admin') {
        return true;
    }
    $dynamicPermission = dynamic_module_permission($module, 'manage');
    if ($dynamicPermission !== null) return $dynamicPermission;
    if (current_role() === 'operator') {
        return in_array($module, [
            'siswa',
            'guru',
            'staf',
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
/** Return null until the RBAC schema/permission is present; otherwise enforce the stored grant. */
function dynamic_module_permission(string $module, string $action): ?bool
{
    static $cache = [];
    if (!in_array($action, ['view', 'manage'], true) || !preg_match('/^[a-z0-9_]+$/', $module)) return null;
    $role = current_role();
    $cacheKey = $role . ':' . $module . ':' . $action;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) return $cache[$cacheKey] = null;
    try {
        if (!db_has_column($pdo, 'roles', 'slug') || !db_has_column($pdo, 'permissions', 'module_key') || !db_has_column($pdo, 'role_permissions', 'granted')) {
            return $cache[$cacheKey] = null;
        }
        $permission = $pdo->prepare('SELECT id FROM permissions WHERE module_key=? AND action_name=? LIMIT 1');
        $permission->execute([$module, $action]);
        $permissionId = $permission->fetchColumn();
        if ($permissionId === false) return $cache[$cacheKey] = null;
        $grant = $pdo->prepare('SELECT rp.granted FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.slug=? AND rp.permission_id=? LIMIT 1');
        $grant->execute([$role, (int)$permissionId]);
        $value = $grant->fetchColumn();
        return $cache[$cacheKey] = $value === false ? false : (int)$value === 1;
    } catch (PDOException $exception) {
        app_log('warning', 'Dynamic permission lookup failed', ['module' => $module, 'action' => $action]);
        return $cache[$cacheKey] = null;
    }
}
function require_module_access(string $module, bool $write = false): void
{
    $allowed = $write ? can_manage_module($module) : can_view_module($module);
    if (!$allowed) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses tindakan ini.');
    }
}
function audit_safe_snapshot(?array $values): ?string
{
    if ($values === null) return null;
    $safe = [];
    foreach ($values as $key => $value) {
        $field = (string)$key;
        if (preg_match('/password|secret|token|cookie|authorization|credential|hash|email|phone|telepon|alamat|address|nama|username|nik|nis|siswa_id|guru_id|akta|dokumen|file|foto|photo|path|session|pesan|catatan|isi|jawaban|deskripsi|instruksi/i', $field)) continue;
        if (!is_scalar($value) && $value !== null) continue;
        $safe[$field] = is_string($value) ? (function_exists('mb_substr') ? mb_substr($value, 0, 1000) : substr($value, 0, 1000)) : $value;
    }
    return $safe ? json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
}
function record_activity(PDO $pdo, string $action, string $entity, ?int $recordId = null, ?string $detail = null, ?array $oldValues = null, ?array $newValues = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO log_aktivitas (user_id,aksi,entitas,record_id,detail) VALUES (?,?,?,?,?)');
        $stmt->execute([$_SESSION['user_id'] ?? null, $action, $entity, $recordId, $detail]);
    } catch (PDOException $e) {
        // Pencatatan audit tidak boleh menggagalkan transaksi bisnis.
        app_log('warning', 'Activity log write failed', ['entity' => $entity]);
    }
    try {
        if (!db_has_column($pdo, 'audit_logs', 'old_data')) return;
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_user_id,action_name,module_key,record_id,old_data,new_data,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)');
        $audit->execute([
            $_SESSION['user_id'] ?? null,
            function_exists('mb_substr') ? mb_substr($action, 0, 80) : substr($action, 0, 80),
            function_exists('mb_substr') ? mb_substr($entity, 0, 100) : substr($entity, 0, 100),
            $recordId,
            audit_safe_snapshot($oldValues),
            audit_safe_snapshot($newValues),
            filter_var($_SERVER['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP) ? (string)$_SERVER['REMOTE_ADDR'] : null,
            (function_exists('mb_substr') ? mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) : substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)) ?: null,
        ]);
    } catch (Throwable $exception) {
        app_log('warning', 'Structured audit write failed', ['entity' => $entity]);
    }
}
function field_label(string $key): string
{
    return ucwords(str_replace('_', ' ', $key));
}
// Validate image content and store it under an unguessable server-generated filename.
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
// Validate private biodata documents by MIME and signature before storing outside public access.
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
