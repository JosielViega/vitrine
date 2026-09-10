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
        <?php foreach ($menu['categories'] as $category):
            $categoryProducts = array_values(array_filter($menu['products'], static fn (array $product): bool => $product['category_id'] === $category['id']));
            if ($categoryProducts === []) { continue; }
        ?>
            <section class="menu-section" id="<?= e($category['slug']) ?>" data-category="<?= e($category['slug']) ?>" aria-labelledby="title-<?= e($category['slug']) ?>">
                <div class="section-heading"><h2 id="title-<?= e($category['slug']) ?>"><?= e($category['name']) ?></h2></div>
                <?php foreach ($menu['subcategories'] as $subcategory):
                    if ($subcategory['category_id'] !== $category['id']) { continue; }
                    $subcategoryProducts = array_values(array_filter($categoryProducts, static fn (array $product): bool => $product['subcategory_id'] === $subcategory['id']));
                    if ($subcategoryProducts === []) { continue; }
                ?>
                    <section class="menu-subcategory" data-subcategory aria-labelledby="subcategory-<?= e((string) $subcategory['id']) ?>">
                        <h3 id="subcategory-<?= e((string) $subcategory['id']) ?>"><?= e($subcategory['name']) ?></h3>
                        <div class="subcategory-products">
                            <?php foreach ($subcategoryProducts as $product): require dirname(__DIR__) . '/components/product-list-item.php'; endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?><div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
