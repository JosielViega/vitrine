<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontCatalogRepository;

final class StorefrontCatalogService
{
    private ?array $cachedCatalog = null;
    /** @var null|array<int, array{product: array<string, mixed>, variant: array<string, mixed>}> */
    private ?array $variantIndex = null;
    private readonly StorefrontProductGroupingService $grouping;

    public function __construct(
        private readonly StorefrontCatalogRepository $repository,
        private readonly array $presentation,
        ?StorefrontProductGroupingService $grouping = null,
    ) {
        $this->grouping = $grouping ?? new StorefrontProductGroupingService((array) ($presentation['variant_aliases'] ?? []));
    }

    /** @return array{categories: list<array<string, mixed>>, subcategories: list<array<string, mixed>>, products: list<array<string, mixed>>} */
    public function catalog(): array
    {
        return $this->cachedCatalog ??= $this->transform(
            $this->repository->activeCategories(),
            $this->repository->activeSubcategories(),
            $this->repository->activeProducts(),
        );
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->catalog()['products'] as $product) {
            if ($product['id'] === $slug) {
                return $product;
            }
        }

        return null;
    }

    /** @return null|array{product: array<string, mixed>, variant: array<string, mixed>} */
    public function findVariantByProductId(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        if ($this->variantIndex === null) {
            $this->variantIndex = [];
            foreach ($this->catalog()['products'] as $product) {
                foreach ($product['variants'] as $variant) {
                    $this->variantIndex[(int) $variant['product_id']] = [
                        'product' => $product,
                        'variant' => $variant,
                    ];
                }
            }
        }

        return $this->variantIndex[$productId] ?? null;
    }

    public function featured(): ?array
    {
        $configured = (string) ($this->presentation['featured'] ?? '');

        return $this->findBySlug($configured) ?? ($this->catalog()['products'][0] ?? null);
    }

    /** @return list<array<string, mixed>> */
    public function popular(?string $excludedSlug = null, int $limit = 2): array
    {
        $limit = max(0, $limit);
        $products = [];
        foreach ((array) ($this->presentation['popular'] ?? []) as $slug) {
            $product = $this->findBySlug((string) $slug);
            if ($product !== null && $product['id'] !== $excludedSlug) {
                $products[$product['id']] = $product;
            }
        }
        foreach ($this->catalog()['products'] as $product) {
            if (count($products) >= $limit) {
                break;
            }
            if ($product['id'] !== $excludedSlug) {
                $products[$product['id']] = $product;
            }
        }

        return array_slice(array_values($products), 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function related(array $product): array
    {
        $configured = (array) ($this->presentation['products'][$product['id']]['related'] ?? []);
        $related = [];
        foreach ($configured as $slug) {
            $candidate = $this->findBySlug((string) $slug);
            if ($candidate !== null && $candidate['id'] !== $product['id']) {
                $related[$candidate['id']] = $candidate;
            }
        }
        foreach ($this->catalog()['products'] as $candidate) {
            if (count($related) >= 2) {
                break;
            }
            if ($candidate['id'] !== $product['id'] && $candidate['category_id'] === $product['category_id']) {
                $related[$candidate['id']] = $candidate;
            }
        }

        return array_slice(array_values($related), 0, 2);
    }

    /** @param list<array<string, mixed>> $categories @param list<array<string, mixed>> $subcategories @param list<array<string, mixed>> $rows */
    public function transform(array $categories, array $subcategories, array $rows): array
    {
        $publicCategories = [];
        $categoryById = [];
        foreach ($categories as $category) {
            if ((int) ($category['active'] ?? 1) !== 1) {
                continue;
            }
            $id = (int) $category['id'];
            $slug = $this->grouping->slug((string) $category['name']) ?: 'categoria-' . $id;
            $entry = ['id' => $id, 'slug' => $slug, 'name' => (string) $category['name'], 'sort_order' => (int) $category['sort_order']];
            $categoryById[$id] = $entry;
            $publicCategories[] = $entry;
        }

        $publicSubcategories = [];
        $subcategoryById = [];
        foreach ($subcategories as $subcategory) {
            $categoryId = (int) $subcategory['category_id'];
            if ((int) ($subcategory['active'] ?? 1) !== 1 || !isset($categoryById[$categoryId])) {
                continue;
            }
            $id = (int) $subcategory['id'];
            $entry = ['id' => $id, 'category_id' => $categoryId, 'slug' => $this->grouping->slug((string) $subcategory['name']) ?: 'subcategoria-' . $id, 'name' => (string) $subcategory['name'], 'sort_order' => (int) $subcategory['sort_order']];
            $subcategoryById[$id] = $entry;
            $publicSubcategories[] = $entry;
        }

        $addonProductIds = $this->addonProductIds();
        $addonRows = [];
        $publishableRows = [];
        foreach ($rows as $row) {
            $subcategoryId = (int) $row['subcategory_id'];
            $categoryId = (int) $row['category_id'];
            if ((int) ($row['active'] ?? 1) !== 1
                || (int) ($row['storefront_visible'] ?? 1) !== 1
                || !isset($subcategoryById[$subcategoryId], $categoryById[$categoryId])) {
                continue;
            }
            $productId = (int) $row['id'];
            if (isset($addonProductIds[$productId])) {
                $addonRows[$productId] = [
                    'product_id' => $productId,
                    'name' => (string) $row['name'],
                    'price_cents' => (int) $row['price_cents'],
                ];
                continue;
            }
            $publishableRows[] = $row;
        }

        $availableAddons = [];
        foreach (array_keys($addonProductIds) as $productId) {
            if (isset($addonRows[$productId])) {
                $availableAddons[] = $addonRows[$productId];
            }
        }
        $eligibleSubcategories = $this->eligibleAddonSubcategories();

        $products = [];
        foreach ($this->grouping->group($publishableRows) as $group) {
            $variants = array_map(
                fn (array $variant): array => $this->variant($variant['row'], $variant['label']),
                $group['variants'],
            );
            $products[] = $this->product(
                $group['primary_row'],
                $group['name'],
                $variants,
                $categoryById,
                $subcategoryById,
                $group['secondary_row'],
            );
            $lastIndex = array_key_last($products);
            $products[$lastIndex]['addons'] = isset($eligibleSubcategories[$products[$lastIndex]['subcategory']['slug']])
                ? $availableAddons
                : [];
        }

        $slugCounts = array_count_values(array_column($products, 'id'));
        foreach ($products as &$product) {
            if ($slugCounts[$product['id']] > 1) {
                $product['id'] .= '-' . $product['subcategory_id'];
            }
            $editorial = (array) ($this->presentation['products'][$product['id']] ?? []);
            $product['image'] = $product['managed_image']
                ?? (string) ($editorial['image'] ?? $this->fallbackImage($product['category']));
            unset($product['managed_image']);
            $product['description'] = isset($editorial['description']) ? (string) $editorial['description'] : null;
        }
        unset($product);

        $productSubcategoryIds = array_fill_keys(array_column($products, 'subcategory_id'), true);
        $publicSubcategories = array_values(array_filter(
            $publicSubcategories,
            static fn (array $subcategory): bool => isset($productSubcategoryIds[$subcategory['id']]),
        ));

        return ['categories' => $publicCategories, 'subcategories' => $publicSubcategories, 'products' => $products];
    }

    /** @return array<int, true> */
    private function addonProductIds(): array
    {
        $ids = [];
        foreach ((array) ($this->presentation['addons']['product_ids'] ?? []) as $productId) {
            if (is_int($productId) && $productId > 0) {
                $ids[$productId] = true;
            }
        }

        return $ids;
    }

    /** @return array<string, true> */
    private function eligibleAddonSubcategories(): array
    {
        $slugs = [];
        foreach ((array) ($this->presentation['addons']['eligible_subcategories'] ?? []) as $subcategory) {
            if (is_string($subcategory) && ($slug = $this->grouping->slug($subcategory)) !== '') {
                $slugs[$slug] = true;
            }
        }

        return $slugs;
    }

    private function product(array $row, string $name, array $variants, array $categories, array $subcategories, ?array $secondaryImageRow = null): array
    {
        $category = $categories[(int) $row['category_id']];
        $subcategory = $subcategories[(int) $row['subcategory_id']];

        return ['id' => $this->grouping->slug($name) ?: 'produto-' . (int) $row['id'], 'name' => $name, 'category' => $category['slug'], 'category_id' => $category['id'], 'category_name' => $category['name'], 'subcategory_id' => $subcategory['id'], 'subcategory' => $subcategory, 'kinds' => array_values(array_unique(array_column($variants, 'kind'))), 'variants' => $variants, 'managed_image' => $this->managedImage($row, $secondaryImageRow)];
    }

    private function managedImage(array $preferredRow, ?array $secondaryRow): ?string
    {
        foreach ([$preferredRow, $secondaryRow] as $imageRow) {
            $path = is_array($imageRow) ? trim((string) ($imageRow['storefront_image_path'] ?? '')) : '';
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    private function variant(array $row, string $label): array
    {
        return ['product_id' => (int) $row['id'], 'label' => $label, 'price_cents' => (int) $row['price_cents'], 'kind' => (string) $row['kind']];
    }

    private function fallbackImage(string $categorySlug): string
    {
        return (string) ($this->presentation['fallback_images'][$categorySlug] ?? $this->presentation['fallback_image'] ?? '/assets/images/products/mixed-portion-placeholder.jpg');
    }
}
