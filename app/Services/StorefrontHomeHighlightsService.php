<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontHomeHighlightsRepositoryInterface;

final class StorefrontHomeHighlightsService
{
    public function __construct(
        private readonly StorefrontHomeHighlightsRepositoryInterface $repository,
        private readonly StorefrontCatalogService $catalog,
    ) {
    }

    /** @return array{featured: ?array<string, mixed>, popular: list<array<string, mixed>>} */
    public function highlights(): array
    {
        $settings = $this->repository->settings();
        $products = $this->productsBySlug();
        $popular = [];
        foreach ([$settings['popular_product_1_slug'], $settings['popular_product_2_slug']] as $slug) {
            if (is_string($slug) && isset($products[$slug])) {
                $popular[] = $products[$slug];
            }
        }

        return [
            'featured' => $products[(string) $settings['featured_product_slug']] ?? null,
            'popular' => $popular,
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $settings = $this->repository->settings();
        $products = $this->productsBySlug();
        $groups = [];
        foreach ($products as $product) {
            $context = $product['category_name'] . ' › ' . $product['subcategory']['name'];
            $groups[$context][] = [
                'slug' => $product['id'],
                'name' => $product['name'],
                'image' => $product['image'],
                'context' => $context,
                'price' => 'R$ ' . number_format(min(array_column($product['variants'], 'price_cents')) / 100, 2, ',', '.'),
            ];
        }

        $warnings = [];
        $featuredSlug = (string) $settings['featured_product_slug'];
        if (!isset($products[$featuredSlug])) {
            $warnings[] = 'O destaque configurado não está disponível na vitrine. Escolha outro produto.';
        }
        foreach ([1 => $settings['popular_product_1_slug'], 2 => $settings['popular_product_2_slug']] as $position => $slug) {
            if (is_string($slug) && $slug !== '' && !isset($products[$slug])) {
                $warnings[] = 'O produto configurado em Mais pedidos — posição ' . $position . ' não está disponível na vitrine.';
            }
        }

        return [
            'settings' => $settings,
            'groups' => $groups,
            'featured' => $products[$featuredSlug] ?? null,
            'popular1' => is_string($settings['popular_product_1_slug']) ? ($products[$settings['popular_product_1_slug']] ?? null) : null,
            'popular2' => is_string($settings['popular_product_2_slug']) ? ($products[$settings['popular_product_2_slug']] ?? null) : null,
            'warnings' => $warnings,
        ];
    }

    public function update(mixed $featured, mixed $popular1, mixed $popular2): void
    {
        if (!is_string($featured) || !is_string($popular1) || !is_string($popular2)) {
            throw new StorefrontHomeHighlightsValidationException('Revise os produtos selecionados.');
        }
        $featured = trim($featured);
        $popular = array_values(array_filter([trim($popular1), trim($popular2)], static fn (string $slug): bool => $slug !== ''));
        if ($featured === '') {
            throw new StorefrontHomeHighlightsValidationException('Escolha o produto de Destaque da casa.');
        }

        $products = $this->productsBySlug();
        foreach ([$featured, ...$popular] as $slug) {
            if (!isset($products[$slug])) {
                throw new StorefrontHomeHighlightsValidationException('Um dos produtos selecionados não está disponível na vitrine.');
            }
        }
        if (count(array_unique([$featured, ...$popular])) !== count([$featured, ...$popular])) {
            throw new StorefrontHomeHighlightsValidationException('Cada posição da Home deve usar um produto diferente.');
        }

        $this->repository->updateHighlights($featured, $popular[0] ?? null, $popular[1] ?? null);
    }

    /** @return array<string, array<string, mixed>> */
    private function productsBySlug(): array
    {
        $products = [];
        foreach ($this->catalog->catalog()['products'] as $product) {
            $products[$product['id']] = $product;
        }
        return $products;
    }
}
