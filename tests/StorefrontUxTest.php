<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class StorefrontUxTest extends TestCase
{
    public function testImageFiltersComeOnlyFromRenderedGroupsAndDisambiguateRepeatedNames(): void
    {
        $html = $this->renderImageAdmin([
            $this->imageGroup('product-1', 1, 'Geral', 10, 'Bebidas', 'Água'),
            $this->imageGroup('product-2', 2, 'Geral', 20, 'Outros', 'Carvão'),
            $this->imageGroup('product-3', 3, 'Porções', 30, 'Comidas', 'Batata'),
            $this->imageGroup('product-4', 3, 'Porções', 30, 'Comidas', 'Camarão'),
        ]);

        self::assertStringContainsString('data-image-filter="all"', $html);
        self::assertSame(1, substr_count($html, 'data-image-filter="3"'));
        self::assertStringContainsString('Bebidas › Geral', $html);
        self::assertStringContainsString('Outros › Geral', $html);
        self::assertStringContainsString('>Porções</button>', $html);
        self::assertStringNotContainsString('Vazia', $html);
    }

    public function testImageCardsExposeSemanticFilteringAndPreviewState(): void
    {
        $html = $this->renderImageAdmin([$this->imageGroup('product-1', 3, 'Porções', 30, 'Comidas', 'Batata')]);

        self::assertStringContainsString('data-subcategory-id="3"', $html);
        self::assertStringContainsString('data-category-id="30"', $html);
        self::assertStringContainsString('data-image-preview', $html);
        self::assertStringContainsString('data-original-src="/fallback.jpg"', $html);
        self::assertStringContainsString('data-original-label="Padrão"', $html);
        self::assertStringContainsString('data-image-unsaved hidden', $html);
        self::assertStringContainsString('Nenhum produto encontrado.', $html);
    }

    public function testAdminJavascriptCombinesFilterAndSearchAndManagesObjectUrls(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/assets/js/admin.js');

        self::assertIsString($source);
        self::assertStringContainsString("let activeSubcategory = 'all'", $source);
        self::assertStringContainsString('matchesSearch', $source);
        self::assertStringContainsString('matchesSubcategory', $source);
        self::assertStringContainsString('!matchesSearch || !matchesSubcategory', $source);
        self::assertStringContainsString('URL.createObjectURL(file)', $source);
        self::assertStringContainsString('URL.revokeObjectURL(previewUrl)', $source);
        self::assertStringContainsString("status.textContent = 'Não salvo'", $source);
        self::assertStringContainsString('image.dataset.originalSrc', $source);
        self::assertStringContainsString('status.dataset.originalLabel', $source);
        self::assertStringContainsString("window.addEventListener('beforeunload'", $source);
    }

    public function testOrderJavascriptFiltersConceptsLimitsTwoAndHidesEmptySection(): void
    {
        $source = $this->appJavascript();

        self::assertStringContainsString('new Set(cart.map((item) => item.publicProductId))', $source);
        self::assertStringContainsString('card.dataset.publicProductId', $source);
        self::assertStringContainsString('.slice(0, 2)', $source);
        self::assertStringContainsString('recommendations.hidden = cart.length === 0 || eligible.length === 0', $source);
        self::assertStringContainsString('renderRecommendations();', $source);
    }

    public function testCheckoutUsesSynchronousAuxiliaryWindowAndClearsOnlyOnAuthoritativeSuccess(): void
    {
        $source = $this->appJavascript();
        $open = strpos($source, "window.open('', '_blank')");
        $fetch = strpos($source, "fetch('/checkout/whatsapp'");
        $success = strpos($source, 'response.ok && result.ok');
        $clear = strpos($source, 'window.localStorage.removeItem(CART_KEY)');

        self::assertIsInt($open);
        self::assertIsInt($fetch);
        self::assertIsInt($success);
        self::assertIsInt($clear);
        self::assertLessThan($fetch, $open);
        self::assertLessThan($clear, $success);
        self::assertSame(1, substr_count($source, 'window.localStorage.removeItem(CART_KEY)'));
        self::assertStringContainsString("result.whatsapp_url.startsWith('https://wa.me/')", $source);
        self::assertStringContainsString('whatsappWindow.location.replace(result.whatsapp_url)', $source);
        self::assertStringContainsString("window.location.replace('/')", $source);
        self::assertStringContainsString('if (!checkoutSucceeded && !whatsappWindow.closed) whatsappWindow.close()', $source);
        self::assertStringContainsString("result.code === 'cart_changed'", $source);
        self::assertStringContainsString('saveCart(result.cart)', $source);
        self::assertStringNotContainsString('window.location.assign(result.whatsapp_url)', $source);
    }

    private function appJavascript(): string
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
        self::assertIsString($source);
        return $source;
    }

    private function renderImageAdmin(array $images): string
    {
        return (new View(dirname(__DIR__) . '/resources/views'))->render('admin/index', [
            'title' => 'Admin',
            'username' => 'manager',
            'csrfToken' => 'token',
            'section' => 'images',
            'dashboard' => ['categories' => [], 'subcategories' => [], 'products' => [], 'images' => $images],
            'flashMessages' => [],
            'imageWarnings' => [],
        ], 'layouts/admin');
    }

    private function imageGroup(string $key, int $subcategoryId, string $subcategory, int $categoryId, string $category, string $name): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'category_name' => $category,
            'category_id' => $categoryId,
            'subcategory_name' => $subcategory,
            'subcategory_id' => $subcategoryId,
            'product_ids' => [1],
            'variant_labels' => ['Única'],
            'variant_names' => [$name],
            'image' => '/fallback.jpg',
            'image_source' => 'fallback',
            'image_id' => null,
            'mime_type' => null,
            'width' => null,
            'height' => null,
            'size_bytes' => null,
            'active' => 1,
            'visible' => 1,
        ];
    }
}
