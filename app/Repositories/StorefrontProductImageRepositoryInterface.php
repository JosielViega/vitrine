<?php

declare(strict_types=1);

namespace App\Repositories;

interface StorefrontProductImageRepositoryInterface
{
    public function administrativeProducts(): array;
    public function findById(int $imageId): ?array;
    public function productIdsForImage(int $imageId): array;
    public function replaceGroupImage(array $metadata, array $productIds): array;
    public function removeGroupAssociations(array $productIds): array;
    public function deleteImageIfUnlinked(int $imageId): bool;
}
