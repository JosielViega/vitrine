<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontProductRepository;
use App\Services\StorefrontCatalogService;
use PHPUnit\Framework\TestCase;

final class StorefrontCatalogServiceTest extends TestCase
{
    public function testGroupsRealPrefixPatternAndPreservesVariantContract(): void
    {
        $service = $this->service([
            $this->product(79, 'Camarão c/ Batata e Aipim', 8500, 'kitchen'),
            $this->product(82, 'Meia: Camarão c/ Batata e Aipim', 7200, 'kitchen'),
        ]);

        $product = $service->findBySlug('camarao-c-batata-e-aipim');
        self::assertCount(1, $service->catalog()['products']);
        self::assertSame(['Inteira', 'Meia'], array_column($product['variants'], 'label'));
        self::assertSame([79, 82], array_column($product['variants'], 'product_id'));
        self::assertSame([8500, 7200], array_column($product['variants'], 'price_cents'));
        self::assertSame(['kitchen', 'kitchen'], array_column($product['variants'], 'kind'));
        self::assertIsInt($product['variants'][0]['price_cents']);
        self::assertIsInt($product['variants'][1]['price_cents']);
    }

    public function testPrefixPatternIsCaseInsensitiveAndAllowsFlexibleSpaces(): void
    {
        foreach (['MEIA: Batata', 'meia :Batata', '  MeIa  :  Batata  '] as $halfName) {
            $catalog = $this->service([
                $this->product(59, 'Batata', 3000),
                $this->product(116, $halfName, 2000),
            ])->catalog();

            self::assertCount(1, $catalog['products'], $halfName);
            self::assertSame(['Inteira', 'Meia'], array_column($catalog['products'][0]['variants'], 'label'));
        }
    }

    public function testKeepsOrphanHalfAsSingleMeiaVariant(): void
    {
        $product = $this->service([$this->product(113, 'Meia: Pescadinha', 3200)])->findBySlug('pescadinha');
        self::assertSame('Pescadinha', $product['name']);
        self::assertSame(['Meia'], array_column($product['variants'], 'label'));
        self::assertSame([113], array_column($product['variants'], 'product_id'));
    }

    public function testKeepsWholeOnlyAsSingleUnlabelledVariant(): void
    {
        $product = $this->service([$this->product(112, 'Pescadinha', 4200)])->findBySlug('pescadinha');
        self::assertSame([''], array_column($product['variants'], 'label'));
        self::assertSame([112], array_column($product['variants'], 'product_id'));
    }

    public function testDoesNotGroupAcrossSubcategories(): void
    {
        $subcategories = [$this->subcategory(1, 'Geral'), $this->subcategory(2, 'Especiais')];
        $catalog = $this->service([
            $this->product(1, 'Camarão', 7500),
            $this->product(2, 'Meia: Camarão', 6200, 'regular', 2),
        ], $subcategories)->catalog();

        self::assertCount(2, $catalog['products']);
        self::assertSame(['camarao-1', 'camarao-2'], array_column($catalog['products'], 'id'));
        self::assertSame(['', 'Meia'], array_map(static fn (array $product): string => $product['variants'][0]['label'], $catalog['products']));
    }

    public function testConfiguredAliasGroupsTheKnownCommercialException(): void
    {
        $service = $this->service([
            $this->product(127, 'Porção de carne', 4200, 'kitchen'),
            $this->product(130, 'Meia: Porção Carne', 3200, 'kitchen'),
        ], presentation: ['variant_aliases' => ['porcao-carne' => 'porcao-de-carne']]);

        $product = $service->findBySlug('porcao-de-carne');
        self::assertCount(1, $service->catalog()['products']);
        self::assertSame([127, 130], array_column($product['variants'], 'product_id'));
        self::assertSame([4200, 3200], array_column($product['variants'], 'price_cents'));
    }

    public function testAliasWithoutExistingTargetRemainsOrphan(): void
    {
        $catalog = $this->service([
            $this->product(130, 'Meia: Porção Carne', 3200),
        ], presentation: ['variant_aliases' => ['porcao-carne' => 'porcao-inexistente']])->catalog();

        self::assertCount(1, $catalog['products']);
        self::assertSame('porcao-carne', $catalog['products'][0]['id']);
        self::assertSame('Meia', $catalog['products'][0]['variants'][0]['label']);
    }

    public function testDoesNotUseFuzzyMatchingForSimilarNames(): void
    {
        $catalog = $this->service([
            $this->product(1, 'Pescada', 5000),
            $this->product(2, 'Meia: Pescadinha', 4000),
        ])->catalog();

        self::assertCount(2, $catalog['products']);
        self::assertContains('pescada', array_column($catalog['products'], 'id'));
        self::assertContains('pescadinha', array_column($catalog['products'], 'id'));
    }

    public function testLegacySuffixPatternRemainsCompatible(): void
    {
        $catalog = $this->service([
            $this->product(10, 'Camarão', 7500),
            $this->product(11, 'Camarão - Meia', 6200),
        ])->catalog();

        self::assertCount(1, $catalog['products']);
        self::assertSame(['Inteira', 'Meia'], array_column($catalog['products'][0]['variants'], 'label'));
    }

    public function testInactiveRowsAreNotPublishedAndCannotBeResolved(): void
    {
        $inactive = $this->product(2, 'Meia: Ativo', 200);
        $inactive['active'] = 0;
        $service = $this->service([$this->product(1, 'Ativo', 100), $inactive]);

        self::assertCount(1, $service->catalog()['products']);
        self::assertNotNull($service->findBySlug('ativo'));
        self::assertSame([''], array_column($service->findBySlug('ativo')['variants'], 'label'));
    }

    public function testRepositorySqlEnforcesActiveJoinChain(): void
    {
        foreach (['p.active = 1', 's.active = 1', 'c.active = 1', 'INNER JOIN subcategories', 'INNER JOIN categories'] as $fragment) {
            self::assertStringContainsString($fragment, StorefrontProductRepository::ACTIVE_PRODUCTS_SQL);
        }
    }

    private function service(array $products, array $subcategories = [], array $presentation = []): StorefrontCatalogService
    {
        $categories = [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]];
        $subcategories = $subcategories ?: [$this->subcategory(1, 'Geral')];
        $repository = new class($categories, $subcategories, $products) implements StorefrontCatalogRepository {
            public function __construct(private array $categories, private array $subcategories, private array $products) {}
            public function activeCategories(): array { return $this->categories; }
            public function activeSubcategories(): array { return $this->subcategories; }
            public function activeProducts(): array { return $this->products; }
        };

        return new StorefrontCatalogService($repository, $presentation + ['fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg']);
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
