<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$flashMessage = take_flash();
$mustChangePassword = !empty($_SESSION['password_must_change']);
$navigationGroups = $mustChangePassword
    ? ['Keamanan Akun' => [['label' => 'Ganti Kata Sandi', 'icon' => 'bi-key', 'href' => 'profile.php']]]
    : require __DIR__ . '/navigation.php';
$activeModule = (string)($_GET['module'] ?? '');
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
$displayPreferences = is_logged_in() ? user_display_preferences($pdo) : normalize_user_display_preferences([]);
$dashboardView = (string)($_GET['view'] ?? 'dashboard');
$isDashboardHome = ($currentPage === 'index.php' && $activeModule === '' && in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true))
    || ($currentPage === 'teacher.php' && current_role() === 'guru' && $dashboardView === 'dashboard');
$unreadNotifications = 0;
if (is_logged_in() && !$mustChangePassword && can_view_module('notifikasi')) {
    try {
        $hasRecipient = db_has_column($pdo, 'notifikasi', 'recipient_user_id');
        $hasArchive = db_has_column($pdo, 'notifikasi', 'archived_at');
        $canSeeLegacy = in_array(current_role(), ['admin', 'operator'], true);
        $scopeSql = $hasRecipient ? '(recipient_user_id=?' . ($canSeeLegacy ? ' OR recipient_user_id IS NULL' : '') . ')' : ($canSeeLegacy ? '1=1' : '1=0');
        $params = $hasRecipient ? [(int)($_SESSION['user_id'] ?? 0)] : [];
        $archiveSql = $hasArchive ? ' AND archived_at IS NULL' : '';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE dibaca=0' . $archiveSql . ' AND ' . $scopeSql);
        $stmt->execute($params);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $unreadNotifications = 0;
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="user-theme" content="<?= e($displayPreferences['tema']) ?>">
    <meta name="user-text-size" content="<?= e($displayPreferences['ukuran_teks']) ?>">
    <meta name="user-table-density" content="<?= e($displayPreferences['kerapatan_tabel']) ?>">
    <title><?= e($pageTitle) ?> | SIMS SMP Negeri 4 Pirime</title>
    <link rel="icon" type="image/png" href="assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png?v=<?= (int)filemtime(__DIR__ . '/../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/style.css'), 0, 12)) ?>" rel="stylesheet">
    <?php if ($currentPage === 'settings.php'): ?>
        <link href="assets/settings.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/settings.css'), 0, 12)) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if ($currentPage === 'index.php' && $activeModule === 'ppdb_admin'): ?>
        <link href="assets/ppdb-admin.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/ppdb-admin.css'), 0, 12)) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if ($currentPage === 'index.php' && $activeModule === 'portal_admin'): ?>
        <link href="assets/portal-admin.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/portal-admin.css'), 0, 12)) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if ($currentPage === 'student.php'): ?>
        <link href="assets/student-dashboard.css?v=<?= (int)filemtime(__DIR__ . '/../assets/student-dashboard.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (($currentPage === 'teacher.php' && current_role() === 'guru') || ($currentPage === 'index.php' && in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true) && ($activeModule === '' || current_role() === 'kepala_sekolah'))): ?>
        <link href="assets/role-dashboards.css?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/role-dashboards.css'), 0, 12)) ?>" rel="stylesheet"><?php endif; ?>
    <?php if (in_array($currentPage, ['profile.php', 'biodata.php'], true)): ?>
        <link href="assets/account-biodata.css?v=<?= (int)filemtime(__DIR__ . '/../assets/account-biodata.css') ?>" rel="stylesheet"><?php endif; ?>
</head>

<body<?= $isDashboardHome ? ' class="dashboard-navy-theme"' : '' ?> data-user-id="<?= (int) ($_SESSION['user_id'] ?? 0) ?>" data-preference-url="<?= e(app_url('settings.php?ajax=preferensi')) ?>">
    <div class="app-layout" data-sidebar-collapsed="<?= $displayPreferences['sidebar_ciut'] ? '1' : '0' ?>">
        <aside class="sidebar no-print" id="appSidebar">
            <a class="sidebar-brand" href="index.php"><img class="brand-logo" src="assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang SMP Negeri 4 Pirime"><span><strong>SIMS SMPN 4</strong><small>Pirime · Papua Pegunungan</small></span></a>
            <div class="sidebar-school">SISTEM INFORMASI SEKOLAH</div>
            <nav class="sidebar-nav" aria-label="Navigasi utama">
                <?php foreach ($navigationGroups as $group => $items): ?>
                    <?php $visibleItems = array_filter($items, static fn($item) => empty($item['module']) || can_view_module($item['module']));
                    if (!$visibleItems) continue; ?>
                    <div class="nav-group-label"><?= e($group) ?></div>
                    <?php foreach ($visibleItems as $item): $href = $item['href'] ?? ('index.php?module=' . urlencode($item['module']));
                        $active = isset($item['report']) ? ($currentPage === 'reports.php' && (string)($_GET['type'] ?? '') === $item['report']) : (isset($item['activeView']) ? ($currentPage === basename($item['href']) && (string)($_GET['view'] ?? 'dashboard') === $item['activeView']) : (isset($item['module']) ? ($activeModule === $item['module'] || (isset($item['href']) && $currentPage === basename($item['href']))) : $currentPage === basename($item['href']))); ?>
                        <a class="sidebar-link<?= $active ? ' active' : '' ?>" href="<?= e($href) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="sidebar-user-icon"><i class="bi bi-person-fill"></i></div>
                <div class="sidebar-user-text"><strong><?= e(current_user()) ?></strong><small><?= e(role_label()) ?></small></div>
                <form action="logout.php" method="post" class="sidebar-logout-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="sidebar-logout" title="Keluar" aria-label="Keluar"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button></form>
            </div>
        </aside>
        <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Tutup menu" tabindex="-1" hidden></button>
        <div class="main-area">
            <header class="topbar no-print"><button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Buka atau tutup menu" aria-controls="appSidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
                <div class="topbar-title"><span class="topbar-eyebrow">SMP NEGERI 4 PIRIME</span><strong><?= e($pageTitle) ?></strong></div>
                <?php if (!$mustChangePassword && !in_array(current_role(), ['guru', 'siswa'], true)): ?><form class="topbar-search" action="search.php" method="get"><i class="bi bi-search"></i><input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari siswa, guru, kelas..." aria-label="Pencarian sekolah"></form><?php endif; ?>
                <div class="topbar-actions"><span class="topbar-date"><i class="bi bi-calendar3"></i> <?= e(date('d M Y')) ?></span><button class="topbar-avatar theme-toggle" type="button" id="themeToggle" aria-label="Aktifkan tema gelap" title="Aktifkan tema gelap"><i class="bi bi-moon-stars"></i></button><?php if (!$mustChangePassword && can_view_module('notifikasi')): ?><a class="topbar-avatar topbar-bell" href="index.php?module=notifikasi" title="Notifikasi" aria-label="Notifikasi"><i class="bi bi-bell" aria-hidden="true"></i><span class="notification-count" data-notification-count<?= $unreadNotifications ? '' : ' hidden' ?>><?= $unreadNotifications ? min($unreadNotifications, 99) : '' ?></span></a><?php endif; ?><?php if (!empty($_SESSION['profile_photo'])): ?><a href="profile.php" title="Profil akun"><img class="topbar-avatar" style="object-fit:cover;padding:0" src="media/profile.php?id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" alt="Pasfoto <?= e(current_user()) ?>"></a><?php else: ?><a class="topbar-avatar" href="profile.php" title="Profil akun"><?= e(strtoupper(substr(current_user(), 0, 1))) ?></a><?php endif; ?>
                    <div class="topbar-profile"><strong><?= e(current_user()) ?></strong><small><?= e(role_label()) ?></small></div>
                </div>
            </header>
            <main class="container-fluid page-content<?= current_role() === 'kepala_sekolah' && $currentPage === 'index.php' ? ' principal-dashboard-content' : (current_role() === 'guru' && $currentPage === 'teacher.php' ? ' teacher-dashboard-content' : (current_role() === 'admin' && $currentPage === 'index.php' && $activeModule === '' ? ' admin-dashboard-content' : (current_role() === 'operator' && $currentPage === 'index.php' && $activeModule === '' ? ' operator-dashboard-content' : ''))) ?>">
                <?php if (!$mustChangePassword && can_view_module('notifikasi')): ?><div class="notification-live-region" id="notificationLiveRegion" aria-live="polite" aria-atomic="false"></div><?php endif; ?>
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show no-print" role="alert">
                        <?= e($flashMessage['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
