<?php

declare(strict_types=1);

/** @var string $username */
/** @var string $csrfToken */
/** @var string $section */
/** @var array $dashboard */
/** @var array $flashMessages */
/** @var array $imageWarnings */
$tabs = ['categories' => 'Categorias', 'subcategories' => 'Subcategorias', 'products' => 'Produtos', 'images' => 'Imagens'];
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
    <?php elseif ($section === 'products'): ?>
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
    <?php else: ?>
        <section aria-labelledby="images-title">
            <div class="section-heading products-heading"><div><h2 id="images-title">Imagens</h2><p>Uma imagem por produto público, compartilhada entre as variantes.</p></div><label class="product-search"><span class="sr-only">Buscar imagens de produtos</span><input type="search" placeholder="Buscar nome, categoria, variante ou ID" data-product-search></label></div>
            <?php foreach ($imageWarnings as $warning): ?><div class="flash flash-error" role="alert"><?= e($warning) ?></div><?php endforeach; ?>
            <p class="search-empty" data-search-empty hidden>Nenhum produto encontrado.</p>
            <div class="admin-grid image-grid" data-product-list>
                <?php foreach ($dashboard['images'] as $item):
                    $searchText = implode(' ', [$item['name'], $item['category_name'], $item['subcategory_name'], ...$item['variant_names'], ...array_map(static fn (int $id): string => '#' . $id, $item['product_ids'])]);
                    $hasManagedImage = $item['image_source'] === 'managed';
                ?>
                    <article class="admin-card image-card" data-product-card data-search="<?= e($searchText) ?>">
                        <div class="item-context"><?= e($item['category_name']) ?> <span>›</span> <?= e($item['subcategory_name']) ?></div>
                        <div class="item-heading"><div><h3><?= e($item['name']) ?></h3><div class="image-variants"><?= e(implode(' · ', $item['variant_labels'])) ?></div><div class="item-id"><?= e(implode(' · ', array_map(static fn (int $id): string => '#' . $id, $item['product_ids']))) ?></div></div><span class="status <?= $hasManagedImage ? 'status-published' : 'status-inactive' ?>"><?= $hasManagedImage ? 'Cadastrada' : e($item['image_source'] === 'editorial' ? 'Editorial' : 'Padrão') ?></span></div>
                        <figure class="image-preview"><img src="<?= e($item['image']) ?>" alt="" loading="lazy" decoding="async"></figure>
                        <?php if ($hasManagedImage): ?>
                            <p class="image-detail"><strong>Imagem cadastrada</strong><span><?= (int) $item['width'] ?> × <?= (int) $item['height'] ?> · WebP · <?= e(number_format((int) $item['size_bytes'] / 1024, 0, ',', '.')) ?> KB</span></p>
                        <?php else: ?>
                            <p class="image-detail"><strong><?= $item['image_source'] === 'editorial' ? 'Imagem editorial' : 'Imagem padrão' ?></strong><span>Sem imagem cadastrada. A vitrine está usando a imagem atual de configuração.</span></p>
                        <?php endif; ?>
                        <p class="operational-state <?= $item['active'] > 0 ? 'is-active' : 'is-inactive' ?>"><?= (int) $item['active'] ?> de <?= count($item['product_ids']) ?> variante(s) ativa(s) · <?= (int) $item['visible'] ?> visível(is)</p>
                        <form class="image-upload-form" method="post" action="/admin/images/upload" enctype="multipart/form-data">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="group_id" value="<?= e($item['key']) ?>">
                            <label class="file-picker"><span><?= $hasManagedImage ? 'Trocar imagem' : 'Escolher imagem' ?></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-image-file></label>
                            <span class="file-name" data-file-name>Nenhum arquivo escolhido</span>
                            <button class="button button-primary button-wide" type="submit">Enviar imagem</button>
                        </form>
                        <?php if ($hasManagedImage): ?>
                            <form method="post" action="/admin/images/remove" data-image-remove>
                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="group_id" value="<?= e($item['key']) ?>">
                                <button class="button button-secondary" type="submit">Remover imagem</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
