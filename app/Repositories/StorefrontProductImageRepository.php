<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use Throwable;

final class StorefrontProductImageRepository implements StorefrontProductImageRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function administrativeProducts(): array
    {
        return $this->database->connection()->query(
            'SELECT p.id, p.name, p.price_cents, p.kind, p.active, p.storefront_visible, '
            . 'p.subcategory_id, s.name AS subcategory_name, s.active AS subcategory_active, '
            . 's.storefront_visible AS subcategory_storefront_visible, c.id AS category_id, '
            . 'c.name AS category_name, c.active AS category_active, c.storefront_visible AS category_storefront_visible, '
            . 'i.id AS storefront_image_id, i.path AS storefront_image_path, i.mime_type AS storefront_image_mime_type, '
            . 'i.width AS storefront_image_width, i.height AS storefront_image_height, i.size_bytes AS storefront_image_size_bytes '
            . 'FROM products p INNER JOIN subcategories s ON s.id = p.subcategory_id '
            . 'INNER JOIN categories c ON c.id = s.category_id '
            . 'LEFT JOIN storefront_product_image_products ip ON ip.product_id = p.id '
            . 'LEFT JOIN storefront_product_images i ON i.id = ip.image_id '
            . 'ORDER BY c.sort_order, c.name, s.sort_order, s.name, p.name',
        )->fetchAll();
    }

    public function findByProductId(int $productId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT i.id, i.path, i.mime_type, i.width, i.height, i.size_bytes, i.created_at, i.updated_at '
            . 'FROM storefront_product_image_products l '
            . 'INNER JOIN storefront_product_images i ON i.id = l.image_id WHERE l.product_id = :product_id',
        );
        $statement->execute(['product_id' => $this->positiveId($productId)]);
        $image = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($image) ? $image : null;
    }

    public function findById(int $imageId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, path, mime_type, width, height, size_bytes, created_at, updated_at '
            . 'FROM storefront_product_images WHERE id = :image_id',
        );
        $statement->execute(['image_id' => $this->positiveId($imageId)]);
        $image = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($image) ? $image : null;
    }

    public function productIdsForImage(int $imageId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT product_id FROM storefront_product_image_products WHERE image_id = :image_id ORDER BY product_id',
        );
        $statement->execute(['image_id' => $this->positiveId($imageId)]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function createAndAssociate(array $metadata, array $productIds): int
    {
        return $this->replaceGroupImage($metadata, $productIds)['image_id'];
    }

    public function replaceAssociations(int $imageId, array $productIds): void
    {
        $imageId = $this->positiveId($imageId);
        $ids = $this->productIds($productIds);
        $this->transaction(function (PDO $pdo) use ($imageId, $ids): void {
            $this->associateWithinTransaction($pdo, $imageId, $ids);
        });
    }

    public function replaceGroupImage(array $metadata, array $productIds): array
    {
        $ids = $this->productIds($productIds);

        return $this->transaction(function (PDO $pdo) use ($metadata, $ids): array {
            $previous = $this->imagesForProducts($pdo, $ids, true);
            $statement = $pdo->prepare(
                'INSERT INTO storefront_product_images (path, mime_type, width, height, size_bytes) '
                . 'VALUES (:path, :mime_type, :width, :height, :size_bytes)',
            );
            $statement->execute([
                'path' => (string) $metadata['path'],
                'mime_type' => (string) $metadata['mime_type'],
                'width' => $this->positiveId((int) $metadata['width']),
                'height' => $this->positiveId((int) $metadata['height']),
                'size_bytes' => $this->positiveId((int) $metadata['size_bytes']),
            ]);
            $imageId = (int) $pdo->lastInsertId();
            $this->associateWithinTransaction($pdo, $imageId, $ids);

            return ['image_id' => $imageId, 'previous_images' => $previous];
        });
    }

    public function removeGroupAssociations(array $productIds): array
    {
        $ids = $this->productIds($productIds);

        return $this->transaction(function (PDO $pdo) use ($ids): array {
            $previous = $this->imagesForProducts($pdo, $ids, true);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare('DELETE FROM storefront_product_image_products WHERE product_id IN (' . $placeholders . ')');
            $statement->execute($ids);

            return $previous;
        });
    }

    public function removeAssociation(int $productId): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM storefront_product_image_products WHERE product_id = :product_id',
        );
        $statement->execute(['product_id' => $this->positiveId($productId)]);
    }

    public function deleteImageIfUnlinked(int $imageId): bool
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM storefront_product_images WHERE id = :image_id '
            . 'AND NOT EXISTS (SELECT 1 FROM storefront_product_image_products WHERE image_id = :linked_image_id)',
        );
        $statement->execute(['image_id' => $this->positiveId($imageId), 'linked_image_id' => $imageId]);

        return $statement->rowCount() === 1;
    }

    private function associateWithinTransaction(PDO $pdo, int $imageId, array $productIds): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO storefront_product_image_products (product_id, image_id) VALUES (:product_id, :image_id) '
            . 'ON DUPLICATE KEY UPDATE image_id = VALUES(image_id)',
        );
        foreach ($productIds as $productId) {
            $statement->execute(['product_id' => $productId, 'image_id' => $imageId]);
        }
    }

    private function imagesForProducts(PDO $pdo, array $productIds, bool $lock): array
    {
        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $statement = $pdo->prepare(
            'SELECT DISTINCT i.id, i.path, i.mime_type, i.width, i.height, i.size_bytes '
            . 'FROM storefront_product_image_products ip '
            . 'INNER JOIN storefront_product_images i ON i.id = ip.image_id '
            . 'WHERE ip.product_id IN (' . $placeholders . ')' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute($productIds);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function transaction(callable $operation): mixed
    {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function productIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_map(fn (mixed $id): int => $this->positiveId((int) $id), $productIds)));
        if ($ids === []) {
            throw new InvalidArgumentException('At least one product ID is required.');
        }
        return $ids;
    }

    private function positiveId(int $id): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('A positive integer is required.');
        }
        return $id;
    }
}
