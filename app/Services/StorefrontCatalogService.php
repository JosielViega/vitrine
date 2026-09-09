<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontCatalogRepository;

final class StorefrontCatalogService
{
    private ?array $cachedCatalog = null;

    public function __construct(
        private readonly StorefrontCatalogRepository $repository,
        private readonly array $presentation,
    ) {
    }

    /** @return array{isOpen: bool, categories: list<array<string, mixed>>, subcategories: list<array<string, mixed>>, products: list<array<string, mixed>>} */
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

    public function featured(): ?array
    {
        $configured = (string) ($this->presentation['featured'] ?? '');

        return $this->findBySlug($configured) ?? ($this->catalog()['products'][0] ?? null);
    }

    /** @return list<array<string, mixed>> */
    public function popular(?string $excludedSlug = null): array
    {
        $products = [];
        foreach ((array) ($this->presentation['popular'] ?? []) as $slug) {
            $product = $this->findBySlug((string) $slug);
            if ($product !== null && $product['id'] !== $excludedSlug) {
                $products[$product['id']] = $product;
            }
        }
        foreach ($this->catalog()['products'] as $product) {
            if (count($products) >= 2) {
                break;
            }
            if ($product['id'] !== $excludedSlug) {
                $products[$product['id']] = $product;
            }
        }

        return array_slice(array_values($products), 0, 2);
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
            $slug = $this->slug((string) $category['name']) ?: 'categoria-' . $id;
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
            $entry = ['id' => $id, 'category_id' => $categoryId, 'slug' => $this->slug((string) $subcategory['name']) ?: 'subcategoria-' . $id, 'name' => (string) $subcategory['name'], 'sort_order' => (int) $subcategory['sort_order']];
            $subcategoryById[$id] = $entry;
            $publicSubcategories[] = $entry;
        }

        $rowsBySubcategory = [];
        foreach ($rows as $row) {
            $subcategoryId = (int) $row['subcategory_id'];
            $categoryId = (int) $row['category_id'];
            if ((int) ($row['active'] ?? 1) !== 1 || !isset($subcategoryById[$subcategoryId], $categoryById[$categoryId])) {
                continue;
            }
            $rowsBySubcategory[$subcategoryId][] = $row;
        }

        $products = [];
        foreach ($rowsBySubcategory as $subcategoryId => $subcategoryRows) {
            $halves = [];
            foreach ($subcategoryRows as $row) {
                $baseName = $this->halfBaseName((string) $row['name']);
                if ($baseName !== null) {
                    $halves[$this->halfMatchSlug($baseName)][] = $row;
                }
            }
            $consumed = [];
            foreach ($subcategoryRows as $row) {
                $productId = (int) $row['id'];
                if (isset($consumed[$productId]) || $this->halfBaseName((string) $row['name']) !== null) {
                    continue;
                }
                $baseName = trim((string) $row['name']);
                $matchingHalf = $halves[$this->slug($baseName)][0] ?? null;
                $variants = [$this->variant($row, $matchingHalf === null ? '' : 'Inteira')];
                if ($matchingHalf !== null) {
                    $variants[] = $this->variant($matchingHalf, 'Meia');
                    $consumed[(int) $matchingHalf['id']] = true;
                }
                $products[] = $this->product($row, $baseName, $variants, $categoryById, $subcategoryById);
                $consumed[$productId] = true;
            }
            foreach ($subcategoryRows as $row) {
                $productId = (int) $row['id'];
                $baseName = $this->halfBaseName((string) $row['name']);
                if ($baseName === null || isset($consumed[$productId])) {
                    continue;
                }
                $products[] = $this->product($row, $baseName, [$this->variant($row, 'Meia')], $categoryById, $subcategoryById);
            }
        }

        $slugCounts = array_count_values(array_column($products, 'id'));
        foreach ($products as &$product) {
            if ($slugCounts[$product['id']] > 1) {
                $product['id'] .= '-' . $product['subcategory_id'];
            }
            $editorial = (array) ($this->presentation['products'][$product['id']] ?? []);
            $product['image'] = (string) ($editorial['image'] ?? $this->fallbackImage($product['category']));
            $product['description'] = isset($editorial['description']) ? (string) $editorial['description'] : null;
        }
        unset($product);

        return ['isOpen' => (bool) ($this->presentation['isOpen'] ?? true), 'categories' => $publicCategories, 'subcategories' => $publicSubcategories, 'products' => $products];
    }

    private function product(array $row, string $name, array $variants, array $categories, array $subcategories): array
    {
        $category = $categories[(int) $row['category_id']];
        $subcategory = $subcategories[(int) $row['subcategory_id']];

        return ['id' => $this->slug($name) ?: 'produto-' . (int) $row['id'], 'name' => $name, 'category' => $category['slug'], 'category_id' => $category['id'], 'category_name' => $category['name'], 'subcategory_id' => $subcategory['id'], 'subcategory' => $subcategory, 'kinds' => array_values(array_unique(array_column($variants, 'kind'))), 'variants' => $variants];
    }

    private function variant(array $row, string $label): array
    {
        return ['product_id' => (int) $row['id'], 'label' => $label, 'price_cents' => (int) $row['price_cents'], 'kind' => (string) $row['kind']];
    }

    private function halfBaseName(string $name): ?string
    {
        foreach (['/^\s*meia\s*:\s*(?<base>.+?)\s*$/iu', '/^(?<base>.+?)\s+-\s*meia\s*$/iu'] as $pattern) {
            if (preg_match($pattern, $name, $matches) === 1 && trim($matches['base']) !== '') {
                return trim($matches['base']);
            }
        }

        return null;
    }

    private function halfMatchSlug(string $baseName): string
    {
        $slug = $this->slug($baseName);
        $aliases = (array) ($this->presentation['variant_aliases'] ?? []);

        return $this->slug((string) ($aliases[$slug] ?? $slug));
    }

    private function slug(string $value): string
    {
        $normalized = strtr(trim($value), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $slug = strtolower($ascii === false ? $value : $ascii);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    }

    private function fallbackImage(string $categorySlug): string
    {
        return (string) ($this->presentation['fallback_images'][$categorySlug] ?? $this->presentation['fallback_image'] ?? '/assets/images/products/mixed-portion-placeholder.jpg');
    }
}
