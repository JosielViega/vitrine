<?php

declare(strict_types=1);

/** @var array<string, mixed> $product */
?>
<article class="product-list-item" data-product-row data-search-name="<?= e($product['name'] . ' ' . $product['subcategory']['name']) ?>">
    <a class="product-list-main" href="/produto/<?= e($product['id']) ?>"><img src="<?= e($product['image']) ?>" alt="" width="76" height="76" loading="lazy"><span class="product-list-copy"><strong><?= e($product['name']) ?></strong><?php foreach ($product['variants'] as $variant): ?><span class="product-list-price"><span><?= e($variant['label'] ?: $product['subcategory']['name']) ?></span><b><?= e($formatPrice($variant['price_cents'])) ?></b></span><?php endforeach; ?></span></a>
    <a class="round-add" href="/produto/<?= e($product['id']) ?>" aria-label="Ver <?= e($product['name']) ?> e adicionar ao pedido"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a>
</article>
