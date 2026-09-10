<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontProductRepository;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\WhatsAppCheckoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontVisibilityTest extends TestCase
{
    public function testRepositoryRequiresActiveAndVisibleAcrossTheHierarchy(): void
    {
        foreach (['active = 1', 'storefront_visible = 1'] as $fragment) {
            self::assertStringContainsString($fragment, StorefrontProductRepository::ACTIVE_CATEGORIES_SQL);
        }
        foreach (['s.active = 1', 's.storefront_visible = 1', 'c.active = 1', 'c.storefront_visible = 1'] as $fragment) {
            self::assertStringContainsString($fragment, StorefrontProductRepository::ACTIVE_SUBCATEGORIES_SQL);
        }
        foreach (['p.active = 1', 'p.storefront_visible = 1', 's.active = 1', 's.storefront_visible = 1', 'c.active = 1', 'c.storefront_visible = 1'] as $fragment) {
            self::assertStringContainsString($fragment, StorefrontProductRepository::ACTIVE_PRODUCTS_SQL);
        }
    }

    #[DataProvider('hierarchyProvider')]
    public function testHiddenOrInactiveHierarchyIsNotPublished(
        int $categoryActive,
        int $categoryVisible,
        int $subcategoryActive,
        int $subcategoryVisible,
        int $productActive,
        int $productVisible,
        int $expectedProducts,
    ): void {
        $service = $this->service(
            categoryActive: $categoryActive,
            categoryVisible: $categoryVisible,
            subcategoryActive: $subcategoryActive,
            subcategoryVisible: $subcategoryVisible,
            products: [$this->product(79, 'Camarão', $productActive, $productVisible)],
        );

        self::assertCount($expectedProducts, $service->catalog()['products']);
        self::assertSame($expectedProducts === 1, $service->findVariantByProductId(79) !== null);
    }

    public static function hierarchyProvider(): array
    {
        return [
            'all public' => [1, 1, 1, 1, 1, 1, 1],
            'category inactive' => [0, 1, 1, 1, 1, 1, 0],
            'category hidden' => [1, 0, 1, 1, 1, 1, 0],
            'subcategory inactive' => [1, 1, 0, 1, 1, 1, 0],
            'subcategory hidden' => [1, 1, 1, 0, 1, 1, 0],
            'product inactive' => [1, 1, 1, 1, 0, 1, 0],
            'product hidden' => [1, 1, 1, 1, 1, 0, 0],
        ];
    }

    #[DataProvider('variantVisibilityProvider')]
    public function testWholeAndHalfVisibilityRemainIndependent(int $wholeVisible, int $halfVisible, array $expectedIds): void
    {
        $service = $this->service(products: [
            $this->product(79, 'Camarão', 1, $wholeVisible),
            $this->product(82, 'Meia: Camarão', 1, $halfVisible),
        ]);
        $products = $service->catalog()['products'];
        $actualIds = $products === [] ? [] : array_column($products[0]['variants'], 'product_id');

        self::assertSame($expectedIds, $actualIds);
        self::assertSame(in_array(79, $expectedIds, true), $service->findVariantByProductId(79) !== null);
        self::assertSame(in_array(82, $expectedIds, true), $service->findVariantByProductId(82) !== null);
    }

    public static function variantVisibilityProvider(): array
    {
        return [
            'both visible' => [1, 1, [79, 82]],
            'whole only' => [1, 0, [79]],
            'half only' => [0, 1, [82]],
            'both hidden' => [0, 0, []],
        ];
    }

    public function testCheckoutRejectsAHiddenVariantThroughThePublicCatalog(): void
    {
        $catalog = $this->service(products: [
            $this->product(79, 'Camarão', 1, 1),
            $this->product(82, 'Meia: Camarão', 1, 0),
        ]);
        $now = new DateTimeImmutable('2026-09-10 18:00:00', new DateTimeZone('America/Sao_Paulo'));
        $hours = new BusinessHoursService(require dirname(__DIR__) . '/config/business.php', static fn (): DateTimeImmutable => $now);
        $checkout = new WhatsAppCheckoutService($hours, $catalog, ['number' => '5527998586163']);
        $result = $checkout->checkout([[
            'productId' => 82,
            'priceCents' => 7200,
            'quantity' => 1,
            'notes' => '',
        ]], 'pickup');

        self::assertSame('cart_invalid', $result['code']);
        self::assertSame([82], $result['invalid_product_ids']);
        self::assertArrayNotHasKey('whatsapp_url', $result);
    }

    private function service(
        int $categoryActive = 1,
        int $categoryVisible = 1,
        int $subcategoryActive = 1,
        int $subcategoryVisible = 1,
        array $products = [],
    ): StorefrontCatalogService {
        $categories = [[
            'id' => 1,
            'name' => 'Comidas',
            'sort_order' => 0,
            'active' => $categoryActive,
            'storefront_visible' => $categoryVisible,
        ]];
        $subcategories = [[
            'id' => 1,
            'category_id' => 1,
            'name' => 'Porções',
            'sort_order' => 0,
            'active' => $subcategoryActive,
            'storefront_visible' => $subcategoryVisible,
        ]];

        return new StorefrontCatalogService(
            new VisibilityFixtureRepository($categories, $subcategories, $products),
            ['fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg'],
        );
    }

    private function product(int $id, string $name, int $active, int $visible): array
    {
        return [
            'id' => $id,
            'subcategory_id' => 1,
            'category_id' => 1,
            'name' => $name,
            'price_cents' => $id === 82 ? 7200 : 8500,
            'kind' => 'kitchen',
            'active' => $active,
            'storefront_visible' => $visible,
        ];
    }
}

final class VisibilityFixtureRepository implements StorefrontCatalogRepository
{
    public function __construct(
        private readonly array $categories,
        private readonly array $subcategories,
        private readonly array $products,
    ) {
    }

    public function activeCategories(): array
    {
        return array_values(array_filter(
            $this->categories,
            static fn (array $category): bool => $category['active'] === 1 && $category['storefront_visible'] === 1,
        ));
    }

    public function activeSubcategories(): array
    {
        $categoryIds = array_column($this->activeCategories(), 'id');

        return array_values(array_filter(
            $this->subcategories,
            static fn (array $subcategory): bool => $subcategory['active'] === 1
                && $subcategory['storefront_visible'] === 1
                && in_array($subcategory['category_id'], $categoryIds, true),
        ));
    }

    public function activeProducts(): array
    {
        $categoryIds = array_column($this->activeCategories(), 'id');
        $subcategoryIds = array_column($this->activeSubcategories(), 'id');

        return array_values(array_filter(
            $this->products,
            static fn (array $product): bool => $product['active'] === 1
                && $product['storefront_visible'] === 1
                && in_array($product['category_id'], $categoryIds, true)
                && in_array($product['subcategory_id'], $subcategoryIds, true),
        ));
    }
}
