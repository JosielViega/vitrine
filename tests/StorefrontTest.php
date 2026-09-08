<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class StorefrontTest extends TestCase
{
    public function testHomeRendersStorefrontAndGroupedVariants(): void
    {
        $root = dirname(__DIR__);
        $menu = require $root . '/config/menu.php';
        $controller = new HomeController(new View($root . '/resources/views'), $menu);

        $response = $controller->index();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Bar e Lanchonete', $response->body());
        self::assertStringContainsString('Camarão c/ Batata ou Aipim', $response->body());
        self::assertStringContainsString('R$ 62,00', $response->body());
        self::assertStringContainsString('R$ 75,00', $response->body());
        self::assertStringContainsString('Finalizar pelo WhatsApp', $response->body());
        self::assertStringNotContainsString('Reusable PHP starter', $response->body());
        self::assertStringNotContainsString('Protected POST example', $response->body());
    }

    public function testPublicRoutesAndRemovedExampleRoute(): void
    {
        $root = dirname(__DIR__);
        $app = require $root . '/bootstrap/app.php';
        $router = require $root . '/routes/web.php';

        $home = $router->dispatch(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]));
        $health = $router->dispatch(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/health',
        ]));
        $missing = $router->dispatch(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/pagina-que-nao-existe',
        ]));
        $removedExample = $router->dispatch(new Request(server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/example',
        ]));

        restore_error_handler();
        restore_exception_handler();

        self::assertSame(200, $home->status());
        self::assertStringContainsString('Nosso cardápio', $home->body());
        self::assertSame(200, $health->status());
        self::assertSame('{"status":"ok"}', $health->body());
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

    public function testMockMenuHasEveryPublicCategory(): void
    {
        $menu = require dirname(__DIR__) . '/config/menu.php';
        $categoriesWithProducts = array_unique(array_column($menu['products'], 'category'));

        self::assertEqualsCanonicalizing(array_keys($menu['categories']), $categoriesWithProducts);
    }
}
