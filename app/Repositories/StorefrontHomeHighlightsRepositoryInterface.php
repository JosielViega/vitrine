<?php

declare(strict_types=1);

namespace App\Repositories;

interface StorefrontHomeHighlightsRepositoryInterface
{
    /** @return array{featured_product_slug: string, popular_product_1_slug: ?string, popular_product_2_slug: ?string} */
    public function settings(): array;

    public function updateHighlights(string $featured, ?string $popular1, ?string $popular2): void;
}
