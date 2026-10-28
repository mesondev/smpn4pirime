<?php

declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > time()) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . (int)ceil(($lockedUntil - time()) / 60) . ' menit.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT id, username, password_hash, nama_lengkap, role, guru_id, siswa_id, foto_path, password_must_change FROM users WHERE username = ? AND aktif = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['linked_guru_id'] = $user['guru_id'] ? (int)$user['guru_id'] : null;
            $_SESSION['linked_siswa_id'] = $user['siswa_id'] ? (int)$user['siswa_id'] : null;
            $_SESSION['profile_photo'] = $user['foto_path'] ?: null;
            $_SESSION['password_must_change'] = (int)$user['password_must_change'];
            $loginUpdate = $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
            $loginUpdate->execute([(int)$user['id']]);
            record_activity($pdo, 'login', 'users', (int)$user['id']);
            if ((int)$user['password_must_change'] === 1) redirect('profile.php?force_password=1');
            redirect('index.php');
        }

        $failures = (int)($_SESSION['login_failures'] ?? 0) + 1;
        if ($failures >= 5) {
            $_SESSION['login_failures'] = 0;
            $_SESSION['login_locked_until'] = time() + 300;
            $error = 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.';
        } else {
            $_SESSION['login_failures'] = $failures;
            $error = 'Username atau password tidak sesuai.';
        }
    }
}
