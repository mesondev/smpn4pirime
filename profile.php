<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id,username,nama_lengkap,password_hash,foto_path,password_must_change FROM users WHERE id=? AND aktif=1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(403);
    exit('Akun tidak aktif atau tidak ditemukan.');
}
$error = '';
$forcePasswordChange = (int)($_SESSION['password_must_change'] ?? 0) === 1;
$newPhoto = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['nama_lengkap'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $photoUpload = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $removePhoto = (string)($_POST['hapus_foto'] ?? '') === '1';

    if ($name === '' || $username === '') {
        $error = 'Nama lengkap dan username wajib diisi.';
    } elseif (strlen($username) > 60 || strlen($name) > 120) {
        $error = 'Nama atau username melebihi batas karakter.';
    } elseif ($newPassword !== '' && !password_verify($currentPassword, $user['password_hash'])) {
        $error = 'Password saat ini tidak sesuai.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 10) {
        $error = 'Password baru minimal 10 karakter.';
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = 'Konfirmasi password baru tidak sama.';
    } elseif ($forcePasswordChange && $newPassword === '') {
        $error = 'Anda harus membuat password baru sebelum melanjutkan.';
    }
    if ($error === '' && $photoUpload) {
        [$newPhoto, $photoError] = store_user_photo($_FILES['foto']);
        if ($photoError) $error = $photoError;
    }
    if ($error === '') {
        try {
            $photoPath = $newPhoto ?? ($removePhoto ? null : ($user['foto_path'] ?? null));
            if ($newPassword !== '') {
                $update = $pdo->prepare('UPDATE users SET username=?,nama_lengkap=?,password_hash=?,password_must_change=0,foto_path=? WHERE id=?');
                $update->execute([$username, $name, password_hash($newPassword, PASSWORD_DEFAULT), $photoPath, $userId]);
            } else {
                $update = $pdo->prepare('UPDATE users SET username=?,nama_lengkap=?,foto_path=? WHERE id=?');
                $update->execute([$username, $name, $photoPath, $userId]);
            }
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $name;
            $_SESSION['profile_photo'] = $photoPath;
            $_SESSION['password_must_change'] = 0;
            if (($newPhoto !== null || $removePhoto) && !empty($user['foto_path'])) remove_user_photo($user['foto_path']);
            record_activity($pdo, 'ubah profil', 'users', $userId);
            flash('Profil berhasil diperbarui.');
            redirect('profile.php');
        } catch (PDOException $e) {
            if ($newPhoto !== null) remove_user_photo($newPhoto);
            $error = 'Profil gagal disimpan. Username mungkin sudah digunakan.';
        }
    }
    $user['nama_lengkap'] = $name;
    $user['username'] = $username;
}

$pageTitle = 'Profil Akun';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Profil Akun</h1>
        <p>Perbarui identitas, pasfoto, dan keamanan akun Anda.</p>
    </div>
</div>
<div class="card p-3 p-md-4" style="max-width:760px">
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($forcePasswordChange): ?><div class="alert alert-warning">Password awal atau password hasil reset harus diganti sebelum Anda membuka menu lain.</div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="col-md-6"><label class="form-label" for="nama_lengkap">Nama Lengkap</label><input class="form-control" id="nama_lengkap" name="nama_lengkap" maxlength="120" value="<?= e($user['nama_lengkap']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" maxlength="60" value="<?= e($user['username']) ?>" required></div>
        <div class="col-12"><label class="form-label" for="foto">Pasfoto (JPG/PNG/WebP, maksimal 2 MB)</label><?php if(!empty($user['foto_path'])): ?><div class="mb-2"><img src="media/profile.php?id=<?= $userId ?>" alt="Pasfoto akun" width="100" height="100" class="rounded-circle object-fit-cover"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="hapus_foto" name="hapus_foto" value="1"><label class="form-check-label" for="hapus_foto">Hapus pasfoto saat menyimpan</label></div></div><?php endif; ?><input class="form-control" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"><div class="form-text">Foto akun hanya terlihat oleh Anda dan Super Admin.</div></div>
        <div class="col-12">
            <hr>
        <h2 class="h6"><?= $forcePasswordChange ? 'Ganti Password Awal' : 'Ganti Password (opsional)' ?></h2>
            <p class="small text-secondary">Kosongkan bagian ini jika password tidak ingin diubah.</p>
        </div>
        <div class="col-md-4"><label class="form-label" for="current_password">Password Saat Ini</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label" for="new_password">Password Baru</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="10" autocomplete="new-password"></div>
        <div class="col-md-4"><label class="form-label" for="confirm_password">Ulangi Password Baru</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password"></div>
        <div class="col-12"><button class="btn btn-primary">Simpan Profil</button></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
