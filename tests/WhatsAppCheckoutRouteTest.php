<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Repositories\StorefrontCatalogRepository;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\WhatsAppCheckoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class WhatsAppCheckoutRouteTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testSuccessfulCheckoutRouteReturnsWhatsAppUrl(): void
    {
        $response = $this->responseAt('2026-09-10 18:00:00', $this->validBody());
        $data = json_decode($response->body(), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->status());
        self::assertTrue($data['ok']);
        self::assertStringStartsWith('https://wa.me/5527998586163?text=', $data['whatsapp_url']);
    }

    public function testClosedCheckoutRouteReturnsConflict(): void
    {
        $response = $this->responseAt('2026-09-10 21:30:00', $this->validBody());
        $data = json_decode($response->body(), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame(409, $response->status());
        self::assertSame('business_closed', $data['code']);
        self::assertArrayNotHasKey('whatsapp_url', $data);
    }

    public function testInvalidCartRouteReturnsClientError(): void
    {
        $body = $this->validBody();
        $body['cart'] = '{}';
        $response = $this->responseAt('2026-09-10 18:00:00', $body);
        $data = json_decode($response->body(), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->status());
        self::assertSame('cart_invalid', $data['code']);
    }

    public function testInvalidCsrfRouteReturnsForbidden(): void
    {
        $response = $this->responseAt('2026-09-10 18:00:00', $this->validBody(), false);
        $data = json_decode($response->body(), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame(403, $response->status());
        self::assertSame('invalid_csrf', $data['code']);
    }

    private function responseAt(string $dateTime, array $body, bool $validCsrf = true): object
    {
        $root = dirname(__DIR__);
        $session = new Session(false);
        $csrf = new Csrf($session);
        $body['_token'] = $validCsrf ? $csrf->token() : 'invalid';
        $request = new Request(parsedBody: $body, server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/checkout/whatsapp']);
        $now = new DateTimeImmutable($dateTime, new DateTimeZone('America/Sao_Paulo'));
        $businessHours = new BusinessHoursService(require $root . '/config/business.php', static fn (): DateTimeImmutable => $now);
        $catalog = $this->catalog();
        $logger = new Logger(sys_get_temp_dir() . '/vitrine-checkout-route-test-logs');
        $checkout = new WhatsAppCheckoutService($businessHours, $catalog, ['number' => '5527998586163'], $logger);
        $app = ['view' => new View($root . '/resources/views'), 'catalog' => $catalog, 'businessHours' => $businessHours, 'whatsappCheckout' => $checkout, 'request' => $request, 'csrf' => $csrf, 'logger' => $logger, 'router' => new Router()];
        $router = require $root . '/routes/web.php';

        return $router->dispatch($request);
    }

    private function validBody(): array
    {
        return [
            'cart' => json_encode([['productId' => 82, 'priceCents' => 7200, 'quantity' => 1, 'notes' => '']], JSON_THROW_ON_ERROR),
            'service_type' => 'pickup',
        ];
    }

    private function catalog(): StorefrontCatalogService
    {
        $categories = [['id' => 1, 'name' => 'Comidas', 'sort_order' => 0, 'active' => 1]];
        $subcategories = [['id' => 1, 'category_id' => 1, 'name' => 'Porções', 'sort_order' => 0, 'active' => 1]];
        $products = [
            ['id' => 79, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Camarão c/ Batata e Aipim', 'price_cents' => 8500, 'kind' => 'kitchen', 'active' => 1],
            ['id' => 82, 'subcategory_id' => 1, 'category_id' => 1, 'name' => 'Meia: Camarão c/ Batata e Aipim', 'price_cents' => 7200, 'kind' => 'kitchen', 'active' => 1],
        ];
        $repository = new class($categories, $subcategories, $products) implements StorefrontCatalogRepository {
            public function __construct(private array $categories, private array $subcategories, private array $products) {}
            public function activeCategories(): array { return $this->categories; }
            public function activeSubcategories(): array { return $this->subcategories; }
            public function activeProducts(): array { return $this->products; }
        };

        return new StorefrontCatalogService($repository, ['fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg']);
    }
}
