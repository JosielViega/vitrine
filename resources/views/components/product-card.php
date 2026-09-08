<?php

declare(strict_types=1);

/** @var array<string, mixed> $product */
$hasVariants = count($product['variants']) > 1;
$firstVariant = $product['variants'][0];
?>
<article class="product-card" data-product-card data-product-id="<?= e($product['id']) ?>" data-product-name="<?= e($product['name']) ?>" data-search-name="<?= e($product['name']) ?>">
    <div class="product-accent" aria-hidden="true"><span></span></div>
    <div class="product-body">
        <div class="product-info">
            <h4><?= e($product['name']) ?></h4>
            <?php if (isset($product['description'])): ?><p><?= e($product['description']) ?></p><?php endif; ?>
        </div>
        <?php if ($hasVariants): ?>
            <fieldset class="variant-options">
                <legend>Escolha uma opção</legend>
                <?php foreach ($product['variants'] as $index => $variant): ?>
                    <label>
                        <input type="radio" name="variant-<?= e($product['id']) ?>" value="<?= e($variant['id']) ?>" data-variant-label="<?= e($variant['label']) ?>" data-price="<?= e((string) $variant['price']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                        <span><?= e($variant['label']) ?></span><strong><?= e($formatPrice($variant['price'])) ?></strong>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php else: ?>
            <p class="single-price"><?= e($formatPrice($firstVariant['price'])) ?></p>
            <input type="hidden" data-single-variant value="<?= e($firstVariant['id']) ?>" data-price="<?= e((string) $firstVariant['price']) ?>" data-variant-label="">
        <?php endif; ?>
        <button class="add-button" type="button" data-add-product>Adicionar <span aria-hidden="true">+</span></button>
    </div>
</article>
