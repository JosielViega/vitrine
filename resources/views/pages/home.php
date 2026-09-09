<?php

declare(strict_types=1);

/** @var array<string, mixed> $menu */
/** @var null|array<string, mixed> $featured */
/** @var list<array<string, mixed>> $popular */
$formatPrice = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
$logoSize = 'large'; $topbarTitle = null; $topbarBackHref = '/cardapio'; $topbarOverlay = true; $activeNav = 'home';
?>
<div class="screen home-screen" data-page="home">
    <section class="home-hero">
        <img class="home-hero-photo" src="/assets/images/storefront/hero-shrimp-placeholder.jpg" alt="Porção de camarão com batatas" width="1280" height="853">
        <div class="hero-shade" aria-hidden="true"></div>
        <?php require dirname(__DIR__) . '/components/topbar.php'; ?>
        <div class="home-brand"><?php require dirname(__DIR__) . '/components/logo.php'; ?></div>
        <div class="home-hero-content"><?php require dirname(__DIR__) . '/components/service-info.php'; ?><p class="home-slogan">Boa comida<br><span>reúne boas pessoas!</span></p></div>
    </section>
    <nav class="home-categories" aria-label="Atalhos do cardápio">
        <?php foreach ($menu['categories'] as $category): $slug = $category['slug']; ?>
            <a href="/cardapio#<?= e($slug) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?php if ($slug === 'comidas'): ?><path d="M5 4v7M8 4v7M5 8h3M6.5 11v9M16 4v16M16 4c4 3 4 7 0 10"/><?php elseif ($slug === 'bebidas'): ?><path d="M7 3h10l-1 18H8L7 3ZM8 8h8"/><?php else: ?><path d="M8 3h8l2 18H6L8 3ZM7 9h10M11 3l3 6"/><?php endif; ?></svg>
                <span><?= e($category['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="home-content">
        <?php if ($featured !== null): ?>
            <section aria-labelledby="featured-title"><div class="section-heading"><h2 id="featured-title">Destaque da casa</h2></div>
                <a class="featured-card" href="/produto/<?= e($featured['id']) ?>"><img src="<?= e($featured['image']) ?>" alt="" width="720" height="420"><span class="featured-shade" aria-hidden="true"></span><span class="featured-copy"><strong><?= e($featured['name']) ?></strong><?php if ($featured['description'] !== null): ?><small><?= e($featured['description']) ?></small><?php endif; ?><b><?= e($formatPrice(max(array_column($featured['variants'], 'price_cents')))) ?></b></span><span class="round-add" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span></a>
            </section>
        <?php endif; ?>
        <?php if ($popular !== []): ?>
            <section aria-labelledby="popular-title"><div class="section-heading"><h2 id="popular-title">Mais pedidos</h2></div><div class="popular-grid"><?php foreach ($popular as $product): require dirname(__DIR__) . '/components/product-card.php'; endforeach; ?></div></section>
        <?php endif; ?>
        <a class="drinks-banner" href="/cardapio#bebidas"><img src="/assets/images/storefront/drinks-placeholder.jpg" alt="Bebidas geladas" width="1280" height="720" loading="lazy"><span>Bebidas geladas<small>para bons momentos!</small></span></a>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?><div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
