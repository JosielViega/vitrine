<?php

declare(strict_types=1);

/** @var array<string, mixed> $menu */
$formatPrice = static fn (float $price): string => 'R$ ' . number_format($price, 2, ',', '.');
$logoSize = 'small';
$topbarTitle = null;
$topbarBackHref = '/';
$topbarOverlay = false;
$activeNav = 'menu';
?>
<div class="screen menu-screen" id="cardapio" data-page="menu">
    <?php require dirname(__DIR__) . '/components/topbar.php'; ?>
    <div class="menu-logo"><?php require dirname(__DIR__) . '/components/logo.php'; ?></div>
    <div class="menu-tools">
        <label class="search">
            <span class="sr-only">Buscar no cardápio</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
            <input id="menu-search" type="search" placeholder="Buscar no cardápio..." autocomplete="off">
        </label>
        <nav class="category-tabs" aria-label="Categorias do cardápio">
            <?php foreach ($menu['categories'] as $slug => $label): ?><a href="#<?= e($slug) ?>" data-category-link="<?= e($slug) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </nav>
    </div>
    <p class="search-empty" id="search-empty" role="status" hidden>Nenhum item encontrado. Tente outro nome.</p>
    <div class="menu-list">
        <?php foreach ($menu['categories'] as $categorySlug => $categoryLabel): ?>
            <section class="menu-section" id="<?= e($categorySlug) ?>" data-category="<?= e($categorySlug) ?>" aria-labelledby="title-<?= e($categorySlug) ?>">
                <div class="section-heading"><h2 id="title-<?= e($categorySlug) ?>"><?= e($categoryLabel) ?></h2></div>
                <?php foreach ($menu['products'] as $product): ?>
                    <?php if ($product['category'] === $categorySlug): ?><?php require dirname(__DIR__) . '/components/product-list-item.php'; ?><?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?>
    <div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
