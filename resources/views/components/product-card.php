<?php

declare(strict_types=1);

/** @var array<string, mixed> $product */
$hasVariants = count($product['variants']) > 1;
$firstVariant = $product['variants'][0];
?>
<article class="product-card" data-product-card data-product-id="<?= e($product['id']) ?>" data-product-name="<?= e($product['name']) ?>" data-search-name="<?= e($product['name']) ?>">
    <div class="product-visual product-visual-<?= e($product['category']) ?>" aria-hidden="true">
        <?php if ($product['category'] === 'bebidas'): ?>
            <svg viewBox="0 0 48 48"><path d="M14 8h20l-2 32H16L14 8Z"/><path d="M16 14h16M19 4h10M24 4v10"/></svg>
        <?php elseif ($product['category'] === 'acrescimos'): ?>
            <svg viewBox="0 0 48 48"><path d="M9 25h30c0 10-7 15-15 15S9 35 9 25Z"/><path d="M14 20c3-5 6 1 9-5M25 20c3-5 6 1 9-5"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 48 48"><ellipse cx="24" cy="27" rx="18" ry="11"/><path d="M13 25c4-7 18-9 24 0M17 22c0-5 5-9 11-7 4 1 6 4 6 7"/></svg>
        <?php endif; ?>
    </div>
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
    </div>
    <button class="add-button" type="button" data-add-product>
        <span class="add-label">Adicionar</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    </button>
</article>
