<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$flashMessage = take_flash();
$navigationGroups = require __DIR__ . '/navigation.php';
$activeModule = (string)($_GET['module'] ?? '');
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
$unreadNotifications = 0;
if (is_logged_in() && can_view_module('notifikasi')) {
    try {
        $unreadNotifications = (int)$pdo->query('SELECT COUNT(*) FROM notifikasi WHERE dibaca=0')->fetchColumn();
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
    <title><?= e($pageTitle) ?> | SIMS SMP Negeri 4 Pirime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar no-print" id="appSidebar">
            <a class="sidebar-brand" href="index.php"><img class="brand-logo" src="assets/logo-sims-pirime.png" alt="Logo SMP Negeri 4 Pirime"><span><strong>SIMS SMPN 4</strong><small>Pirime · Papua Pegunungan</small></span></a>
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
                <div class="sidebar-user-text"><strong><?= e(current_user()) ?></strong><small><?= e(role_label()) ?></small></div><a href="logout.php" class="sidebar-logout" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </aside>
        <div class="main-area">
            <header class="topbar no-print"><button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Buka atau tutup menu"><i class="bi bi-list"></i></button>
                <div class="topbar-title"><span class="topbar-eyebrow">SMP NEGERI 4 PIRIME</span><strong><?= e($pageTitle) ?></strong></div>
                <?php if (!in_array(current_role(), ['guru','siswa'], true)): ?><form class="topbar-search" action="search.php" method="get"><i class="bi bi-search"></i><input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari siswa, guru, kelas..." aria-label="Pencarian sekolah"></form><?php endif; ?>
                <div class="topbar-actions"><span class="topbar-date"><i class="bi bi-calendar3"></i> <?= e(date('d M Y')) ?></span><button class="topbar-avatar theme-toggle" type="button" id="themeToggle" aria-label="Aktifkan tema gelap" title="Aktifkan tema gelap"><i class="bi bi-moon-stars"></i></button><?php if (can_view_module('notifikasi')): ?><a class="topbar-avatar topbar-bell" href="index.php?module=notifikasi" title="Notifikasi"><i class="bi bi-bell"></i><span class="notification-count" data-notification-count<?= $unreadNotifications ? '' : ' hidden' ?>><?= $unreadNotifications ? min($unreadNotifications, 99) : '' ?></span></a><?php endif; ?><?php if(!empty($_SESSION['profile_photo'])): ?><a href="profile.php" title="Profil akun"><img class="topbar-avatar" style="object-fit:cover;padding:0" src="media/profile.php?id=<?= (int)($_SESSION['user_id']??0) ?>" alt="Pasfoto <?= e(current_user()) ?>"></a><?php else: ?><a class="topbar-avatar" href="profile.php" title="Profil akun"><?= e(strtoupper(substr(current_user(), 0, 1))) ?></a><?php endif; ?>
                    <div class="topbar-profile"><strong><?= e(current_user()) ?></strong><small><?= e(role_label()) ?></small></div>
                </div>
            </header>
            <main class="container-fluid page-content">
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show no-print" role="alert">
                        <?= e($flashMessage['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
