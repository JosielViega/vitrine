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
use App\Repositories\StorefrontProductImageRepositoryInterface;
use App\Repositories\StorefrontOperationsRepositoryInterface;
use App\Services\AdminAuthService;
use App\Services\BusinessHoursService;
use App\Services\ProductImageProcessor;
use App\Services\ProductImageService;
use App\Services\ProductImageStorage;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontOperationsService;
use App\Services\StorefrontProductGroupingService;
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

    public function testImageUploadWithoutAuthenticationIsBlocked(): void
    {
        $response = $this->dispatch($this->request('POST', '/admin/images/upload'));
        self::assertSame(303, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testImageUploadWithInvalidCsrfIsBlocked(): void
    {
        $this->authenticateSession();
        $response = $this->dispatch($this->request('POST', '/admin/images/upload', ['_token' => 'invalid', 'group_id' => 'product-79']));
        self::assertSame(403, $response->status());
    }

    public function testImageUploadWithInvalidGroupIsRejectedServerSide(): void
    {
        $this->authenticateSession();
        $session = new Session(false);
        $csrf = new Csrf($session);
        $response = $this->dispatch($this->request('POST', '/admin/images/upload', ['_token' => $csrf->token(), 'group_id' => 'product-999']), $session, $csrf);
        self::assertSame(303, $response->status());
        self::assertSame('/admin?section=images', $response->headers()['Location']);
    }

    public function testImageRemoveWithoutAuthenticationIsBlocked(): void
    {
        $response = $this->dispatch($this->request('POST', '/admin/images/remove'));
        self::assertSame(303, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testImageRemoveWithInvalidCsrfIsBlocked(): void
    {
        $this->authenticateSession();
        $response = $this->dispatch($this->request('POST', '/admin/images/remove', ['_token' => 'invalid', 'group_id' => 'product-79']));
        self::assertSame(403, $response->status());
    }

    public function testImageRemoveWithInvalidGroupIsRejectedServerSide(): void
    {
        $this->authenticateSession();
        $session = new Session(false);
        $csrf = new Csrf($session);
        $response = $this->dispatch($this->request('POST', '/admin/images/remove', ['_token' => $csrf->token(), 'group_id' => 'product-999']), $session, $csrf);
        self::assertSame(303, $response->status());
        self::assertSame('/admin?section=images', $response->headers()['Location']);
    }
    public function testOperationsTabRendersWithoutNumericCounterAndEscapesPreview(): void
    {
        $this->authenticateSession();
        $request = new Request(queryParams: ['section' => 'operations'], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin?section=operations']);
        $response = $this->dispatch($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Funcionamento da vitrine', $response->body());
        self::assertStringContainsString('Horário de funcionamento', $response->body());
        self::assertStringContainsString('America/Sao_Paulo', $response->body());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringNotContainsString('Funcionamento <span>', $response->body());
    }

    public function testOperationsPostsRequireAuthenticationAndCsrf(): void
    {
        $unauthenticated = $this->dispatch($this->request('POST', '/admin/operations/notice'));
        self::assertSame(303, $unauthenticated->status());
        self::assertSame('/admin/login', $unauthenticated->headers()['Location']);

        $this->authenticateSession();
        $invalidCsrf = $this->dispatch($this->request('POST', '/admin/operations/hours', ['_token' => 'invalid']));
        self::assertSame(403, $invalidCsrf->status());
        self::assertStringContainsString('no-store', $invalidCsrf->headers()['Cache-Control']);
    }

    public function testValidNoticeAndHoursUsePrgRedirect(): void
    {
        $this->authenticateSession();
        $session = new Session(false);
        $csrf = new Csrf($session);

        $notice = $this->dispatch($this->request('POST', '/admin/operations/notice', [
            '_token' => $csrf->token(),
            'notice_enabled' => '1',
            'notice_title' => 'Aviso',
            'notice_message' => 'Mensagem',
        ]), $session, $csrf);
        self::assertSame(303, $notice->status());
        self::assertSame('/admin?section=operations', $notice->headers()['Location']);

        $body = ['_token' => $csrf->token()];
        foreach ([4, 5, 6] as $day) {
            $body['day_' . $day . '_enabled'] = '1';
            $body['day_' . $day . '_open'] = '17:00';
            $body['day_' . $day . '_close'] = '21:30';
        }
        $hours = $this->dispatch($this->request('POST', '/admin/operations/hours', $body), $session, $csrf);
        self::assertSame(303, $hours->status());
        self::assertSame('/admin?section=operations', $hours->headers()['Location']);
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
        $operations = new StorefrontOperationsService(new AdminRouteOperationsRepository());
        $logger = new Logger(sys_get_temp_dir() . '/vitrine-admin-route-test-logs');
        $productImages = new ProductImageService(new AdminRouteImageRepository(), new StorefrontProductGroupingService(), new ProductImageProcessor(), new ProductImageStorage(sys_get_temp_dir() . '/vitrine-admin-route-public'), ['fallback_image' => '/fallback.jpg'], $catalog);
        $app = [
            'request' => $request,
            'view' => new View($root . '/resources/views'),
            'csrf' => $csrf,
            'session' => $session,
            'adminAuth' => $auth,
            'storefrontVisibility' => $visibility,
            'productImages' => $productImages,
            'catalog' => $catalog,
            'businessHours' => $businessHours,
            'storefrontOperations' => $operations,
            'whatsappCheckout' => new WhatsAppCheckoutService($businessHours, $catalog, ['number' => '5527998586163'], $logger, $operations),
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
final class AdminRouteImageRepository implements StorefrontProductImageRepositoryInterface
{
    public function administrativeProducts(): array { return []; }
    public function findById(int $imageId): ?array { return null; }
    public function productIdsForImage(int $imageId): array { return []; }
    public function replaceGroupImage(array $metadata, array $productIds): array { return ['image_id' => 1, 'previous_images' => []]; }
    public function removeGroupAssociations(array $productIds): array { return []; }
    public function deleteImageIfUnlinked(int $imageId): bool { return true; }
}
final class AdminRouteOperationsRepository implements StorefrontOperationsRepositoryInterface
{
    private array $settings = ['notice_enabled' => 0, 'notice_title' => '<script>alert(1)</script>', 'notice_message' => '<b>Mensagem</b>'];
    private array $hours = [];

    public function __construct()
    {
        for ($day = 1; $day <= 7; $day++) {
            $enabled = in_array($day, [4, 5, 6], true);
            $this->hours[] = ['weekday' => $day, 'enabled' => $enabled ? 1 : 0, 'open_time' => $enabled ? '17:00:00' : null, 'close_time' => $enabled ? '21:30:00' : null];
        }
    }

    public function settings(): array { return $this->settings; }
    public function businessHours(): array { return $this->hours; }
    public function updateNotice(bool $enabled, string $title, string $message): void
    {
        $this->settings = ['notice_enabled' => $enabled ? 1 : 0, 'notice_title' => $title, 'notice_message' => $message];
    }
    public function updateBusinessHours(array $schedule): void {}
}
