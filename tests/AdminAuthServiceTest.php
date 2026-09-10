<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Session;
use App\Repositories\AdminAuthRepositoryInterface;
use App\Services\AdminAuthService;
use PHPUnit\Framework\TestCase;

final class AdminAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testValidExistingUserCanAuthenticate(): void
    {
        $service = $this->service();

        self::assertSame(AdminAuthService::LOGIN_SUCCESS, $service->attemptLogin('manager', 'correct-password'));
        self::assertTrue($service->check());
        self::assertSame(7, $service->userId());
        self::assertSame('manager', $service->username());
    }

    public function testValidPasswordIsVerifiedAgainstStoredHash(): void
    {
        self::assertSame(AdminAuthService::LOGIN_SUCCESS, $this->service()->attemptLogin('manager', 'correct-password'));
    }

    public function testWrongPasswordFailsWithGenericResult(): void
    {
        self::assertSame(AdminAuthService::LOGIN_INVALID, $this->service()->attemptLogin('manager', 'wrong'));
        self::assertFalse($this->service()->check());
    }

    public function testMissingUserFailsWithTheSameGenericResult(): void
    {
        self::assertSame(AdminAuthService::LOGIN_INVALID, $this->service()->attemptLogin('missing', 'wrong'));
    }

    public function testSuccessfulLoginStoresOnlyMinimumIdentityData(): void
    {
        $this->service()->attemptLogin('manager', 'correct-password');

        self::assertTrue($_SESSION['admin_authenticated']);
        self::assertSame(7, $_SESSION['admin_user_id']);
        self::assertSame('manager', $_SESSION['admin_username']);
    }

    public function testSuccessfulLoginRegeneratesSessionId(): void
    {
        $regenerations = 0;
        $session = new Session(false, static function () use (&$regenerations): bool {
            $regenerations++;
            return true;
        });

        $this->service($session)->attemptLogin('manager', 'correct-password');

        self::assertSame(1, $regenerations);
    }

    public function testLogoutRemovesOnlyAdminStateAndRegeneratesSession(): void
    {
        $regenerations = 0;
        $session = new Session(false, static function () use (&$regenerations): bool {
            $regenerations++;
            return true;
        });
        $session->put('cart', ['preserved']);
        $service = $this->service($session);
        $service->attemptLogin('manager', 'correct-password');

        $service->logout();

        self::assertFalse($service->check());
        self::assertSame(['preserved'], $session->get('cart'));
        self::assertSame(2, $regenerations);
    }

    public function testPasswordAndHashAreNeverStoredInSession(): void
    {
        $this->service()->attemptLogin('manager', 'correct-password');

        self::assertArrayNotHasKey('password', $_SESSION);
        self::assertArrayNotHasKey('password_hash', $_SESSION);
        self::assertStringNotContainsString('correct-password', serialize($_SESSION));
        self::assertStringNotContainsString('$2y$', serialize($_SESSION));
    }

    public function testFiveInvalidAttemptsTemporarilyBlockFurtherAttempts(): void
    {
        $repository = new AdminAuthFakeRepository();
        $service = $this->service(repository: $repository);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(AdminAuthService::LOGIN_INVALID, $service->attemptLogin('manager', 'wrong'));
        }

        self::assertSame(AdminAuthService::LOGIN_RATE_LIMITED, $service->attemptLogin('manager', 'correct-password'));
        self::assertSame(5, $repository->lookups);
    }

    public function testSuccessfulLoginClearsEarlierFailedAttempts(): void
    {
        $service = $this->service();
        $service->attemptLogin('manager', 'wrong');
        $service->attemptLogin('manager', 'wrong');

        self::assertSame(AdminAuthService::LOGIN_SUCCESS, $service->attemptLogin('manager', 'correct-password'));
        self::assertArrayNotHasKey('admin_login_attempts', $_SESSION);
    }

    private function service(
        ?Session $session = null,
        ?AdminAuthFakeRepository $repository = null,
    ): AdminAuthService {
        return new AdminAuthService(
            $repository ?? new AdminAuthFakeRepository(),
            $session ?? new Session(false),
            static fn (): int => 1_000_000,
        );
    }
}

final class AdminAuthFakeRepository implements AdminAuthRepositoryInterface
{
    public int $lookups = 0;

    public function findByUsername(string $username): ?array
    {
        $this->lookups++;
        if ($username !== 'manager') {
            return null;
        }

        return ['id' => 7, 'username' => 'manager', 'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT)];
    }
}
