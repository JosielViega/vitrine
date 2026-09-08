<?php

declare(strict_types=1);

/** @var string $activeNav */
?>
<nav class="bottom-nav" aria-label="Navegação principal">
    <a href="/" class="<?= $activeNav === 'home' ? 'is-active' : '' ?>" <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9H6v-9"/><path d="M10 20v-6h4v6"/></svg><span>Início</span>
    </a>
    <a href="/cardapio" class="<?= $activeNav === 'menu' ? 'is-active' : '' ?>" <?= $activeNav === 'menu' ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/></svg><span>Cardápio</span>
    </a>
    <a href="/pedido" class="<?= $activeNav === 'order' ? 'is-active' : '' ?>" <?= $activeNav === 'order' ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L20.5 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg><span class="nav-badge" data-cart-count hidden>0</span></span><span>Pedido</span>
    </a>
</nav>
