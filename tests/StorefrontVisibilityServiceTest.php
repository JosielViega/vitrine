<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\StorefrontVisibilityRepositoryInterface;
use App\Services\StorefrontVisibilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontVisibilityServiceTest extends TestCase
{
    #[DataProvider('toggleProvider')]
    public function testEachTypeCanBeTurnedOffAndOn(string $type, string $collection): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $service = new StorefrontVisibilityService($repository);

        self::assertTrue($service->setVisibility($type, 1, false));
        self::assertSame(0, $repository->{$collection}[0]['storefront_visible']);
        self::assertTrue($service->setVisibility($type, 1, true));
        self::assertSame(1, $repository->{$collection}[0]['storefront_visible']);
    }

    public static function toggleProvider(): array
    {
        return [
            'category' => ['category', 'categoryRows'],
            'subcategory' => ['subcategory', 'subcategoryRows'],
            'product' => ['product', 'productRows'],
        ];
    }

    public function testUpdatesNeverChangeActive(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $service = new StorefrontVisibilityService($repository);

        foreach (['category', 'subcategory', 'product'] as $type) {
            $service->setVisibility($type, 1, false);
        }

        self::assertSame(1, $repository->categoryRows[0]['active']);
        self::assertSame(1, $repository->subcategoryRows[0]['active']);
        self::assertSame(1, $repository->productRows[0]['active']);
    }

    public function testCategoryVisibilityDoesNotCascadeToChildren(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->productRows[0]['storefront_visible'] = 0;
        $service = new StorefrontVisibilityService($repository);

        $service->setVisibility('category', 1, false);
        $service->setVisibility('category', 1, true);

        self::assertSame(1, $repository->subcategoryRows[0]['storefront_visible']);
        self::assertSame(0, $repository->productRows[0]['storefront_visible']);
    }

    public function testSubcategoryVisibilityDoesNotCascadeToProducts(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->productRows[0]['storefront_visible'] = 0;
        $service = new StorefrontVisibilityService($repository);

        $service->setVisibility('subcategory', 1, false);
        $service->setVisibility('subcategory', 1, true);

        self::assertSame(0, $repository->productRows[0]['storefront_visible']);
    }

    public function testVisibleHierarchyAppearsInEveryVisibilitySection(): void
    {
        $dashboard = (new StorefrontVisibilityService(new StorefrontVisibilityFakeRepository()))->dashboard();

        self::assertCount(1, $dashboard['categories']);
        self::assertCount(1, $dashboard['subcategories']);
        self::assertCount(1, $dashboard['products']);
    }

    public function testHiddenCategoryRemainsListedWhileItsDescendantsAreFiltered(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->categoryRows[0]['storefront_visible'] = 0;
        $repository->subcategoryRows[0]['category_storefront_visible'] = 0;
        $repository->productRows[0]['category_storefront_visible'] = 0;
        $dashboard = (new StorefrontVisibilityService($repository))->dashboard();

        self::assertCount(1, $dashboard['categories']);
        self::assertSame('Oculto', $dashboard['categories'][0]['effective_status']);
        self::assertSame([], $dashboard['subcategories']);
        self::assertSame([], $dashboard['products']);
    }

    public function testHiddenSubcategoryRemainsListedWhileItsProductsAreFiltered(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->subcategoryRows[0]['storefront_visible'] = 0;
        $repository->productRows[0]['subcategory_storefront_visible'] = 0;
        $dashboard = (new StorefrontVisibilityService($repository))->dashboard();

        self::assertCount(1, $dashboard['subcategories']);
        self::assertSame('Oculto', $dashboard['subcategories'][0]['effective_status']);
        self::assertSame([], $dashboard['products']);
    }

    public function testHiddenProductRemainsListedWithItsOwnStatus(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->productRows[0]['storefront_visible'] = 0;
        $dashboard = (new StorefrontVisibilityService($repository))->dashboard();

        self::assertCount(1, $dashboard['products']);
        self::assertSame(0, $dashboard['products'][0]['storefront_visible']);
        self::assertSame('Oculto', $dashboard['products'][0]['effective_status']);
    }

    public function testOperationalActiveDoesNotControlAdministrativeFiltering(): void
    {
        $repository = new StorefrontVisibilityFakeRepository();
        $repository->categoryRows[0]['active'] = 0;
        $repository->subcategoryRows[0]['category_active'] = 0;
        $repository->productRows[0]['category_active'] = 0;
        $dashboard = (new StorefrontVisibilityService($repository))->dashboard();

        self::assertCount(1, $dashboard['categories']);
        self::assertCount(1, $dashboard['subcategories']);
        self::assertCount(1, $dashboard['products']);
        self::assertSame('Inativo no sistema', $dashboard['categories'][0]['effective_status']);
        self::assertSame('Categoria inativa no sistema', $dashboard['subcategories'][0]['effective_status']);
        self::assertSame('Categoria inativa no sistema', $dashboard['products'][0]['effective_status']);
    }

    public function testUnknownItemReturnsFalse(): void
    {
        self::assertFalse((new StorefrontVisibilityService(new StorefrontVisibilityFakeRepository()))->setVisibility('product', 999, false));
    }
}

final class StorefrontVisibilityFakeRepository implements StorefrontVisibilityRepositoryInterface
{
    public array $categoryRows = [['id' => 1, 'name' => 'Comidas', 'active' => 1, 'storefront_visible' => 1]];
    public array $subcategoryRows = [['id' => 1, 'category_id' => 1, 'category_name' => 'Comidas', 'name' => 'Porções', 'active' => 1, 'storefront_visible' => 1, 'category_active' => 1, 'category_storefront_visible' => 1]];
    public array $productRows = [['id' => 1, 'name' => 'Camarão', 'price_cents' => 8500, 'kind' => 'kitchen', 'active' => 1, 'storefront_visible' => 1, 'subcategory_id' => 1, 'subcategory_name' => 'Porções', 'subcategory_active' => 1, 'subcategory_storefront_visible' => 1, 'category_id' => 1, 'category_name' => 'Comidas', 'category_active' => 1, 'category_storefront_visible' => 1]];

    public function categories(): array { return $this->categoryRows; }
    public function subcategories(): array { return $this->subcategoryRows; }
    public function products(): array { return $this->productRows; }
    public function setCategoryVisibility(int $id, bool $visible): bool { return $this->set($this->categoryRows, $id, $visible); }
    public function setSubcategoryVisibility(int $id, bool $visible): bool { return $this->set($this->subcategoryRows, $id, $visible); }
    public function setProductVisibility(int $id, bool $visible): bool { return $this->set($this->productRows, $id, $visible); }

    private function set(array &$rows, int $id, bool $visible): bool
    {
        foreach ($rows as &$row) {
            if ($row['id'] === $id) {
                $row['storefront_visible'] = $visible ? 1 : 0;
                return true;
            }
        }

        return false;
    }
}
