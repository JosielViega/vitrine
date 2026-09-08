<?php

declare(strict_types=1);

/** @var string|null $topbarTitle */
/** @var string $topbarBackHref */
/** @var bool $topbarOverlay */
?>
<header class="topbar <?= $topbarOverlay ? 'topbar-overlay' : '' ?>">
    <a class="topbar-action" href="<?= e($topbarBackHref) ?>" aria-label="<?= $topbarTitle === null ? 'Abrir cardápio' : 'Voltar' ?>">
        <?php if ($topbarTitle === null): ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        <?php endif; ?>
    </a>
    <?php if ($topbarTitle !== null): ?><h1><?= e($topbarTitle) ?></h1><?php endif; ?>
    <a class="topbar-action cart-shortcut" href="/pedido" aria-label="Abrir meu pedido">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L20.5 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <span class="nav-badge" data-cart-count hidden>0</span>
    </a>
</header>
