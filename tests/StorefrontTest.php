<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class StorefrontTest extends TestCase
{
    public function testHomeRendersPromotionalStorefront(): void
    {
        $root = dirname(__DIR__);
        $menu = require $root . '/config/menu.php';
        $controller = new HomeController(new View($root . '/resources/views'), $menu);

        $response = $controller->index();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Bar e Lanchonete', $response->body());
        self::assertStringContainsString('Destaque da casa', $response->body());
        self::assertStringContainsString('Mais pedidos', $response->body());
        self::assertStringContainsString('Camarão c/ Batata ou Aipim', $response->body());
        self::assertStringNotContainsString('Reusable PHP starter', $response->body());
    }

    public function testPublicStorefrontRoutes(): void
    {
        $root = dirname(__DIR__);
        $app = require $root . '/bootstrap/app.php';
        $router = require $root . '/routes/web.php';

        $home = $this->dispatch($router, 'GET', '/');
        $menu = $this->dispatch($router, 'GET', '/cardapio');
        $product = $this->dispatch($router, 'GET', '/produto/camarao-batata-aipim');
        $order = $this->dispatch($router, 'GET', '/pedido');
        $health = $this->dispatch($router, 'GET', '/health');
        $unknownProduct = $this->dispatch($router, 'GET', '/produto/item-inexistente');
        $missing = $this->dispatch($router, 'GET', '/pagina-que-nao-existe');
        $removedExample = $this->dispatch($router, 'POST', '/example');

        restore_error_handler();
        restore_exception_handler();

        self::assertSame(200, $home->status());
        self::assertSame(200, $menu->status());
        self::assertStringContainsString('Buscar no cardápio', $menu->body());
        self::assertSame(200, $product->status());
        self::assertStringContainsString('Detalhes do Produto', $product->body());
        self::assertStringContainsString('R$ 62,00', $product->body());
        self::assertStringContainsString('R$ 75,00', $product->body());
        self::assertSame(200, $order->status());
        self::assertStringContainsString('Meu Pedido', $order->body());
        self::assertStringContainsString('Finalizar pedido no WhatsApp', $order->body());
        self::assertSame(200, $health->status());
        self::assertSame('{"status":"ok"}', $health->body());
        self::assertSame(404, $unknownProduct->status());
        self::assertSame(404, $missing->status());
        self::assertStringContainsString('Página não encontrada', $missing->body());
        self::assertSame(404, $removedExample->status());
    }

    public function testNotFoundPageEscapesRequestedPath(): void
    {
        $root = dirname(__DIR__);
        $view = new View($root . '/resources/views');
        $html = $view->render('pages/404', [
            'title' => 'Página não encontrada',
            'path' => '/<script>alert(1)</script>',
        ]);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testMockMenuHasEveryPublicCategoryAndLocalImages(): void
    {
        $menu = require dirname(__DIR__) . '/config/menu.php';
        $categoriesWithProducts = array_unique(array_column($menu['products'], 'category'));

        self::assertEqualsCanonicalizing(array_keys($menu['categories']), $categoriesWithProducts);
        foreach ($menu['products'] as $product) {
            self::assertStringStartsWith('/assets/images/', $product['image']);
            self::assertNotEmpty($product['variants']);
        }
    }

    private function dispatch(object $router, string $method, string $uri): object
    {
        return $router->dispatch(new Request(server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ]));
    }
}
