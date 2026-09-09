<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Repositories\StorefrontCatalogRepository;
use App\Services\StorefrontCatalogService;
use PHPUnit\Framework\TestCase;

final class StorefrontTest extends TestCase
{
    public function testPublicRoutesUseFixtureCatalogWithoutDatabase(): void
    {
        $root = dirname(__DIR__);
        $app = ['view' => new View($root . '/resources/views'), 'catalog' => $this->catalog(), 'router' => new Router()];
        $router = require $root . '/routes/web.php';

        $home = $this->dispatch($router, 'GET', '/');
        $menu = $this->dispatch($router, 'GET', '/cardapio');
        $product = $this->dispatch($router, 'GET', '/produto/camarao');
        $order = $this->dispatch($router, 'GET', '/pedido');
        $health = $this->dispatch($router, 'GET', '/health');
        $unknown = $this->dispatch($router, 'GET', '/produto/inativo');

        self::assertSame(200, $home->status());
        self::assertStringContainsString('Destaque da casa', $home->body());
        self::assertStringContainsString('/assets/images/brand/logo-sao-jorge.png', $home->body());
        self::assertSame(200, $menu->status());
        self::assertStringContainsString('Buscar no cardápio', $menu->body());
        self::assertSame(200, $product->status());
        self::assertStringContainsString('R$ 75,00', $product->body());
        self::assertStringContainsString('R$ 62,00', $product->body());
        self::assertStringContainsString('value="10"', $product->body());
        self::assertStringContainsString('value="11"', $product->body());
        self::assertSame(200, $order->status());
        self::assertStringContainsString('Finalizar pedido no WhatsApp', $order->body());
        self::assertSame('{"status":"ok"}', $health->body());
        self::assertSame(404, $unknown->status());
    }

    public function testVersionedCartContractUsesRealProductIdAndIntegerCents(): void
    {
        $javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("const CART_KEY = 'saoJorgeCartV2'", $javascript);
        self::assertStringContainsString('productId', $javascript);
        self::assertStringContainsString('publicProductId', $javascript);
        self::assertStringContainsString('priceCents', $javascript);
        self::assertStringNotContainsString("const CART_KEY = 'saoJorgeCart'", $javascript);
    }

    public function testNotFoundEscapesRequestedPath(): void
    {
        $html = (new View(dirname(__DIR__) . '/resources/views'))->render('pages/404', ['title' => 'Página não encontrada', 'path' => '/<script>alert(1)</script>']);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    private function catalog(): StorefrontCatalogService
    {
        $categories = [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]];
        $subcategories = [['id' => 1, 'category_id' => 1, 'name' => 'Porções', 'sort_order' => 0, 'active' => 1]];
        $products = [
            ['id' => 10, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Camarão', 'price_cents' => 7500, 'kind' => 'kitchen', 'active' => 1],
            ['id' => 11, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Meia: Camarão', 'price_cents' => 6200, 'kind' => 'kitchen', 'active' => 1],
            ['id' => 12, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Batata', 'price_cents' => 3000, 'kind' => 'kitchen', 'active' => 1],
        ];
        $repository = new class($categories, $subcategories, $products) implements StorefrontCatalogRepository {
            public function __construct(private array $categories, private array $subcategories, private array $products) {}
            public function activeCategories(): array { return $this->categories; }
            public function activeSubcategories(): array { return $this->subcategories; }
            public function activeProducts(): array { return $this->products; }
        };
        return new StorefrontCatalogService($repository, ['featured' => 'camarao', 'popular' => ['batata'], 'fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg']);
    }

    private function dispatch(Router $router, string $method, string $uri): object
    {
        return $router->dispatch(new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]));
    }
}
