<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use Throwable;

final class StorefrontProductImageRepository
{
    public function __construct(private readonly Database $database)
    {
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
        $ids = $this->productIds($productIds);

        return $this->transaction(function (PDO $pdo) use ($metadata, $ids): int {
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

            return $imageId;
        });
    }

    public function replaceAssociations(int $imageId, array $productIds): void
    {
        $imageId = $this->positiveId($imageId);
        $ids = $this->productIds($productIds);
        $this->transaction(function (PDO $pdo) use ($imageId, $ids): void {
            $this->associateWithinTransaction($pdo, $imageId, $ids);
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
