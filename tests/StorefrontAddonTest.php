<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Repositories\StorefrontCatalogRepository;
use App\Services\StorefrontCatalogService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontAddonTest extends TestCase
{
    public function testConfiguredAddonsAreExcludedFromProductsAndTheirEmptySubcategory(): void
    {
        $catalog = $this->service()->catalog();

        self::assertSame(['camarao', 'cerveja'], array_column($catalog['products'], 'id'));
        self::assertSame([1, 2], array_column($catalog['subcategories'], 'id'));
        self::assertNotContains(3, array_column($catalog['subcategories'], 'id'));
        self::assertNull($this->service()->findVariantByProductId(117));
        self::assertNull($this->service()->findVariantByProductId(74));
        self::assertNull($this->service()->findBySlug('bacon'));
        self::assertNull($this->service()->findBySlug('mussarela'));
    }

    public function testEveryEligiblePortionReceivesBothAuthoritativeAddonsAndKeepsVariants(): void
    {
        $product = $this->service()->findBySlug('camarao');

        self::assertNotNull($product);
        self::assertSame(['Inteira', 'Meia'], array_column($product['variants'], 'label'));
        self::assertSame([79, 82], array_column($product['variants'], 'product_id'));
        self::assertSame([117, 74], array_column($product['addons'], 'product_id'));
        self::assertSame(['Bacon', 'Mussarela'], array_column($product['addons'], 'name'));
        self::assertSame([600, 600], array_column($product['addons'], 'price_cents'));
    }

    public function testAllProductsInEligibleSubcategoryReceiveAvailableAddons(): void
    {
        $rows = $this->products();
        $rows[] = $this->product(91, 1, 1, 'Calabresa', 3400);
        $products = array_values(array_filter(
            $this->service($rows)->catalog()['products'],
            static fn (array $product): bool => $product['subcategory_id'] === 1,
        ));

        self::assertCount(2, $products);
        foreach ($products as $product) {
            self::assertSame([117, 74], array_column($product['addons'], 'product_id'));
        }
    }

    public function testAddonsNeverEnterFeaturedPopularOrRelatedSelections(): void
    {
        $service = $this->service();
        $portion = $service->findBySlug('camarao');
        $selected = [$service->featured(), ...$service->popular(), ...$service->related($portion)];

        foreach (array_filter($selected) as $product) {
            self::assertNotContains(117, array_column($product['variants'], 'product_id'));
            self::assertNotContains(74, array_column($product['variants'], 'product_id'));
        }
    }

    public function testProductOutsideEligibleSubcategoryReceivesNoAddons(): void
    {
        self::assertSame([], $this->service()->findBySlug('cerveja')['addons']);
    }

    #[DataProvider('unavailableAddonProvider')]
    public function testUnavailableAddonIsNotOffered(int $active, int $visible): void
    {
        $rows = $this->products();
        $rows[3]['active'] = $active;
        $rows[3]['storefront_visible'] = $visible;

        $addons = $this->service($rows)->findBySlug('camarao')['addons'];

        self::assertSame([74], array_column($addons, 'product_id'));
    }

    public static function unavailableAddonProvider(): array
    {
        return [
            'inactive' => [0, 1],
            'hidden' => [1, 0],
        ];
    }

    public function testProductDetailRendersIndependentOptionalAddonCheckboxes(): void
    {
        $product = $this->service()->findBySlug('camarao');
        $html = (new View(dirname(__DIR__) . '/resources/views'))->render('pages/product', [
            'title' => 'Produto',
            'product' => $product,
            'related' => [],
        ]);

        self::assertStringContainsString('<legend>Acréscimos <small>opcionais</small></legend>', $html);
        self::assertSame(2, substr_count($html, 'name="addons[]"'));
        self::assertStringContainsString('value="117"', $html);
        self::assertStringContainsString('value="74"', $html);
        self::assertStringContainsString('+ R$ 6,00', $html);
    }

    public function testFrontendContractNormalizesAddonIdentityAndKeepsLegacyCartKey(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');

        self::assertIsString($source);
        self::assertStringContainsString("const CART_KEY = 'saoJorgeCartV2'", $source);
        self::assertStringContainsString('const addons = normalizeAddons(item.addons);', $source);
        self::assertStringContainsString('.sort((left, right) => left.productId - right.productId)', $source);
        self::assertStringContainsString("addons.map((addon) => addon.productId).join(',')", $source);
        self::assertStringContainsString('input[name="addons[]"]:checked', $source);
        self::assertStringContainsString('itemUnitPrice(item) * item.quantity', $source);
        self::assertStringContainsString("addons.className = 'order-item-addons'", $source);
    }

    private function service(?array $products = null): StorefrontCatalogService
    {
        $categories = [
            ['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1],
            ['id' => 2, 'name' => 'Bebidas', 'sort_order' => 1, 'active' => 1],
        ];
        $subcategories = [
            ['id' => 1, 'category_id' => 1, 'name' => 'Porções', 'sort_order' => 0, 'active' => 1],
            ['id' => 2, 'category_id' => 2, 'name' => 'Cervejas', 'sort_order' => 0, 'active' => 1],
            ['id' => 3, 'category_id' => 1, 'name' => 'Acréscimo', 'sort_order' => 1, 'active' => 1],
        ];
        $repository = new StorefrontAddonRepository($categories, $subcategories, $products ?? $this->products());

        return new StorefrontCatalogService($repository, [
            'addons' => [
                'product_ids' => [117, 74],
                'eligible_subcategories' => ['porcoes'],
            ],
            'fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg',
        ]);
    }

    private function products(): array
    {
        return [
            $this->product(79, 1, 1, 'Camarão', 8500),
            $this->product(82, 1, 1, 'Meia: Camarão', 7200),
            $this->product(3, 2, 2, 'Cerveja', 700),
            $this->product(117, 3, 1, 'Bacon', 600),
            $this->product(74, 3, 1, 'Mussarela', 600),
        ];
    }

    private function product(int $id, int $subcategoryId, int $categoryId, string $name, int $priceCents): array
    {
        return [
            'id' => $id,
            'subcategory_id' => $subcategoryId,
            'category_id' => $categoryId,
            'name' => $name,
            'price_cents' => $priceCents,
            'kind' => 'regular',
            'active' => 1,
            'storefront_visible' => 1,
        ];
    }
}

final class StorefrontAddonRepository implements StorefrontCatalogRepository
{
    public function __construct(
        private readonly array $categories,
        private readonly array $subcategories,
        private readonly array $products,
    ) {
    }

    public function activeCategories(): array { return $this->categories; }
    public function activeSubcategories(): array { return $this->subcategories; }
    public function activeProducts(): array { return $this->products; }
}