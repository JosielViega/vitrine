<?php

declare(strict_types=1);

/** @var string $username */
/** @var string $csrfToken */
/** @var string $section */
/** @var array $dashboard */
/** @var array $flashMessages */
$tabs = ['categories' => 'Categorias', 'subcategories' => 'Subcategorias', 'products' => 'Produtos'];
?>
<header class="admin-header">
    <div class="admin-header-inner">
        <a class="admin-brand" href="/admin" aria-label="Admin da Vitrine">
            <img src="/assets/images/brand/logo-sao-jorge.png" alt="">
            <span><small>São Jorge</small>Admin da Vitrine</span>
        </a>
        <div class="admin-account">
            <span class="account-name">Olá, <?= e($username) ?></span>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="button button-ghost" type="submit">Sair</button>
            </form>
        </div>
    </div>
</header>

<main class="admin-main">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Controle de publicação</span>
            <h1>Visibilidade da vitrine</h1>
            <p>Escolha o que aparece no cardápio público. O estado operacional é apenas informativo.</p>
        </div>
    </div>

    <div id="admin-feedback" aria-live="polite">
        <?php foreach (($flashMessages['success'] ?? []) as $message): ?>
            <div class="flash flash-success"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?php foreach (($flashMessages['error'] ?? []) as $message): ?>
            <div class="flash flash-error" role="alert"><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>

    <nav class="admin-tabs" aria-label="Seções do painel">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="/admin?section=<?= e($key) ?>" class="admin-tab <?= $section === $key ? 'is-active' : '' ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?>
                <span><?= count($dashboard[$key]) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($section === 'categories'): ?>
        <section aria-labelledby="categories-title">
            <div class="section-heading"><h2 id="categories-title">Categorias</h2><p>Todos os registros, inclusive ocultos e inativos.</p></div>
            <div class="admin-grid">
                <?php foreach ($dashboard['categories'] as $item): ?>
                    <article class="admin-card">
                        <div class="item-heading"><div><span class="item-id">#<?= (int) $item['id'] ?></span><h3><?= e($item['name']) ?></h3></div><span class="status status-<?= e($item['effective_tone']) ?>"><?= e($item['effective_status']) ?></span></div>
                        <p class="operational-state <?= (int) $item['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $item['active'] === 1 ? 'Ativo no sistema' : 'Inativo no sistema' ?></p>
                        <?php $itemType = 'category'; require dirname(__DIR__) . '/components/admin-visibility-form.php'; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($section === 'subcategories'): ?>
        <section aria-labelledby="subcategories-title">
            <div class="section-heading"><h2 id="subcategories-title">Subcategorias</h2><p>A visibilidade própria é preservada quando a categoria é ocultada.</p></div>
            <div class="admin-grid">
                <?php foreach ($dashboard['subcategories'] as $item): ?>
                    <article class="admin-card">
                        <div class="item-context"><?= e($item['category_name']) ?></div>
                        <div class="item-heading"><div><span class="item-id">#<?= (int) $item['id'] ?></span><h3><?= e($item['name']) ?></h3></div><span class="status status-<?= e($item['effective_tone']) ?>"><?= e($item['effective_status']) ?></span></div>
                        <p class="operational-state <?= (int) $item['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $item['active'] === 1 ? 'Ativo no sistema' : 'Inativo no sistema' ?></p>
                        <?php $itemType = 'subcategory'; require dirname(__DIR__) . '/components/admin-visibility-form.php'; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <section aria-labelledby="products-title">
            <div class="section-heading products-heading"><div><h2 id="products-title">Produtos</h2><p>Registros individuais, incluindo variantes Inteira e Meia.</p></div><label class="product-search"><span class="sr-only">Buscar produtos</span><input type="search" placeholder="Buscar produto, categoria ou subcategoria" data-product-search></label></div>
            <p class="search-empty" data-search-empty hidden>Nenhum produto encontrado.</p>
            <div class="admin-grid products-grid" data-product-list>
                <?php foreach ($dashboard['products'] as $item): ?>
                    <article class="admin-card product-card" data-product-card data-search="<?= e($item['name'] . ' ' . $item['category_name'] . ' ' . $item['subcategory_name']) ?>">
                        <div class="item-context"><?= e($item['category_name']) ?> <span>›</span> <?= e($item['subcategory_name']) ?></div>
                        <div class="item-heading"><div><span class="item-id">#<?= (int) $item['id'] ?></span><h3><?= e($item['name']) ?></h3></div><span class="status status-<?= e($item['effective_tone']) ?>"><?= e($item['effective_status']) ?></span></div>
                        <div class="product-meta"><strong>R$ <?= e(number_format((int) $item['price_cents'] / 100, 2, ',', '.')) ?></strong><span><?= $item['kind'] === 'kitchen' ? 'Cozinha' : 'Regular' ?></span></div>
                        <p class="operational-state <?= (int) $item['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $item['active'] === 1 ? 'Ativo no sistema' : 'Inativo no sistema' ?></p>
                        <?php $itemType = 'product'; require dirname(__DIR__) . '/components/admin-visibility-form.php'; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
