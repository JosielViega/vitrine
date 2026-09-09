<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Repositories\StorefrontCatalogRepository;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\WhatsAppCheckoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class StorefrontTest extends TestCase
{
    public function testPublicRoutesUseFixtureCatalogWithoutDatabase(): void
    {
        $router = $this->routerAt('2026-09-10 18:00:00');

        $home = $this->dispatch($router, 'GET', '/');
        $menu = $this->dispatch($router, 'GET', '/cardapio');
        $product = $this->dispatch($router, 'GET', '/produto/camarao');
        $order = $this->dispatch($router, 'GET', '/pedido');
        $health = $this->dispatch($router, 'GET', '/health');
        $unknown = $this->dispatch($router, 'GET', '/produto/inativo');

        self::assertSame(200, $home->status());
        self::assertStringContainsString('Destaque da casa', $home->body());
        self::assertStringContainsString('/assets/images/brand/logo-sao-jorge.png', $home->body());
        self::assertStringContainsString('Aberto agora', $home->body());
        self::assertStringContainsString('Hoje até 21h30', $home->body());
        self::assertSame(200, $menu->status());
        self::assertStringContainsString('Buscar no cardápio', $menu->body());
        self::assertSame(200, $product->status());
        self::assertStringContainsString('R$ 75,00', $product->body());
        self::assertStringContainsString('R$ 62,00', $product->body());
        self::assertStringContainsString('value="10"', $product->body());
        self::assertStringContainsString('value="11"', $product->body());
        self::assertStringContainsString('Adicionar ao pedido', $product->body());
        self::assertSame(200, $order->status());
        self::assertStringContainsString('data-business-open="true"', $order->body());
        self::assertStringContainsString('Pedidos até 21h30', $order->body());
        self::assertStringContainsString('data-checkout-token', $order->body());
        self::assertStringContainsString('value="pickup"', $order->body());
        self::assertStringContainsString('value="dine_in"', $order->body());
        self::assertSame('{"status":"ok"}', $health->body());
        self::assertSame(404, $unknown->status());
    }

    public function testClosedHoursOnlyDisableCheckout(): void
    {
        $router = $this->routerAt('2026-09-10 21:30:00');

        $home = $this->dispatch($router, 'GET', '/');
        $menu = $this->dispatch($router, 'GET', '/cardapio');
        $product = $this->dispatch($router, 'GET', '/produto/camarao');
        $order = $this->dispatch($router, 'GET', '/pedido');

        self::assertStringContainsString('Fechado agora', $home->body());
        self::assertStringContainsString('Abrimos sexta-feira às 17h', $home->body());
        self::assertSame(200, $menu->status());
        self::assertSame(200, $product->status());
        self::assertStringContainsString('Adicionar ao pedido', $product->body());
        self::assertStringContainsString('data-business-open="false"', $order->body());
        self::assertStringContainsString('data-checkout disabled', $order->body());
        self::assertStringContainsString('Pedidos pelo WhatsApp estão disponíveis durante nosso horário de atendimento.', $order->body());
        self::assertStringContainsString('Abrimos sexta-feira às 17h', $order->body());
    }

    public function testVersionedCartContractUsesServerBusinessStatus(): void
    {
        $javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("const CART_KEY = 'saoJorgeCartV2'", $javascript);
        self::assertStringContainsString('productId', $javascript);
        self::assertStringContainsString('publicProductId', $javascript);
        self::assertStringContainsString('priceCents', $javascript);
        self::assertStringContainsString("screen.dataset.businessOpen === 'true'", $javascript);
        self::assertStringNotContainsString('new Date(', $javascript);
        self::assertStringNotContainsString('.getDay(', $javascript);
        self::assertStringNotContainsString("const CART_KEY = 'saoJorgeCart'", $javascript);
    }

    public function testNotFoundEscapesRequestedPath(): void
    {
        $html = (new View(dirname(__DIR__) . '/resources/views'))->render('pages/404', ['title' => 'Página não encontrada', 'path' => '/<script>alert(1)</script>']);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    private function routerAt(string $dateTime): Router
    {
        $root = dirname(__DIR__);
        $now = new DateTimeImmutable($dateTime, new DateTimeZone('America/Sao_Paulo'));
        $businessHours = new BusinessHoursService(require $root . '/config/business.php', static fn (): DateTimeImmutable => $now);
        $session = new Session(false);
        $csrf = new Csrf($session);
        $catalog = $this->catalog();
        $logger = new Logger(sys_get_temp_dir() . '/vitrine-test-logs');
        $checkout = new WhatsAppCheckoutService($businessHours, $catalog, ['number' => '5527998586163']);
        $app = ['view' => new View($root . '/resources/views'), 'catalog' => $catalog, 'businessHours' => $businessHours, 'whatsappCheckout' => $checkout, 'request' => new Request(), 'csrf' => $csrf, 'logger' => $logger, 'router' => new Router()];

        return require $root . '/routes/web.php';
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
