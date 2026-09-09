<?php

declare(strict_types=1);

/** @var array<string, mixed> $menu */
$formatPrice = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
$logoSize = 'small'; $topbarTitle = null; $topbarBackHref = '/'; $topbarOverlay = false; $activeNav = 'menu';
?>
<div class="screen menu-screen" id="cardapio" data-page="menu">
    <?php require dirname(__DIR__) . '/components/topbar.php'; ?>
    <div class="menu-logo"><?php require dirname(__DIR__) . '/components/logo.php'; ?></div>
    <div class="menu-tools"><label class="search"><span class="sr-only">Buscar no cardápio</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="menu-search" type="search" placeholder="Buscar no cardápio..." autocomplete="off"></label>
        <nav class="category-tabs" aria-label="Categorias do cardápio"><?php foreach ($menu['categories'] as $category): ?><a href="#<?= e($category['slug']) ?>" data-category-link="<?= e($category['slug']) ?>"><?= e($category['name']) ?></a><?php endforeach; ?></nav>
    </div>
    <p class="search-empty" id="search-empty" role="status" hidden>Nenhum item encontrado. Tente outro nome.</p>
    <div class="menu-list">
        <?php foreach ($menu['categories'] as $category): ?>
            <section class="menu-section" id="<?= e($category['slug']) ?>" data-category="<?= e($category['slug']) ?>" aria-labelledby="title-<?= e($category['slug']) ?>"><div class="section-heading"><h2 id="title-<?= e($category['slug']) ?>"><?= e($category['name']) ?></h2></div>
                <?php foreach ($menu['products'] as $product): if ($product['category_id'] === $category['id']): require dirname(__DIR__) . '/components/product-list-item.php'; endif; endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?><div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
