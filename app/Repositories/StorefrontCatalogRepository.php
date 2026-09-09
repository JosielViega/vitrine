<?php

declare(strict_types=1);

namespace App\Repositories;

interface StorefrontCatalogRepository
{
    /** @return list<array<string, mixed>> */
    public function activeCategories(): array;

    /** @return list<array<string, mixed>> */
    public function activeSubcategories(): array;

    /** @return list<array<string, mixed>> */
    public function activeProducts(): array;
}
