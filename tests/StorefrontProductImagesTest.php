<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontProductRepository;
use App\Services\ProductImageStorage;
use App\Services\StorefrontCatalogService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontProductImagesTest extends TestCase
{
    public function testManagedImageTakesPrecedenceOverEditorialAndFallback(): void
    {
        $product = $this->service(
            [$this->product(79, 'Camarão', '/uploads/products/2026/09/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp')],
            ['products' => ['camarao' => ['image' => '/assets/editorial.webp']]],
        )->findBySlug('camarao');

        self::assertSame('/uploads/products/2026/09/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp', $product['image']);
    }

    public function testProductWithoutManagedImagePreservesEditorialAndFallbackChain(): void
    {
        $editorial = $this->service(
            [$this->product(1, 'Editorial')],
            ['products' => ['editorial' => ['image' => '/assets/editorial.webp']]],
        )->findBySlug('editorial');
        $categoryFallback = $this->service(
            [$this->product(2, 'Categoria')],
            ['fallback_images' => ['comidas' => '/assets/category.webp']],
        )->findBySlug('categoria');
        $generalFallback = $this->service([$this->product(3, 'Geral')])->findBySlug('geral');

        self::assertSame('/assets/editorial.webp', $editorial['image']);
        self::assertSame('/assets/category.webp', $categoryFallback['image']);
        self::assertSame('/assets/general.webp', $generalFallback['image']);
    }

    public function testWholeAndHalfShareOnePublicImage(): void
    {
        $path = '/uploads/products/2026/09/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.webp';
        $catalog = $this->service([
            $this->product(79, 'Camarão c/ Batata e Aipim', $path),
            $this->product(82, 'Meia: Camarão c/ Batata e Aipim', $path),
        ])->catalog();

        self::assertCount(1, $catalog['products']);
        self::assertSame([79, 82], array_column($catalog['products'][0]['variants'], 'product_id'));
        self::assertSame($path, $catalog['products'][0]['image']);
    }

    public function testIsolatedHalfAndIsolatedWholeResolveTheirManagedImages(): void
    {
        $halfPath = '/uploads/products/2026/09/cccccccccccccccccccccccccccccccc.webp';
        $wholePath = '/uploads/products/2026/09/dddddddddddddddddddddddddddddddd.webp';

        self::assertSame($halfPath, $this->service([
            $this->product(82, 'Meia: Camarão', $halfPath),
        ])->findBySlug('camarao')['image']);
        self::assertSame($wholePath, $this->service([
            $this->product(79, 'Camarão', $wholePath),
        ])->findBySlug('camarao')['image']);
    }

    public function testInconsistentVariantImagesPreferWholeThenHalf(): void
    {
        $whole = '/uploads/products/2026/09/eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee.webp';
        $half = '/uploads/products/2026/09/ffffffffffffffffffffffffffffffff.webp';

        $inconsistent = $this->service([
            $this->product(79, 'Camarão', $whole),
            $this->product(82, 'Meia: Camarão', $half),
        ])->findBySlug('camarao');
        $halfOnlyImage = $this->service([
            $this->product(79, 'Camarão'),
            $this->product(82, 'Meia: Camarão', $half),
        ])->findBySlug('camarao');

        self::assertSame($whole, $inconsistent['image']);
        self::assertSame($half, $halfOnlyImage['image']);
    }

    public function testRepositoryUsesOptionalImageJoinsByRealProductId(): void
    {
        $sql = StorefrontProductRepository::ACTIVE_PRODUCTS_SQL;

        self::assertStringContainsString('LEFT JOIN storefront_product_image_products ip ON ip.product_id = p.id', $sql);
        self::assertStringContainsString('LEFT JOIN storefront_product_images i ON i.id = ip.image_id', $sql);
        self::assertStringContainsString('i.path AS storefront_image_path', $sql);
    }

    public function testSchemaHasOneImagePerProductAndNoOperationalForeignKey(): void
    {
        $sql = file_get_contents(dirname(__DIR__) . '/database/patches/002_add_storefront_product_images.sql');

        self::assertIsString($sql);
        self::assertStringContainsString('PRIMARY KEY (product_id)', $sql);
        self::assertStringContainsString('KEY idx_storefront_product_image_products_image_id (image_id)', $sql);
        self::assertStringContainsString('FOREIGN KEY (image_id) REFERENCES storefront_product_images (id)', $sql);
        self::assertDoesNotMatchRegularExpression('/FOREIGN KEY\s*\(product_id\)/i', $sql);
        self::assertDoesNotMatchRegularExpression('/\b(position|sort_order|is_primary|caption|gallery|multiple_images)\b/i', $sql);
        self::assertDoesNotMatchRegularExpression('/ALTER\s+TABLE\s+(products|categories|subcategories)/i', $sql);
    }

    public function testSchemaApplierIsDedicatedIdempotentAndHasNoMigrationsTable(): void
    {
        $script = file_get_contents(dirname(__DIR__) . '/bin/apply-storefront-images-schema.php');
        $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsString($script);
        self::assertStringContainsString('information_schema.TABLES', $script);
        self::assertStringContainsString('already exists', $script);
        self::assertStringNotContainsString('CREATE TABLE migrations', $script);
        self::assertSame('@php bin/apply-storefront-images-schema.php', $composer['scripts']['storefront:images-schema']);
    }

    public function testImageRepositoryPreparesAtomicCreateReplaceAndRemoval(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/app/Repositories/StorefrontProductImageRepository.php');

        self::assertIsString($source);
        self::assertStringContainsString('beginTransaction()', $source);
        self::assertStringContainsString('INSERT INTO storefront_product_images', $source);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE image_id = VALUES(image_id)', $source);
        self::assertStringContainsString('DELETE FROM storefront_product_image_products WHERE product_id', $source);
        self::assertStringContainsString('DELETE FROM storefront_product_images WHERE id', $source);
    }
    public function testStorageGeneratesControlledRandomWebPathAndPhysicalPath(): void
    {
        $publicRoot = sys_get_temp_dir() . '/vitrine-images-' . bin2hex(random_bytes(5)) . '/public';
        $storage = new ProductImageStorage($publicRoot, static fn (int $length): string => str_repeat("\xAB", $length));
        $path = $storage->generatePublicPath('.WEBP', new DateTimeImmutable('2026-09-11'));

        self::assertSame('/uploads/products/2026/09/' . str_repeat('ab', 16) . '.webp', $path);
        self::assertSame(
            $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR
                . '2026' . DIRECTORY_SEPARATOR . '09' . DIRECTORY_SEPARATOR . str_repeat('ab', 16) . '.webp',
            $storage->physicalPath($path),
        );
        $directory = $storage->ensureDirectory($path);
        self::assertDirectoryExists($directory);
        file_put_contents($storage->physicalPath($path), 'image-bytes');
        self::assertTrue($storage->remove($path));
        self::assertFileDoesNotExist($storage->physicalPath($path));
        rmdir($directory);
        rmdir(dirname($directory));
        rmdir(dirname(dirname($directory)));
        rmdir(dirname(dirname(dirname($directory))));
        rmdir(dirname(dirname(dirname(dirname($directory)))));
        rmdir(dirname(dirname(dirname(dirname(dirname($directory))))));
    }

    #[DataProvider('invalidPathProvider')]
    public function testStorageRejectsTraversalAndArbitraryPaths(string $path): void
    {
        $storage = new ProductImageStorage(sys_get_temp_dir() . '/vitrine-public');

        $this->expectException(InvalidArgumentException::class);
        $storage->physicalPath($path);
    }

    public static function invalidPathProvider(): array
    {
        return [
            'parent traversal' => ['/uploads/products/2026/09/../secret.webp'],
            'Windows traversal' => ['/uploads/products/2026/09/..\\secret.webp'],
            'outside uploads' => ['/assets/images/products/photo.webp'],
            'executable extension' => ['/uploads/products/2026/09/' . str_repeat('a', 32) . '.php'],
            'invalid month' => ['/uploads/products/2026/13/' . str_repeat('a', 32) . '.webp'],
            'predictable name' => ['/uploads/products/2026/09/product-name.webp'],
        ];
    }

    private function service(array $products, array $presentation = []): StorefrontCatalogService
    {
        $repository = new class($products) implements StorefrontCatalogRepository {
            public function __construct(private readonly array $products) {}
            public function activeCategories(): array { return [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]]; }
            public function activeSubcategories(): array { return [['id' => 1, 'category_id' => 1, 'name' => 'Geral', 'sort_order' => 0, 'active' => 1]]; }
            public function activeProducts(): array { return $this->products; }
        };

        return new StorefrontCatalogService($repository, $presentation + ['fallback_image' => '/assets/general.webp']);
    }

    private function product(int $id, string $name, ?string $image = null): array
    {
        return [
            'id' => $id,
            'subcategory_id' => 1,
            'category_id' => 1,
            'name' => $name,
            'price_cents' => 5000,
            'kind' => 'kitchen',
            'active' => 1,
            'storefront_image_path' => $image,
        ];
    }
}
