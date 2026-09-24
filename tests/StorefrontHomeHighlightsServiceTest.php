<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontCatalogRepository;
use App\Repositories\StorefrontHomeHighlightsRepositoryInterface;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontHomeHighlightsService;
use App\Services\StorefrontHomeHighlightsValidationException;
use PHPUnit\Framework\TestCase;

final class StorefrontHomeHighlightsServiceTest extends TestCase
{
    public function testResolvesPersistedFeaturedAndPopularInOrder(): void
    {
        $repository = new HomeHighlightsRepositoryFake('batata', 'camarao', 'carne');
        $highlights = $this->service($repository)->highlights();

        self::assertSame('batata', $highlights['featured']['id']);
        self::assertSame(['camarao', 'carne'], array_column($highlights['popular'], 'id'));
    }

    public function testPopularMayHaveZeroOrOneItem(): void
    {
        self::assertSame([], $this->service(new HomeHighlightsRepositoryFake('batata'))->highlights()['popular']);
        self::assertSame(['carne'], array_column($this->service(new HomeHighlightsRepositoryFake('batata', 'carne'))->highlights()['popular'], 'id'));
    }

    public function testUpdateRequiresPublicFeaturedAndRejectsUnknownOrAddon(): void
    {
        $repository = new HomeHighlightsRepositoryFake('batata');
        $service = $this->service($repository);

        foreach ([['', '', ''], ['inexistente', '', ''], ['bacon', '', '']] as $input) {
            try {
                $service->update(...$input);
                self::fail('Expected invalid highlight selection.');
            } catch (StorefrontHomeHighlightsValidationException) {
                self::assertSame('batata', $repository->settings()['featured_product_slug']);
            }
        }

        $hiddenService = $this->service($repository, hiddenSlug: 'oculto');
        $this->expectException(StorefrontHomeHighlightsValidationException::class);
        $hiddenService->update('oculto', '', '');
    }

    public function testUpdateRejectsDuplicatesAcrossAllSlots(): void
    {
        $service = $this->service(new HomeHighlightsRepositoryFake('batata'));

        foreach ([['batata', 'batata', ''], ['batata', 'carne', 'batata'], ['batata', 'carne', 'carne']] as $input) {
            try {
                $service->update(...$input);
                self::fail('Expected duplicate selection.');
            } catch (StorefrontHomeHighlightsValidationException $exception) {
                self::assertStringContainsString('diferente', $exception->getMessage());
            }
        }
    }

    public function testPopularSecondSlotAloneIsCompacted(): void
    {
        $repository = new HomeHighlightsRepositoryFake('batata');

        $this->service($repository)->update('batata', '', 'carne');

        self::assertSame('carne', $repository->settings()['popular_product_1_slug']);
        self::assertNull($repository->settings()['popular_product_2_slug']);
    }

    public function testHiddenPersistedProductsAreOmittedWithoutRandomFallbackAndWarnAdmin(): void
    {
        $repository = new HomeHighlightsRepositoryFake('oculto', 'carne', 'oculto');
        $service = $this->service($repository, hiddenSlug: 'oculto');

        $highlights = $service->highlights();
        $dashboard = $service->dashboard();

        self::assertNull($highlights['featured']);
        self::assertSame(['carne'], array_column($highlights['popular'], 'id'));
        self::assertCount(2, $dashboard['warnings']);
        self::assertStringContainsString('não está disponível', implode(' ', $dashboard['warnings']));
    }

    public function testDashboardOptionsUseLiveCatalogImagePriceAndContext(): void
    {
        $repository = new HomeHighlightsRepositoryFake('batata');
        $dashboard = $this->service($repository, batataPrice: 4321, batataImage: '/uploads/products/live.webp')->dashboard();
        $options = array_merge(...array_values($dashboard['groups']));
        $batata = array_values(array_filter($options, static fn (array $option): bool => $option['slug'] === 'batata'))[0];

        self::assertSame('/uploads/products/live.webp', $batata['image']);
        self::assertSame('R$ 43,21', $batata['price']);
        self::assertSame('Comidas › Porções', $batata['context']);
    }

    private function service(HomeHighlightsRepositoryFake $repository, ?string $hiddenSlug = null, int $batataPrice = 3000, string $batataImage = '/batata.jpg'): StorefrontHomeHighlightsService
    {
        $products = [
            $this->product(1, 'Batata', $batataPrice, $batataImage, $hiddenSlug !== 'batata'),
            $this->product(2, 'Camarão', 7500, '/camarao.jpg', $hiddenSlug !== 'camarao'),
            $this->product(3, 'Carne', 5200, '/carne.jpg', $hiddenSlug !== 'carne'),
            $this->product(4, 'Oculto', 1000, '/oculto.jpg', $hiddenSlug !== 'oculto'),
            $this->product(117, 'Bacon', 600, '/bacon.jpg'),
        ];
        $catalogRepository = new class($products) implements StorefrontCatalogRepository {
            public function __construct(private array $products) {}
            public function activeCategories(): array { return [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]]; }
            public function activeSubcategories(): array { return [['id' => 1, 'category_id' => 1, 'name' => 'Porções', 'sort_order' => 0, 'active' => 1]]; }
            public function activeProducts(): array { return $this->products; }
        };
        $presentation = ['addons' => ['product_ids' => [117], 'eligible_subcategories' => ['porcoes']], 'fallback_image' => '/fallback.jpg'];

        return new StorefrontHomeHighlightsService(new HomeHighlightsRepositoryProxy($repository), new StorefrontCatalogService($catalogRepository, $presentation));
    }

    private function product(int $id, string $name, int $price, string $image, bool $visible = true): array
    {
        return ['id' => $id, 'subcategory_id' => 1, 'category_id' => 1, 'name' => $name, 'price_cents' => $price, 'kind' => 'kitchen', 'active' => 1, 'storefront_visible' => $visible ? 1 : 0, 'storefront_image_path' => $image];
    }
}

final class HomeHighlightsRepositoryFake implements StorefrontHomeHighlightsRepositoryInterface
{
    public function __construct(private string $featured, private ?string $popular1 = null, private ?string $popular2 = null) {}
    public function settings(): array { return ['featured_product_slug' => $this->featured, 'popular_product_1_slug' => $this->popular1, 'popular_product_2_slug' => $this->popular2]; }
    public function updateHighlights(string $featured, ?string $popular1, ?string $popular2): void { $this->featured = $featured; $this->popular1 = $popular1; $this->popular2 = $popular2; }
}

final class HomeHighlightsRepositoryProxy implements StorefrontHomeHighlightsRepositoryInterface
{
    public function __construct(private readonly HomeHighlightsRepositoryFake $repository) {}
    public function settings(): array { return $this->repository->settings(); }
    public function updateHighlights(string $featured, ?string $popular1, ?string $popular2): void { $this->repository->updateHighlights($featured, $popular1, $popular2); }
}
