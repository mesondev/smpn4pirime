<?php

declare(strict_types=1);

function public_page_hero(string $eyebrow, string $title, string $intro, string $activePage): void
{
?>
    <section class="page-hero">
        <div class="wrap">
            <span class="eyebrow"><?= e($eyebrow) ?></span>
            <h1><?= e($title) ?></h1>
            <p><?= e($intro) ?></p>
            <?= portal_breadcrumb_markup($activePage, $title) ?>
        </div>
    </section>
<?php
}

function public_empty_panel(string $icon, string $title, string $message): void
{
?>
    <div class="public-empty-state public-empty-state--text" role="status">
        <span class="public-empty-icon"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span>
        <div class="public-empty-copy">
            <strong><?= e($title) ?></strong>
            <p><?= e($message) ?></p>
            <a class="text-link" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>
<?php
}

function public_service_notice(string $message): void
{
?>
    <div class="public-service-notice public-empty-state--text" role="status">
        <span class="public-empty-icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
        <div>
            <strong>Informasi sedang disiapkan</strong>
            <p><?= e($message) ?></p>
        </div>
    </div>
<?php
}

function public_text_cards(array $items, string $emptyTitle, string $emptyMessage, callable $render): void
{
    if (!$items) {
        public_empty_panel('info-circle', $emptyTitle, $emptyMessage);
        return;
    }

    echo '<div class="cards public-feature-grid">';
    foreach ($items as $item) $render($item);
    echo '</div>';
}
