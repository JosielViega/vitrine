<?php

declare(strict_types=1);

/** @var array<string, mixed> $product */
$startingPrice = min(array_column($product['variants'], 'price_cents'));
?>
<a class="popular-card" href="/produto/<?= e($product['id']) ?>"><img src="<?= e($product['image']) ?>" alt="" width="220" height="140" loading="lazy"><span><strong><?= e($product['name']) ?></strong><small>A partir de</small><b><?= e($formatPrice($startingPrice)) ?></b></span><span class="round-add" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span></a>
