<?php

declare(strict_types=1);

/** @var string $username */
/** @var string $csrfToken */
/** @var string $section */
/** @var array $dashboard */
/** @var array $flashMessages */
/** @var array $imageWarnings */
$tabs = ['categories' => 'Categorias', 'subcategories' => 'Subcategorias', 'products' => 'Produtos', 'images' => 'Imagens', 'home' => 'Home', 'operations' => 'Funcionamento'];
$isOperations = $section === 'operations';
$isHome = $section === 'home';
$imageFilters = [];
if ($section === 'images') {
    $nameSubcategoryIds = [];
    foreach ($dashboard['images'] as $imageGroup) {
        $nameKey = strtolower(trim((string) $imageGroup['subcategory_name']));
        $nameSubcategoryIds[$nameKey][(int) $imageGroup['subcategory_id']] = true;
    }
    foreach ($dashboard['images'] as $imageGroup) {
        $filterId = (string) $imageGroup['subcategory_id'];
        if (isset($imageFilters[$filterId])) {
            continue;
        }
        $name = (string) $imageGroup['subcategory_name'];
        $label = count($nameSubcategoryIds[strtolower(trim($name))] ?? []) > 1
            ? $imageGroup['category_name'] . ' › ' . $name
            : $name;
        $imageFilters[$filterId] = $label;
    }
}
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
            <span class="eyebrow"><?= $isOperations ? 'Operação' : ($isHome ? 'Vitrine' : 'Controle de publicação') ?></span>
            <h1><?= $isOperations ? 'Funcionamento da vitrine' : ($isHome ? 'Destaques da Home' : 'Visibilidade da vitrine') ?></h1>
            <p><?= $isOperations ? 'Gerencie comunicados bloqueantes e o calendário semanal.' : ($isHome ? 'Escolha os produtos que aparecem em destaque na página inicial.' : 'Escolha o que aparece no cardápio público. O estado operacional é apenas informativo.') ?></p>
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
                <?php if (!in_array($key, ['home', 'operations'], true)): ?><span><?= count($dashboard[$key]) ?></span><?php endif; ?>
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
    <?php elseif ($section === 'images'): ?>
        <section aria-labelledby="images-title">
            <div class="section-heading products-heading"><div><h2 id="images-title">Imagens</h2><p>Uma imagem por produto público, compartilhada entre as variantes.</p></div><label class="product-search"><span class="sr-only">Buscar imagens de produtos</span><input type="search" placeholder="Buscar nome, categoria, variante ou ID" data-product-search></label></div>
            <?php foreach ($imageWarnings as $warning): ?><div class="flash flash-error" role="alert"><?= e($warning) ?></div><?php endforeach; ?>
            <?php if ($imageFilters !== []): ?>
                <nav class="image-filters" aria-label="Filtrar imagens por subcategoria" data-image-filters>
                    <button class="image-filter is-active" type="button" data-image-filter="all" aria-pressed="true">Todas</button>
                    <?php foreach ($imageFilters as $filterId => $filterLabel): ?>
                        <button class="image-filter" type="button" data-image-filter="<?= e($filterId) ?>" aria-pressed="false"><?= e($filterLabel) ?></button>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <p class="search-empty" data-search-empty hidden>Nenhum produto encontrado.</p>
            <div class="admin-grid image-grid" data-product-list>
                <?php foreach ($dashboard['images'] as $item):
                    $searchText = implode(' ', [$item['name'], $item['category_name'], $item['subcategory_name'], ...$item['variant_names'], ...array_map(static fn (int $id): string => '#' . $id, $item['product_ids'])]);
                    $hasManagedImage = $item['image_source'] === 'managed';
                ?>
                    <article class="admin-card image-card" data-product-card data-search="<?= e($searchText) ?>" data-subcategory-id="<?= (int) $item['subcategory_id'] ?>" data-category-id="<?= (int) $item['category_id'] ?>">
                        <div class="item-context"><?= e($item['category_name']) ?> <span>›</span> <?= e($item['subcategory_name']) ?></div>
                        <?php $imageStatus = $hasManagedImage ? 'Cadastrada' : ($item['image_source'] === 'editorial' ? 'Editorial' : 'Padrão'); $imageStatusClass = $hasManagedImage ? 'status-published' : 'status-inactive'; ?>
                        <div class="item-heading"><div><h3><?= e($item['name']) ?></h3><div class="image-variants"><?= e(implode(' · ', $item['variant_labels'])) ?></div><div class="item-id"><?= e(implode(' · ', array_map(static fn (int $id): string => '#' . $id, $item['product_ids']))) ?></div></div><span class="status <?= e($imageStatusClass) ?>" data-image-status data-original-label="<?= e($imageStatus) ?>" data-original-class="status <?= e($imageStatusClass) ?>"><?= e($imageStatus) ?></span></div>
                        <figure class="image-preview"><img src="<?= e($item['image']) ?>" data-image-preview data-original-src="<?= e($item['image']) ?>" alt="" loading="lazy" decoding="async"><span class="image-unsaved" data-image-unsaved hidden>Não salvo</span></figure>
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
    <?php elseif ($section === 'home'):
        $homeDashboard = $dashboard['home'];
        $homeSettings = $homeDashboard['settings'];
        $homeSlots = [
            ['field' => 'featured_product_slug', 'title' => 'Destaque da casa', 'required' => true, 'selected' => (string) $homeSettings['featured_product_slug'], 'product' => $homeDashboard['featured']],
            ['field' => 'popular_product_1_slug', 'title' => 'Mais pedidos — posição 1', 'required' => false, 'selected' => (string) ($homeSettings['popular_product_1_slug'] ?? ''), 'product' => $homeDashboard['popular1']],
            ['field' => 'popular_product_2_slug', 'title' => 'Mais pedidos — posição 2', 'required' => false, 'selected' => (string) ($homeSettings['popular_product_2_slug'] ?? ''), 'product' => $homeDashboard['popular2']],
        ];
    ?>
        <section aria-labelledby="home-highlights-title">
            <div class="section-heading"><div><h2 id="home-highlights-title">Produtos em destaque</h2><p>As opções incluem somente produtos disponíveis atualmente na vitrine.</p></div></div>
            <?php foreach ($homeDashboard['warnings'] as $warning): ?><div class="flash flash-error" role="alert"><?= e($warning) ?></div><?php endforeach; ?>
            <form class="home-highlights-form" method="post" action="/admin/home/highlights" data-home-highlights-form>
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="home-highlights-grid">
                    <?php foreach ($homeSlots as $slot): $selectedProduct = $slot['product']; ?>
                        <article class="admin-card home-highlight-card" data-home-highlight-slot>
                            <div class="item-heading"><div><span class="eyebrow">Posição na Home</span><h3><?= e($slot['title']) ?></h3></div><span class="status status-unsaved" data-highlight-unsaved hidden>Não salvo</span></div>
                            <label class="highlight-select-label"><span>Produto</span>
                                <select name="<?= e($slot['field']) ?>" <?= $slot['required'] ? 'required' : '' ?> data-highlight-select data-original-value="<?= e($slot['selected']) ?>">
                                    <?php if (!$slot['required']): ?><option value="">Nenhum</option><?php else: ?><option value="" disabled>Escolha um produto</option><?php endif; ?>
                                    <?php foreach ($homeDashboard['groups'] as $groupLabel => $products): ?>
                                        <optgroup label="<?= e($groupLabel) ?>">
                                            <?php foreach ($products as $option): ?>
                                                <option value="<?= e($option['slug']) ?>" data-image="<?= e($option['image']) ?>" data-name="<?= e($option['name']) ?>" data-context="<?= e($option['context']) ?>" data-price="<?= e($option['price']) ?>" <?= $slot['selected'] === $option['slug'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="highlight-preview" data-highlight-preview <?= $selectedProduct === null ? 'hidden' : '' ?>>
                                <img src="<?= e($selectedProduct['image'] ?? '') ?>" alt="" width="180" height="120" data-highlight-image>
                                <div><strong data-highlight-name><?= e($selectedProduct['name'] ?? '') ?></strong><span data-highlight-context><?= $selectedProduct === null ? '' : e($selectedProduct['category_name'] . ' › ' . $selectedProduct['subcategory']['name']) ?></span><b data-highlight-price><?= $selectedProduct === null ? '' : e('R$ ' . number_format(min(array_column($selectedProduct['variants'], 'price_cents')) / 100, 2, ',', '.')) ?></b></div>
                            </div>
                            <p class="highlight-empty" data-highlight-empty <?= $selectedProduct !== null ? 'hidden' : '' ?>>Nenhum produto selecionado.</p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button class="button button-primary" type="submit">Salvar destaques</button>
            </form>
        </section>
    <?php else:
        $operations = $dashboard['operations'];
        $notice = $operations['notice'];
        $businessStatus = $operations['business_status'];
    ?>
        <div class="operations-stack">
            <section class="operations-card storefront-state <?= $notice['enabled'] ? 'is-blocked' : 'is-available' ?>" aria-labelledby="storefront-state-title">
                <span class="eyebrow" id="storefront-state-title">Estado da vitrine</span>
                <h2><?= $notice['enabled'] ? 'Vitrine bloqueada por aviso' : 'Vitrine liberada' ?></h2>
                <?php if ($notice['enabled']): ?>
                    <blockquote>“<?= e($notice['title']) ?>”</blockquote>
                    <p class="notice-preview-message"><?= e($notice['message']) ?></p>
                <?php else: ?>
                    <strong class="business-now"><?= e($businessStatus['status_label']) ?></strong>
                    <p><?= e($businessStatus['message']) ?></p>
                <?php endif; ?>
            </section>

            <section class="operations-card" aria-labelledby="notice-settings-title">
                <div class="section-heading">
                    <div><span class="eyebrow">Quadro de aviso</span><h2 id="notice-settings-title">Comunicado público bloqueante</h2></div>
                </div>
                <form class="operations-form" method="post" action="/admin/operations/notice">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <label class="switch-row operations-switch">
                        <span><strong>Ativar quadro de aviso</strong><small>Enquanto ativo, substitui integralmente as páginas públicas.</small></span>
                        <span class="switch"><input type="checkbox" name="notice_enabled" value="1" <?= $notice['enabled'] ? 'checked' : '' ?>><span class="switch-track"></span></span>
                    </label>
                    <label><span>Título</span><input type="text" name="notice_title" maxlength="120" value="<?= e($notice['title']) ?>"></label>
                    <label><span>Mensagem</span><textarea name="notice_message" maxlength="1500" rows="6"><?= e($notice['message']) ?></textarea></label>
                    <div class="notice-preview">
                        <span class="eyebrow">Prévia segura</span>
                        <strong><?= e($notice['title'] !== '' ? $notice['title'] : 'Título do aviso') ?></strong>
                        <p><?= e($notice['message'] !== '' ? $notice['message'] : 'Mensagem do aviso') ?></p>
                    </div>
                    <button class="button button-primary" type="submit">Salvar aviso</button>
                </form>
            </section>

            <section class="operations-card" aria-labelledby="hours-settings-title">
                <div class="section-heading">
                    <div><span class="eyebrow">Calendário semanal</span><h2 id="hours-settings-title">Horário de funcionamento</h2><p>Timezone: America/Sao_Paulo</p></div>
                </div>
                <form class="operations-form" method="post" action="/admin/operations/hours">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="hours-grid">
                        <?php foreach ($operations['schedule'] as $day): ?>
                            <fieldset class="hours-day" data-hours-day>
                                <legend><?= e($day['label']) ?></legend>
                                <label class="switch-row">
                                    <span>Aberto</span>
                                    <span class="switch"><input type="checkbox" name="day_<?= (int) $day['weekday'] ?>_enabled" value="1" <?= $day['enabled'] ? 'checked' : '' ?> data-hours-toggle><span class="switch-track"></span></span>
                                </label>
                                <div class="hours-fields">
                                    <label><span>Abertura</span><input type="time" name="day_<?= (int) $day['weekday'] ?>_open" value="<?= e($day['open_time']) ?>" <?= $day['enabled'] ? '' : 'disabled' ?> data-hours-input></label>
                                    <label><span>Fechamento</span><input type="time" name="day_<?= (int) $day['weekday'] ?>_close" value="<?= e($day['close_time']) ?>" <?= $day['enabled'] ? '' : 'disabled' ?> data-hours-input></label>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                    <button class="button button-primary" type="submit">Salvar horário</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</main>
