<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class StorefrontVisibilityRepository implements StorefrontVisibilityRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function categories(): array
    {
        return $this->database->connection()->query(
            'SELECT id, name, active, storefront_visible FROM categories ORDER BY sort_order, name',
        )->fetchAll();
    }

    public function subcategories(): array
    {
        return $this->database->connection()->query(
            'SELECT s.id, s.category_id, c.name AS category_name, s.name, s.active, s.storefront_visible, '
            . 'c.active AS category_active, c.storefront_visible AS category_storefront_visible '
            . 'FROM subcategories s INNER JOIN categories c ON c.id = s.category_id '
            . 'ORDER BY c.sort_order, c.name, s.sort_order, s.name',
        )->fetchAll();
    }

    public function products(): array
    {
        return $this->database->connection()->query(
            'SELECT p.id, p.name, p.price_cents, p.kind, p.active, p.storefront_visible, '
            . 'p.subcategory_id, s.name AS subcategory_name, s.active AS subcategory_active, '
            . 's.storefront_visible AS subcategory_storefront_visible, c.id AS category_id, '
            . 'c.name AS category_name, c.active AS category_active, '
            . 'c.storefront_visible AS category_storefront_visible '
            . 'FROM products p INNER JOIN subcategories s ON s.id = p.subcategory_id '
            . 'INNER JOIN categories c ON c.id = s.category_id '
            . 'ORDER BY c.sort_order, c.name, s.sort_order, s.name, p.name',
        )->fetchAll();
    }

    public function setCategoryVisibility(int $id, bool $visible): bool
    {
        return $this->updateVisibility(
            'UPDATE categories SET storefront_visible = :visible WHERE id = :id',
            'SELECT 1 FROM categories WHERE id = :id',
            $id,
            $visible,
        );
    }

    public function setSubcategoryVisibility(int $id, bool $visible): bool
    {
        return $this->updateVisibility(
            'UPDATE subcategories SET storefront_visible = :visible WHERE id = :id',
            'SELECT 1 FROM subcategories WHERE id = :id',
            $id,
            $visible,
        );
    }

    public function setProductVisibility(int $id, bool $visible): bool
    {
        return $this->updateVisibility(
            'UPDATE products SET storefront_visible = :visible WHERE id = :id',
            'SELECT 1 FROM products WHERE id = :id',
            $id,
            $visible,
        );
    }

    private function updateVisibility(string $updateSql, string $existsSql, int $id, bool $visible): bool
    {
        $pdo = $this->database->connection();
        $exists = $pdo->prepare($existsSql);
        $exists->execute(['id' => $id]);
        if ($exists->fetchColumn() === false) {
            return false;
        }

        $update = $pdo->prepare($updateSql);
        $update->execute(['visible' => $visible ? 1 : 0, 'id' => $id]);

        return true;
    }
}
