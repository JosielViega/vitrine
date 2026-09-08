<?php

declare(strict_types=1);

/** @var array<string, mixed> $product */
/** @var list<array<string, mixed>> $related */
$formatPrice = static fn (float $price): string => 'R$ ' . number_format($price, 2, ',', '.');
$topbarTitle = 'Detalhes do Produto';
$topbarBackHref = '/cardapio#' . $product['category'];
$topbarOverlay = false;
$activeNav = 'menu';
?>
<div class="screen product-screen" data-page="product">
    <?php require dirname(__DIR__) . '/components/topbar.php'; ?>
    <img class="product-hero" src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" width="1280" height="853">
    <div class="product-content">
        <h2><?= e($product['name']) ?></h2>
        <p class="product-description"><?= e($product['description'] ?? 'Preparado com todo o cuidado da casa.') ?></p>
        <form class="product-form" data-product-detail data-product-id="<?= e($product['id']) ?>" data-product-name="<?= e($product['name']) ?>" data-product-image="<?= e($product['image']) ?>">
            <fieldset class="detail-variants">
                <legend><?= count($product['variants']) > 1 ? 'Escolha o tamanho' : 'Preço' ?></legend>
                <?php foreach ($product['variants'] as $index => $variant): ?>
                    <label><input type="radio" name="variant" value="<?= e($variant['id']) ?>" data-label="<?= e($variant['label']) ?>" data-price="<?= e((string) $variant['price']) ?>" <?= $index === 0 ? 'checked' : '' ?>><span><?= e($variant['label'] ?: 'Porção') ?></span><strong><?= e($formatPrice($variant['price'])) ?></strong></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="detail-field">
                <span>Quantidade</span>
                <div class="quantity-control"><button type="button" data-quantity-minus aria-label="Diminuir quantidade">−</button><output data-quantity>1</output><button type="button" data-quantity-plus aria-label="Aumentar quantidade">+</button></div>
            </div>
            <label class="notes-field">Observações (opcional)<textarea data-notes rows="3" maxlength="180" placeholder="Ex.: sem salada, ponto da fritura, etc."></textarea></label>
            <button class="primary-button" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L20.5 8H6"/></svg>Adicionar ao pedido</button>
        </form>
        <?php if ($related !== []): ?>
            <section class="related" aria-labelledby="related-title"><div class="section-heading"><h2 id="related-title">Você também pode gostar</h2></div>
                <?php foreach ($related as $item): $relatedProduct = $product; $product = $item; require dirname(__DIR__) . '/components/product-list-item.php'; $product = $relatedProduct; endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php require dirname(__DIR__) . '/components/bottom-nav.php'; ?>
    <div class="toast" data-toast role="status" aria-live="polite"></div>
</div>
