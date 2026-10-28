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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="display-6">🏫</div>
                        <h1 class="h4 mt-2">SIMS SMPN 4 Pirime</h1>
                        <p class="text-secondary">Silakan masuk untuk mengelola data sekolah</p>
                    </div>
                    <?php if (!empty($_GET['inactive'])): ?><div class="alert alert-warning">Akun ini tidak aktif atau aksesnya telah dicabut. Hubungi pengelola sekolah.</div><?php endif; ?>
                    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required autofocus></div>
                        <div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
                        <button class="btn btn-primary w-100">Masuk</button>
                    </form>
                    <p class="small text-secondary text-center mt-4">Gunakan akun yang diberikan oleh pengelola sekolah.</p>
                    <p class="text-center mb-0"><a href="public/index.php">Kunjungi portal publik sekolah</a></p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
