<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminAuthRepositoryInterface;
use App\Repositories\StorefrontCatalogRepository;
use App\Services\AdminAuthService;
use App\Services\BusinessHoursService;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontVisibilityService;
use App\Services\WhatsAppCheckoutService;
use PHPUnit\Framework\TestCase;

final class AdminRouteTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAdminWithoutLoginRedirectsToLogin(): void
    {
        $response = $this->dispatch($this->request('GET', '/admin'));

        self::assertSame(303, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertStringContainsString('no-store', $response->headers()['Cache-Control']);
    }

    public function testLoginPageWithoutLoginReturnsSuccess(): void
    {
        $response = $this->dispatch($this->request('GET', '/admin/login'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Admin da Vitrine', $response->body());
        self::assertStringNotContainsString('bottom-nav', $response->body());
    }

    public function testLoginPageRedirectsAuthenticatedUserToAdmin(): void
    {
        $this->authenticateSession();
        $response = $this->dispatch($this->request('GET', '/admin/login'));

        self::assertSame(303, $response->status());
        self::assertSame('/admin', $response->headers()['Location']);
    }

    public function testLoginWithInvalidCsrfReturnsForbidden(): void
    {
        $response = $this->dispatch($this->request('POST', '/admin/login', [
            '_token' => 'invalid',
            'username' => 'manager',
            'password' => 'correct-password',
        ]));

        self::assertSame(403, $response->status());
    }

    public function testVisibilityWithoutAuthenticationIsBlocked(): void
    {
        $response = $this->dispatch($this->request('POST', '/admin/visibility', [
            '_token' => 'anything', 'type' => 'product', 'id' => '1', 'visible' => '0',
        ]));

        self::assertSame(303, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testVisibilityWithInvalidCsrfReturnsForbidden(): void
    {
        $this->authenticateSession();
        $response = $this->dispatch($this->request('POST', '/admin/visibility', [
            '_token' => 'invalid', 'type' => 'product', 'id' => '1', 'visible' => '0',
        ]));

        self::assertSame(403, $response->status());
    }

    public function testLogoutRemovesAdminSession(): void
    {
        $this->authenticateSession();
        $session = new Session(false);
        $csrf = new Csrf($session);
        $response = $this->dispatch($this->request('POST', '/admin/logout', ['_token' => $csrf->token()]), $session, $csrf);

        self::assertSame(303, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertArrayNotHasKey('admin_authenticated', $_SESSION);
        self::assertArrayNotHasKey('admin_user_id', $_SESSION);
        self::assertArrayNotHasKey('admin_username', $_SESSION);
    }

    private function request(string $method, string $uri, array $body = []): Request
    {
        return new Request(parsedBody: $body, server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
    }

    private function dispatch(Request $request, ?Session $session = null, ?Csrf $csrf = null): object
    {
        $root = dirname(__DIR__);
        $session ??= new Session(false);
        $csrf ??= new Csrf($session);
        $auth = new AdminAuthService(new AdminRouteAuthRepository(), $session);
        $visibility = new StorefrontVisibilityService(new StorefrontVisibilityFakeRepository());
        $catalog = new StorefrontCatalogService(new class implements StorefrontCatalogRepository {
            public function activeCategories(): array { return []; }
            public function activeSubcategories(): array { return []; }
            public function activeProducts(): array { return []; }
        }, ['fallback_image' => '/fallback.jpg']);
        $businessHours = new BusinessHoursService(require $root . '/config/business.php');
        $logger = new Logger(sys_get_temp_dir() . '/vitrine-admin-route-test-logs');
        $app = [
            'request' => $request,
            'view' => new View($root . '/resources/views'),
            'csrf' => $csrf,
            'session' => $session,
            'adminAuth' => $auth,
            'storefrontVisibility' => $visibility,
            'catalog' => $catalog,
            'businessHours' => $businessHours,
            'whatsappCheckout' => new WhatsAppCheckoutService($businessHours, $catalog, ['number' => '5527998586163'], $logger),
            'logger' => $logger,
            'router' => new Router(),
        ];
        $router = require $root . '/routes/web.php';

        return $router->dispatch($request);
    }

    private function authenticateSession(): void
    {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user_id'] = 7;
        $_SESSION['admin_username'] = 'manager';
    }
}

final class AdminRouteAuthRepository implements AdminAuthRepositoryInterface
{
    public function findByUsername(string $username): ?array
    {
        return $username === 'manager'
            ? ['id' => 7, 'username' => 'manager', 'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT)]
            : null;
    }
}
