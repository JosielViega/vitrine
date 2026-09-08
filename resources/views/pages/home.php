<?php

declare(strict_types=1);

/** @var array{isOpen: bool, categories: array<string, string>, products: array<int, array<string, mixed>>} $menu */
$formatPrice = static fn (float $price): string => 'R$ ' . number_format($price, 2, ',', '.');
?>
<section class="intro">
    <div class="container intro-content">
        <div class="hero-copy">
            <h1 class="sr-only">Bar e Lanchonete São Jorge</h1>
            <p class="status <?= $menu['isOpen'] ? 'is-open' : 'is-closed' ?>">
                <span aria-hidden="true"></span><?= $menu['isOpen'] ? 'Aberto agora' : 'Fechado agora' ?>
            </p>
            <p class="hero-tagline">Boa comida<br><span>reúne boas pessoas!</span></p>
        </div>
        <div class="service-card" aria-label="Informações de atendimento">
            <p>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <strong>Quinta, sexta e sábado</strong><small>Das 17h às 21h30</small>
            </p>
            <p>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <strong>Consumo no local ou retirada</strong><small>Não fazemos entregas.</small>
            </p>
        </div>
        <a class="primary-link" href="#cardapio">Ver cardápio
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </a>
    </div>
</section>

<section class="menu" id="cardapio" aria-labelledby="menu-title">
    <div class="container">
        <div class="menu-heading">
            <h2 class="sr-only" id="menu-title">Nosso cardápio</h2>
            <label class="search">
                <span class="sr-only">Buscar no cardápio</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                <input id="menu-search" type="search" placeholder="Buscar no cardápio..." autocomplete="off">
            </label>
        </div>
        <nav class="category-nav" aria-label="Categorias do cardápio">
            <?php foreach ($menu['categories'] as $slug => $label): ?>
                <a href="#<?= e($slug) ?>" data-category-link="<?= e($slug) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="search-empty" id="search-empty" role="status" hidden>Nenhum item encontrado. Tente buscar por outro nome.</p>
        <?php foreach ($menu['categories'] as $categorySlug => $categoryLabel): ?>
            <section class="menu-section" id="<?= e($categorySlug) ?>" data-category="<?= e($categorySlug) ?>" aria-labelledby="title-<?= e($categorySlug) ?>">
                <div class="section-title">
                    <h3 id="title-<?= e($categorySlug) ?>"><?= e($categoryLabel) ?></h3>
                    <span><?= count(array_filter($menu['products'], static fn (array $product): bool => $product['category'] === $categorySlug)) ?> opções</span>
                </div>
                <div class="product-grid">
                    <?php foreach ($menu['products'] as $product): ?>
                        <?php if ($product['category'] === $categorySlug): ?>
                            <?php require dirname(__DIR__) . '/components/product-card.php'; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<button class="cart-bar" id="cart-bar" type="button" aria-controls="cart-dialog" hidden>
    <span class="cart-count"><strong id="cart-bar-count">0</strong> <span id="cart-bar-label">itens</span></span>
    <span>Ver pedido</span><strong id="cart-bar-total">R$ 0,00</strong>
</button>
<dialog class="cart-dialog" id="cart-dialog" aria-labelledby="cart-title">
    <div class="cart-sheet">
        <header class="cart-header">
            <div><p class="eyebrow">Seu pedido</p><h2 id="cart-title">Revise os itens</h2></div>
            <button class="icon-button" id="cart-close" type="button" aria-label="Fechar pedido">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </header>
        <div class="cart-items" id="cart-items"></div>
        <div class="cart-summary">
            <p><span>Subtotal</span><strong id="cart-subtotal">R$ 0,00</strong></p>
            <small>Pedido para consumo no local ou retirada. Não fazemos entregas.</small>
            <button class="checkout-button" id="checkout-button" type="button">Finalizar pelo WhatsApp</button>
            <p class="checkout-notice" id="checkout-notice" role="status" hidden>Finalização pelo WhatsApp será conectada na próxima etapa.</p>
        </div>
    </div>
</dialog>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
