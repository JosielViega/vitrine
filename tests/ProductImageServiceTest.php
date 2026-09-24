<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontProductImageRepositoryInterface;
use App\Services\ProductImageException;
use App\Services\ProductImageProcessorInterface;
use App\Services\ProductImageService;
use App\Services\ProductImageStorageInterface;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontProductGroupingService;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductImageServiceTest extends TestCase
{
    private string $root;
    private ProductImageRepositoryFake $repository;
    private ProductImageStorageFake $storage;
    private ProductImageProcessorFake $processor;
    private ProductImageService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/vitrine-image-service-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0775, true);
        $this->repository = new ProductImageRepositoryFake([$this->row(79, 'Camarão'), $this->row(82, 'Meia: Camarão')]);
        $this->storage = new ProductImageStorageFake($this->root);
        $this->processor = new ProductImageProcessorFake();
        $this->service = $this->createService();
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->root);
    }

    public function testNewUploadCreatesOneFileOneMetadataAndTwoAssociations(): void
    {
        $this->service->upload('product-79', ['error' => UPLOAD_ERR_OK]);

        self::assertCount(1, $this->repository->images);
        self::assertSame($this->repository->links[79], $this->repository->links[82]);
        self::assertCount(1, glob($this->root . '/*.webp'));
        self::assertSame('image/webp', array_values($this->repository->images)[0]['mime_type']);
    }

    public function testReplacementCommitsNewImageBeforeDeletingUnlinkedOldImage(): void
    {
        $oldPath = $this->seedOldImage([79, 82]);

        $this->service->upload('product-79', ['error' => UPLOAD_ERR_OK]);

        self::assertArrayNotHasKey(10, $this->repository->images);
        self::assertFileDoesNotExist($this->storage->physicalPath($oldPath));
        self::assertSame($this->repository->links[79], $this->repository->links[82]);
        self::assertLessThan(array_search('delete:10', $this->repository->events, true), array_search('commit:replace', $this->repository->events, true));
    }

    public function testProcessingFailureDoesNotTouchDatabaseOrLeaveFile(): void
    {
        $this->processor->fail = true;
        try {
            $this->service->upload('product-79', ['error' => UPLOAD_ERR_OK]);
            self::fail('Expected processing failure.');
        } catch (ProductImageException) {
            self::assertSame([], $this->repository->links);
            self::assertSame([], glob($this->root . '/*'));
        }
    }

    public function testDatabaseFailureRemovesNewFileAndPreservesOldImage(): void
    {
        $oldPath = $this->seedOldImage([79, 82]);
        $this->repository->failReplace = true;

        try {
            $this->service->upload('product-79', ['error' => UPLOAD_ERR_OK]);
            self::fail('Expected database failure.');
        } catch (ProductImageException) {
            self::assertSame(10, $this->repository->links[79]);
            self::assertSame(10, $this->repository->links[82]);
            self::assertFileExists($this->storage->physicalPath($oldPath));
            self::assertCount(1, glob($this->root . '/*.webp'));
        }
    }

    public function testSharedOldImageIsNotDeletedWhileAnotherProductUsesIt(): void
    {
        $oldPath = $this->seedOldImage([79, 82, 99]);

        $this->service->upload('product-79', ['error' => UPLOAD_ERR_OK]);

        self::assertArrayHasKey(10, $this->repository->images);
        self::assertSame(10, $this->repository->links[99]);
        self::assertFileExists($this->storage->physicalPath($oldPath));
    }

    public function testRemoveClearsWholeGroupAndDeletesUnlinkedImage(): void
    {
        $oldPath = $this->seedOldImage([79, 82]);

        $this->service->remove('product-79');

        self::assertSame([], $this->repository->links);
        self::assertSame([], $this->repository->images);
        self::assertFileDoesNotExist($this->storage->physicalPath($oldPath));
    }

    public function testGroupWithVisibleWholeAndHiddenHalfRemainsAvailableWithEveryId(): void
    {
        $this->repository->rows[1]['storefront_visible'] = 0;
        $groups = $this->createService()->groups();

        self::assertCount(1, $groups);
        self::assertSame([79, 82], $groups[0]['product_ids']);
        self::assertSame(1, $groups[0]['visible']);
    }

    public function testGroupWithHiddenWholeAndVisibleHalfRemainsAvailableWithEveryId(): void
    {
        $this->repository->rows[0]['storefront_visible'] = 0;
        $groups = $this->createService()->groups();

        self::assertCount(1, $groups);
        self::assertSame([79, 82], $groups[0]['product_ids']);
        self::assertSame(1, $groups[0]['visible']);
    }

    public function testFullyHiddenGroupIsOmittedAndCannotBeManipulated(): void
    {
        $this->repository->rows[0]['storefront_visible'] = 0;
        $this->repository->rows[1]['storefront_visible'] = 0;
        $service = $this->createService();

        self::assertSame([], $service->groups());
        try {
            $service->upload('product-79', ['error' => UPLOAD_ERR_OK]);
            self::fail('Expected ineligible upload to be rejected.');
        } catch (ProductImageException $exception) {
            self::assertSame('Produto não encontrado.', $exception->getMessage());
        }
        $this->expectException(ProductImageException::class);
        $service->remove('product-79');
    }

    public function testParentVisibilityAndActivityControlEligibility(): void
    {
        foreach (['category_storefront_visible', 'subcategory_storefront_visible', 'category_active', 'subcategory_active'] as $field) {
            foreach ($this->repository->rows as &$row) $row[$field] = 0;
            unset($row);
            self::assertSame([], $this->createService()->groups(), $field);
            foreach ($this->repository->rows as &$row) $row[$field] = 1;
            unset($row);
        }
        $this->repository->rows[0]['active'] = 0;
        $this->repository->rows[1]['active'] = 0;
        self::assertSame([], $this->createService()->groups());
    }

    public function testConfiguredAddonProductsAreNeverImageGroups(): void
    {
        $this->repository->rows = [$this->row(117, 'Bacon'), $this->row(74, 'Mussarela')];

        self::assertSame([], $this->createService()->groups());
    }

    public function testHidingAndReactivatingGroupPreservesExistingImage(): void
    {
        $oldPath = $this->seedOldImage([79, 82]);
        foreach ($this->repository->rows as &$row) $row['storefront_visible'] = 0;
        unset($row);

        self::assertSame([], $this->createService()->groups());
        self::assertSame(10, $this->repository->links[79]);
        self::assertFileExists($this->storage->physicalPath($oldPath));

        $this->repository->rows[0]['storefront_visible'] = 1;
        $groups = $this->createService()->groups();
        self::assertCount(1, $groups);
        self::assertSame('managed', $groups[0]['image_source']);
        self::assertSame(10, $groups[0]['image_id']);
    }

    public function testUnknownGroupIsRejectedWithoutDatabaseMutation(): void
    {
        $this->expectException(ProductImageException::class);
        $this->expectExceptionMessage('Produto não encontrado.');
        $this->service->upload('product-999', ['error' => UPLOAD_ERR_OK]);
    }

    private function seedOldImage(array $productIds): string
    {
        $path = '/uploads/products/2026/09/' . str_repeat('a', 32) . '.webp';
        $this->repository->images[10] = ['id' => 10, 'path' => $path, 'mime_type' => 'image/webp', 'width' => 800, 'height' => 600, 'size_bytes' => 3];
        foreach ($productIds as $id) $this->repository->links[$id] = 10;
        file_put_contents($this->storage->physicalPath($path), 'old');
        return $path;
    }

    private function createService(): ProductImageService
    {
        $presentation = require dirname(__DIR__) . '/config/storefront.php';
        $grouping = new StorefrontProductGroupingService((array) ($presentation['variant_aliases'] ?? []));
        $catalog = new StorefrontCatalogService(new ProductImageCatalogRepositoryFake($this->repository), $presentation, $grouping);

        return new ProductImageService($this->repository, $grouping, $this->processor, $this->storage, $presentation, $catalog);
    }

    private function row(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'price_cents' => 5000, 'kind' => 'kitchen', 'active' => 1, 'storefront_visible' => 1, 'subcategory_id' => 1, 'subcategory_name' => 'Porções', 'subcategory_active' => 1, 'subcategory_storefront_visible' => 1, 'category_id' => 1, 'category_name' => 'Comidas', 'category_active' => 1, 'category_storefront_visible' => 1];
    }
}

final class ProductImageCatalogRepositoryFake implements StorefrontCatalogRepository
{
    public function __construct(private readonly ProductImageRepositoryFake $source) {}

    public function activeCategories(): array
    {
        $categories = [];
        foreach ($this->source->rows as $row) {
            if ((int) $row['category_active'] !== 1 || (int) $row['category_storefront_visible'] !== 1) continue;
            $categories[(int) $row['category_id']] = ['id' => (int) $row['category_id'], 'name' => $row['category_name'], 'sort_order' => 0, 'active' => 1];
        }
        return array_values($categories);
    }

    public function activeSubcategories(): array
    {
        $subcategories = [];
        foreach ($this->source->rows as $row) {
            if ((int) $row['category_active'] !== 1 || (int) $row['category_storefront_visible'] !== 1 || (int) $row['subcategory_active'] !== 1 || (int) $row['subcategory_storefront_visible'] !== 1) continue;
            $subcategories[(int) $row['subcategory_id']] = ['id' => (int) $row['subcategory_id'], 'category_id' => (int) $row['category_id'], 'name' => $row['subcategory_name'], 'sort_order' => 0, 'active' => 1];
        }
        return array_values($subcategories);
    }

    public function activeProducts(): array
    {
        return array_values(array_filter($this->source->administrativeProducts(), static fn (array $row): bool =>
            (int) $row['active'] === 1
            && (int) $row['storefront_visible'] === 1
            && (int) $row['subcategory_active'] === 1
            && (int) $row['subcategory_storefront_visible'] === 1
            && (int) $row['category_active'] === 1
            && (int) $row['category_storefront_visible'] === 1
        ));
    }
}

final class ProductImageProcessorFake implements ProductImageProcessorInterface
{
    public bool $fail = false;
    public function process(array $upload, string $destination): array
    {
        if ($this->fail) throw new ProductImageException('Não foi possível processar a imagem.');
        file_put_contents($destination, 'new-webp');
        return ['mime_type' => 'image/webp', 'width' => 1200, 'height' => 800, 'size_bytes' => 8];
    }
}

final class ProductImageStorageFake implements ProductImageStorageInterface
{
    private int $counter = 0;
    public function __construct(private readonly string $root) {}
    public function generatePublicPath(string $extension, ?DateTimeInterface $date = null): string { return '/uploads/products/2026/09/' . str_pad((string) ++$this->counter, 32, '0', STR_PAD_LEFT) . '.webp'; }
    public function ensureDirectory(string $publicPath): string { return $this->root; }
    public function physicalPath(string $publicPath): string { return $this->root . '/' . basename($publicPath); }
    public function remove(string $publicPath): bool { $path = $this->physicalPath($publicPath); return !is_file($path) || unlink($path); }
}

final class ProductImageRepositoryFake implements StorefrontProductImageRepositoryInterface
{
    public array $images = [];
    public array $links = [];
    public array $events = [];
    public bool $failReplace = false;
    private int $nextId = 11;
    public function __construct(public array $rows) {}
    public function administrativeProducts(): array
    {
        return array_map(function (array $row): array {
            $image = $this->images[$this->links[$row['id']] ?? 0] ?? null;
            return [...$row, 'storefront_image_id' => $image['id'] ?? null, 'storefront_image_path' => $image['path'] ?? null, 'storefront_image_mime_type' => $image['mime_type'] ?? null, 'storefront_image_width' => $image['width'] ?? null, 'storefront_image_height' => $image['height'] ?? null, 'storefront_image_size_bytes' => $image['size_bytes'] ?? null];
        }, $this->rows);
    }
    public function findById(int $imageId): ?array { return $this->images[$imageId] ?? null; }
    public function productIdsForImage(int $imageId): array { return array_map('intval', array_keys(array_filter($this->links, static fn (int $id): bool => $id === $imageId))); }
    public function replaceGroupImage(array $metadata, array $productIds): array
    {
        if ($this->failReplace) throw new RuntimeException('database failed');
        $previous = $this->previous($productIds);
        $id = $this->nextId++;
        $this->images[$id] = ['id' => $id, ...$metadata];
        foreach ($productIds as $productId) $this->links[$productId] = $id;
        $this->events[] = 'commit:replace';
        return ['image_id' => $id, 'previous_images' => $previous];
    }
    public function removeGroupAssociations(array $productIds): array
    {
        $previous = $this->previous($productIds);
        foreach ($productIds as $productId) unset($this->links[$productId]);
        $this->events[] = 'commit:remove';
        return $previous;
    }
    public function deleteImageIfUnlinked(int $imageId): bool
    {
        if ($this->productIdsForImage($imageId) !== []) return false;
        unset($this->images[$imageId]);
        $this->events[] = 'delete:' . $imageId;
        return true;
    }
    private function previous(array $productIds): array
    {
        $ids = array_unique(array_intersect_key($this->links, array_flip($productIds)));
        return array_values(array_intersect_key($this->images, array_flip($ids)));
    }
}
