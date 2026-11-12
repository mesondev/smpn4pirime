<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect('index.php');
$pageTitle = 'Login';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SIMS SMP Negeri 4 Pirime</title>
    <link rel="icon" type="image/png" href="assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png?v=<?= (int)filemtime(__DIR__ . '/assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body class="login-page">
    <main class="login-stage">
        <div class="login-stage__content">
        <section class="login-panel" aria-labelledby="loginTitle">
            <div class="login-brand">
                <img src="assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang SMP Negeri 4 Pirime" class="login-logo">
                <h1 id="loginTitle">SIMS SMPN 4 PIRIME</h1>
                <p>Sistem Informasi Manajemen Sekolah</p>
                <span class="login-brand-accent" aria-hidden="true"></span>
            </div>
            <?php if (!empty($_GET['inactive'])): ?>
                <div class="alert alert-warning" role="alert">Akun ini tidak aktif atau aksesnya telah dicabut. Hubungi pengelola sekolah.</div>
                
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="login-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3"><label class="form-label" for="loginUsername">Username</label>
                    <div class="login-input-wrap"><i class="bi bi-person" aria-hidden="true"></i>
                        <input class="form-control" id="loginUsername" name="username" placeholder="Masukkan username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                    </div>
                    <!-- <div class="form-text">Untuk siswa, username adalah NIS. Gunakan kata sandi awal dari Super Admin; NIS bukan kata sandi. Saat pertama masuk, Anda diminta membuat kata sandi baru. Jika lupa, minta Super Admin meresetnya.</div> -->
                </div>
                <div class="mb-4">
                    <label class="form-label" for="loginPassword">Password</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input
                            class="form-control"
                            id="loginPassword"
                            type="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required>
                        <button
                            class="login-password-toggle"
                            type="button" id="toggleLoginPassword"
                            aria-label="Tampilkan password"
                            aria-pressed="false">
                            <i class="bi bi-eye"
                                aria-hidden="true">
                            </i>
                        </button>
                    </div>
                </div>
                <button class="login-submit" type="submit">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Masuk</button>
            </form>
            <!-- <div class="login-help"><i class="bi bi-info-circle" aria-hidden="true"></i><p>Gunakan akun yang diberikan oleh pengelola sekolah. Pada login pertama atau setelah reset, Anda harus mengganti kata sandi sebelum dashboard dapat dibuka. Jika lupa kata sandi atau akun terkunci, hubungi Super Admin sekolah.</p></div> -->
            <div class="login-divider"><span>atau</span></div>
            <a class="login-back-link" href="public/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i>Kembali ke portal publik sekolah</a>
        </section>
        <footer class="login-footer" aria-label="Informasi hak cipta">
            <div class="login-footer__credit">
                <p class="login-footer__message">Melayani dengan integritas, disiplin, dan semangat belajar.</p>
            </div>
            <p class="login-footer__legal">© <?= date('Y') ?> SMP Negeri 4 Pirime. Hak cipta dilindungi.</p>
        </footer>
        </div>
    </main>
    <script>
        document.getElementById('toggleLoginPassword')?.addEventListener('click', function() {
            const input = document.getElementById('loginPassword');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.setAttribute('aria-pressed', show ? 'true' : 'false');
            this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            this.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
        });
    </script>
</body>

</html>
