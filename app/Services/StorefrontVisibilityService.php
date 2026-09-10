<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontVisibilityRepositoryInterface;

final class StorefrontVisibilityService
{
    public function __construct(private readonly StorefrontVisibilityRepositoryInterface $repository)
    {
    }

    public function dashboard(): array
    {
        return [
            'categories' => array_map($this->category(...), $this->repository->categories()),
            'subcategories' => array_map($this->subcategory(...), $this->repository->subcategories()),
            'products' => array_map($this->product(...), $this->repository->products()),
        ];
    }

    public function setVisibility(string $type, int $id, bool $visible): bool
    {
        return match ($type) {
            'category' => $this->repository->setCategoryVisibility($id, $visible),
            'subcategory' => $this->repository->setSubcategoryVisibility($id, $visible),
            'product' => $this->repository->setProductVisibility($id, $visible),
            default => false,
        };
    }

    private function category(array $item): array
    {
        return [...$item, ...$this->status(
            (int) $item['active'] === 1,
            (int) $item['storefront_visible'] === 1,
        )];
    }

    private function subcategory(array $item): array
    {
        $blockedBy = null;
        if ((int) $item['category_active'] !== 1) {
            $blockedBy = 'Categoria inativa no sistema';
        } elseif ((int) $item['category_storefront_visible'] !== 1) {
            $blockedBy = 'Oculto pela categoria';
        }

        return [...$item, ...$this->status(
            (int) $item['active'] === 1,
            (int) $item['storefront_visible'] === 1,
            $blockedBy,
        )];
    }

    private function product(array $item): array
    {
        $blockedBy = null;
        if ((int) $item['category_active'] !== 1) {
            $blockedBy = 'Categoria inativa no sistema';
        } elseif ((int) $item['category_storefront_visible'] !== 1) {
            $blockedBy = 'Oculto pela categoria';
        } elseif ((int) $item['subcategory_active'] !== 1) {
            $blockedBy = 'Subcategoria inativa no sistema';
        } elseif ((int) $item['subcategory_storefront_visible'] !== 1) {
            $blockedBy = 'Oculto pela subcategoria';
        }

        return [...$item, ...$this->status(
            (int) $item['active'] === 1,
            (int) $item['storefront_visible'] === 1,
            $blockedBy,
        )];
    }

    private function status(bool $active, bool $visible, ?string $blockedBy = null): array
    {
        if (!$active) {
            return ['effective_status' => 'Inativo no sistema', 'effective_tone' => 'inactive'];
        }
        if ($blockedBy !== null) {
            return ['effective_status' => $blockedBy, 'effective_tone' => 'blocked'];
        }
        if (!$visible) {
            return ['effective_status' => 'Oculto', 'effective_tone' => 'hidden'];
        }

        return ['effective_status' => 'Publicado', 'effective_tone' => 'published'];
    }
}
