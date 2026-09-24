<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

final class StorefrontHomeHighlightsRepository implements StorefrontHomeHighlightsRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function settings(): array
    {
        $settings = $this->database->connection()->query(
            'SELECT featured_product_slug, popular_product_1_slug, popular_product_2_slug '
            . 'FROM storefront_home_highlights WHERE id = 1',
        )->fetch();
        if (!is_array($settings)) {
            throw new RuntimeException('Storefront home highlights are not initialized.');
        }

        return $settings;
    }

    public function updateHighlights(string $featured, ?string $popular1, ?string $popular2): void
    {
        $pdo = $this->database->connection();
        $statement = $pdo->prepare(
            'UPDATE storefront_home_highlights SET featured_product_slug = :featured, '
            . 'popular_product_1_slug = :popular1, popular_product_2_slug = :popular2 WHERE id = 1',
        );
        $statement->execute(['featured' => $featured, 'popular1' => $popular1, 'popular2' => $popular2]);
        if ($statement->rowCount() === 0 && $pdo->query('SELECT 1 FROM storefront_home_highlights WHERE id = 1')->fetchColumn() === false) {
            throw new RuntimeException('Storefront home highlights are not initialized.');
        }
    }
}
