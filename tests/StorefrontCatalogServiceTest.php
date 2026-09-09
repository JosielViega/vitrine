<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontProductRepository;
use App\Services\StorefrontCatalogService;
use PHPUnit\Framework\TestCase;

final class StorefrontCatalogServiceTest extends TestCase
{
    public function testSimpleProductPreservesIntegerCentsAndRealId(): void
    {
        $product = $this->service([$this->product(10, 'Coxinha', 800)])->catalog()['products'][0];
        self::assertSame('coxinha', $product['id']);
        self::assertSame(10, $product['variants'][0]['product_id']);
        self::assertSame(800, $product['variants'][0]['price_cents']);
        self::assertIsInt($product['variants'][0]['price_cents']);
    }

    public function testGroupsOnlyBaseAndSuffixMeiaInSameSubcategory(): void
    {
        $service = $this->service([$this->product(10, 'Camarão', 7500, 'kitchen'), $this->product(11, 'Camarão - MeIa  ', 6200, 'kitchen')]);
        $product = $service->findBySlug('camarao');
        self::assertCount(1, $service->catalog()['products']);
        self::assertSame(['Inteira', 'Meia'], array_column($product['variants'], 'label'));
        self::assertSame([10, 11], array_column($product['variants'], 'product_id'));
        self::assertSame([7500, 6200], array_column($product['variants'], 'price_cents'));
    }

    public function testKeepsOrphanSuffixMeia(): void
    {
        $product = $this->service([$this->product(11, 'Camarão - Meia', 6200)])->findBySlug('camarao');
        self::assertSame('Camarão', $product['name']);
        self::assertSame('Meia', $product['variants'][0]['label']);
        self::assertSame(11, $product['variants'][0]['product_id']);
    }

    public function testDoesNotGroupSimilarNamesOrObservedPrefixPattern(): void
    {
        $products = [$this->product(1, 'Brahma Long Neck', 900), $this->product(2, 'Brahma Latão', 700), $this->product(3, 'Brahma Litrinho', 500), $this->product(4, 'Meia: Camarão', 4800)];
        $catalog = $this->service($products)->catalog();
        self::assertCount(4, $catalog['products']);
        self::assertContains('meia-camarao', array_column($catalog['products'], 'id'));
    }

    public function testSameNamesInDifferentSubcategoriesGetUniqueSlugs(): void
    {
        $subcategories = [$this->subcategory(1, 'Geral'), $this->subcategory(2, 'Especiais')];
        $products = [$this->product(1, 'Água', 300), $this->product(2, 'Água', 400, 'regular', 2)];
        $catalog = $this->service($products, $subcategories)->catalog();
        self::assertSame(['agua-1', 'agua-2'], array_column($catalog['products'], 'id'));
        self::assertSame([1, 2], array_column($catalog['products'], 'subcategory_id'));
    }

    public function testInactiveRowsAreNotPublishedAndCannotBeResolved(): void
    {
        $inactive = $this->product(2, 'Inativo', 200); $inactive['active'] = 0;
        $service = $this->service([$this->product(1, 'Ativo', 100), $inactive]);
        self::assertCount(1, $service->catalog()['products']);
        self::assertNotNull($service->findBySlug('ativo'));
        self::assertNull($service->findBySlug('inativo'));
    }

    public function testRepositorySqlEnforcesActiveJoinChain(): void
    {
        foreach (['p.active = 1', 's.active = 1', 'c.active = 1', 'INNER JOIN subcategories', 'INNER JOIN categories'] as $fragment) {
            self::assertStringContainsString($fragment, StorefrontProductRepository::ACTIVE_PRODUCTS_SQL);
        }
    }

    private function service(array $products, array $subcategories = []): StorefrontCatalogService
    {
        $categories = [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]];
        $subcategories = $subcategories ?: [$this->subcategory(1, 'Geral')];
        $repository = new class($categories, $subcategories, $products) implements StorefrontCatalogRepository {
            public function __construct(private array $categories, private array $subcategories, private array $products) {}
            public function activeCategories(): array { return $this->categories; }
            public function activeSubcategories(): array { return $this->subcategories; }
            public function activeProducts(): array { return $this->products; }
        };
        return new StorefrontCatalogService($repository, ['fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg']);
    }

    private function subcategory(int $id, string $name): array
    {
        return ['id' => $id, 'category_id' => 1, 'name' => $name, 'sort_order' => 0, 'active' => 1];
    }

    private function product(int $id, string $name, int $priceCents, string $kind = 'regular', int $subcategoryId = 1): array
    {
        return ['id' => $id, 'subcategory_id' => $subcategoryId, 'category_id' => 1, 'name' => $name, 'price_cents' => $priceCents, 'kind' => $kind, 'active' => 1];
    }
}
