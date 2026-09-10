<?php

declare(strict_types=1);

namespace App\Repositories;

interface StorefrontVisibilityRepositoryInterface
{
    public function categories(): array;

    public function subcategories(): array;

    public function products(): array;

    public function setCategoryVisibility(int $id, bool $visible): bool;

    public function setSubcategoryVisibility(int $id, bool $visible): bool;

    public function setProductVisibility(int $id, bool $visible): bool;
}
