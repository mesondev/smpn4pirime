<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
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

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        redirect('login.php');
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
        redirect('profile.php?force_password=1');
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
            'kelas',
            'nilai',
            'absensi',
            'absensi_guru',
            'jadwal',
            'pengumuman',
            'kalender_akademik',
            'kelas_berlangsung',
            'kelas_binaan',
            'rencana_ajar',
            'kokurikuler',
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
