<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class StorefrontProductRepository implements StorefrontCatalogRepository
{
    public const ACTIVE_CATEGORIES_SQL = <<<'SQL'
SELECT id, name, sort_order, active
FROM categories
WHERE active = 1 AND storefront_visible = 1
ORDER BY sort_order, name
SQL;

    public const ACTIVE_SUBCATEGORIES_SQL = <<<'SQL'
SELECT s.id, s.category_id, s.name, s.sort_order, s.active
FROM subcategories s
INNER JOIN categories c ON c.id = s.category_id
WHERE s.active = 1 AND s.storefront_visible = 1
  AND c.active = 1 AND c.storefront_visible = 1
ORDER BY c.sort_order, s.sort_order, s.name
SQL;

    public const ACTIVE_PRODUCTS_SQL = <<<'SQL'
SELECT p.id, p.subcategory_id, s.name AS subcategory_name, s.sort_order AS subcategory_sort_order,
       c.id AS category_id, c.name AS category_name, c.sort_order AS category_sort_order,
       p.name, p.price_cents, p.kind, p.active
FROM products p
INNER JOIN subcategories s ON s.id = p.subcategory_id
INNER JOIN categories c ON c.id = s.category_id
WHERE p.active = 1 AND p.storefront_visible = 1
  AND s.active = 1 AND s.storefront_visible = 1
  AND c.active = 1 AND c.storefront_visible = 1
ORDER BY c.sort_order, s.sort_order, p.name
SQL;

    public function __construct(private readonly Database $database)
    {
    }

    public function activeCategories(): array
    {
        return $this->database->connection()->query(self::ACTIVE_CATEGORIES_SQL)->fetchAll();
    }

    public function activeSubcategories(): array
    {
        return $this->database->connection()->query(self::ACTIVE_SUBCATEGORIES_SQL)->fetchAll();
    }

    public function activeProducts(): array
    {
        return $this->database->connection()->query(self::ACTIVE_PRODUCTS_SQL)->fetchAll();
    }
}
