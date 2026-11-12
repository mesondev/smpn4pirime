<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id,username,nama_lengkap,role,created_at,password_hash,foto_path,password_must_change FROM users WHERE id=? AND aktif=1');
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
$showAccountForm = $forcePasswordChange || $error !== '';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div><span class="account-eyebrow">Pengaturan Pengguna</span>
        <h1>Profil Akun</h1>
        <p>Atur identitas akun dan keamanan login Anda dalam satu halaman.</p>
    </div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($forcePasswordChange): ?><div class="alert alert-warning" role="alert"><strong>Perubahan kata sandi diperlukan sebelum membuka dashboard.</strong>
        <ol class="mb-0 mt-2">
            <li>Masukkan kata sandi sementara pada kolom <em>Kata Sandi Saat Ini</em>.</li>
            <li>Buat kata sandi baru minimal 10 karakter, lalu ulangi pada kolom konfirmasi.</li>
            <li>Pilih <strong>Simpan Perubahan</strong>.</li>
        </ol>
    </div><?php endif; ?>
<div class="account-profile-page">
    <section class="card account-profile-hero" aria-label="Identitas profil">
        <div class="account-hero-photo-wrap"><?php if (!empty($user['foto_path'])): ?><img class="account-hero-photo" id="profilePhotoPreview" src="media/profile.php?id=<?= $userId ?>" alt="Foto profil <?= e($user['nama_lengkap']) ?>"><?php else: ?><span class="account-hero-photo account-hero-photo-fallback" id="profilePhotoFallback" aria-label="Inisial nama"><?= e(mb_strtoupper(mb_substr($user['nama_lengkap'], 0, 1))) ?></span><img class="account-hero-photo d-none" id="profilePhotoPreview" alt="Pratinjau foto profil"><?php endif; ?></div>
        <div class="account-hero-content"><span class="account-hero-kicker"><i class="bi bi-person-badge"></i> Profil <?= e(role_label($user['role'])) ?></span>
            <h2><?= e($user['nama_lengkap']) ?></h2>
            <p class="account-hero-username">@<?= e($user['username']) ?></p>
            <div class="account-hero-badges"><span class="account-role-badge"><i class="bi bi-person-vcard"></i><?= e(role_label($user['role'])) ?></span><span class="account-active-badge"><i class="bi bi-check-circle-fill"></i> Akun aktif</span></div>
            <dl class="account-hero-meta">
                <div>
                    <dt>Status akun</dt>
                    <dd>Aktif · dapat digunakan untuk masuk</dd>
                </div>
                <div>
                    <dt>Terdaftar sejak</dt>
                    <dd><?= e(date('d/m/Y', strtotime((string)$user['created_at']))) ?></dd>
                </div>
                <div>
                    <dt>ID akun</dt>
                    <dd>#<?= (int)$user['id'] ?></dd>
                </div>
            </dl>
            <p class="account-hero-privacy"><i class="bi bi-shield-lock"></i> Informasi profil ini hanya dapat dilihat dan diubah oleh pemilik akun.</p>
            <button class="btn btn-primary account-edit-toggle" type="button" id="accountFormToggle" aria-expanded="<?= $showAccountForm ? 'true' : 'false' ?>" aria-controls="accountFormPanel"><i class="bi <?= $showAccountForm ? 'bi-dash-lg' : 'bi-plus-lg' ?>" id="accountFormToggleIcon"></i><span id="accountFormToggleLabel"><?= $showAccountForm ? 'Tutup pengaturan' : 'Tambah / Ubah Informasi Akun' ?></span></button>
        </div>
    </section>
    <div class="account-edit-modal" id="accountFormPanel" <?= $showAccountForm ? '' : 'hidden' ?>><button class="account-modal-backdrop" type="button" data-account-modal-close aria-label="Tutup formulir profil"></button>
        <section class="card account-form-card account-form-panel" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle" tabindex="-1">
            <header class="account-modal-heading">
                <div><span class="account-eyebrow">Pengaturan akun</span>
                    <h2 id="accountModalTitle">Informasi Akun &amp; Keamanan</h2>
                    <p>Perbarui identitas login, foto profil, atau kata sandi.</p>
                </div><button class="account-modal-close" type="button" data-account-modal-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </header>
            <nav class="account-section-nav" aria-label="Bagian profil"><a class="active" href="#accountIdentity"><i class="bi bi-person-lines-fill"></i> Informasi Akun</a><a href="#accountSecurity"><i class="bi bi-shield-lock"></i> Keamanan</a></nav>
            <form method="post" enctype="multipart/form-data" class="account-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <section id="accountIdentity" class="account-form-section">
                    <div class="account-section-heading"><span class="account-section-icon"><i class="bi bi-person-vcard"></i></span>
                        <div>
                            <h2>Informasi Akun</h2>
                            <p>Nama dan username yang digunakan pada SIMS.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="nama_lengkap">Nama Tampilan</label><input class="form-control" id="nama_lengkap" name="nama_lengkap" maxlength="120" value="<?= e($user['nama_lengkap']) ?>" autocomplete="name" required></div>
                        <div class="col-md-6"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" maxlength="60" value="<?= e($user['username']) ?>" autocomplete="username" required>
                            <div class="form-text">Username harus unik dan dipakai untuk login.</div>
                        </div>
                        <div class="col-md-6"><label class="form-label" for="accountRole">Peran</label><input class="form-control" id="accountRole" value="<?= e(role_label($user['role'])) ?>" readonly></div>
                        <div class="col-md-6"><label class="form-label" for="accountStatus">Status Akun</label><input class="form-control account-status-input" id="accountStatus" value="Aktif" readonly></div>
                        <div class="col-12"><label class="form-label" for="foto">Foto Profil</label><input class="form-control" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB. Email dan nomor telepon dapat diisi melalui halaman Biodata Saya.</div><?php if (!empty($user['foto_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="hapus_foto" name="hapus_foto" value="1"><label class="form-check-label" for="hapus_foto">Hapus foto profil saat menyimpan</label></div><?php endif; ?>
                        </div>
                    </div>
                </section>
                <section id="accountSecurity" class="account-form-section account-security-section">
                    <div class="account-section-heading"><span class="account-section-icon security"><i class="bi bi-shield-lock"></i></span>
                        <div>
                            <h2><?= $forcePasswordChange ? 'Buat Kata Sandi Baru' : 'Keamanan Akun' ?></h2>
                            <p><?= $forcePasswordChange ? 'Perbarui kata sandi awal untuk melindungi akun.' : 'Kosongkan bagian ini jika Anda tidak ingin mengganti kata sandi.' ?></p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="current_password">Kata Sandi Saat Ini</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" <?= $forcePasswordChange ? 'required' : '' ?>></div>
                        <div class="col-md-4"><label class="form-label" for="new_password">Kata Sandi Baru</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="10" autocomplete="new-password" <?= $forcePasswordChange ? 'required' : '' ?>>
                            <div class="account-password-meter" id="passwordMeter" aria-live="polite"><span></span><span></span><span></span><span></span></div><small class="account-password-hint" id="passwordHint">Minimal 10 karakter.</small>
                        </div>
                        <div class="col-md-4"><label class="form-label" for="confirm_password">Konfirmasi Kata Sandi</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" <?= $forcePasswordChange ? 'required' : '' ?>><small class="account-password-hint" id="passwordMatch" aria-live="polite"></small></div>
                    </div>
                    <div class="account-security-note"><i class="bi bi-info-circle"></i><span>Kata sandi disimpan dengan hashing aman dan tidak pernah ditampilkan kembali.</span></div>
                </section>
                <div class="account-form-actions"><button class="btn btn-outline-secondary" type="button" data-account-modal-close>Batal</button><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Simpan Perubahan</button></div>
            </form>
        </section>
    </div>
</div>
<script>
    (() => {
        const formToggle = document.getElementById('accountFormToggle'),
            formPanel = document.getElementById('accountFormPanel'),
            dialog = formPanel?.querySelector('[role="dialog"]'),
            toggleIcon = document.getElementById('accountFormToggleIcon'),
            toggleLabel = document.getElementById('accountFormToggleLabel');
        const setModalOpen = open => {
            if (!formPanel) return;
            formPanel.hidden = !open;
            formToggle?.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('account-modal-open', open);
            if (toggleIcon) toggleIcon.className = `bi ${open?'bi-dash-lg':'bi-plus-lg'}`;
            if (toggleLabel) toggleLabel.textContent = open ? 'Tutup pengaturan' : 'Tambah / Ubah Informasi Akun';
            if (open) dialog?.focus();
            else formToggle?.focus();
        };
        if (formPanel && !formPanel.hidden) document.body.classList.add('account-modal-open');
        formToggle?.addEventListener('click', () => setModalOpen(formPanel.hidden));
        formPanel?.querySelectorAll('[data-account-modal-close]').forEach(button => button.addEventListener('click', () => setModalOpen(false)));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && formPanel && !formPanel.hidden) setModalOpen(false);
        });
        const photoInput = document.getElementById('foto');
        const preview = document.getElementById('profilePhotoPreview');
        const fallback = document.getElementById('profilePhotoFallback');
        if (photoInput && preview) photoInput.addEventListener('change', () => {
            const file = photoInput.files?.[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
            fallback?.classList.add('d-none');
        });
        const password = document.getElementById('new_password'),
            confirm = document.getElementById('confirm_password'),
            meter = document.getElementById('passwordMeter'),
            hint = document.getElementById('passwordHint'),
            match = document.getElementById('passwordMatch');
        const refresh = () => {
            if (!password) return;
            const value = password.value;
            const score = [value.length >= 10, /[a-z]/.test(value) && /[A-Z]/.test(value), /\d/.test(value), /[^A-Za-z0-9]/.test(value)].filter(Boolean).length;
            meter?.setAttribute('data-score', String(score));
            hint.textContent = value ? ['Masih lemah', 'Perlu diperkuat', 'Cukup kuat', 'Kuat', 'Sangat kuat'][score] : 'Minimal 10 karakter.';
            match.textContent = confirm.value ? (confirm.value === value ? 'Kata sandi cocok.' : 'Kata sandi belum cocok.') : '';
            match.classList.toggle('is-match', !!confirm.value && confirm.value === value);
        };
        password?.addEventListener('input', refresh);
        confirm?.addEventListener('input', refresh);
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
