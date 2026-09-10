<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class AdminSecurityTest extends TestCase
{
    public function testAdminUsersRepositoryIsReadOnlyAndUsesPreparedLookup(): void
    {
        $source = $this->source('app/Repositories/AdminAuthRepository.php');

        self::assertStringContainsString('prepare(', $source);
        self::assertStringContainsString('WHERE username = :username', $source);
        self::assertDoesNotMatchRegularExpression('/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?admin_users\b/i', $source);
    }

    public function testVisibilityRepositoryHasOnlyThreeAllowlistedUpdates(): void
    {
        $source = $this->source('app/Repositories/StorefrontVisibilityRepository.php');
        preg_match_all('/UPDATE\s+(categories|subcategories|products)\s+SET\s+storefront_visible\s*=\s*:visible\s+WHERE\s+id\s*=\s*:id/i', $source, $matches);

        self::assertSame(['categories', 'subcategories', 'products'], $matches[1]);
        self::assertSame(3, substr_count($source, "'UPDATE "));
        self::assertDoesNotMatchRegularExpression('/UPDATE[^\r\n]+\bactive\s*=/i', $source);
    }

    public function testAdminDoesNotUseRememberTokensOrEnvironmentPassword(): void
    {
        $sources = implode("\n", [
            $this->source('app/Repositories/AdminAuthRepository.php'),
            $this->source('app/Services/AdminAuthService.php'),
            $this->source('app/Controllers/AdminAuthController.php'),
        ]);

        self::assertStringNotContainsString('admin_remember_tokens', $sources);
        self::assertStringNotContainsString('ADMIN_PASSWORD', $sources);
    }

    public function testPasswordHashNeverAppearsInViews(): void
    {
        $views = $this->source('resources/views/admin/login.php') . $this->source('resources/views/admin/index.php');

        self::assertStringNotContainsString('password_hash', $views);
    }

    public function testLogoutAndVisibilityArePostRoutes(): void
    {
        $routes = $this->source('routes/web.php');

        self::assertStringContainsString("post('/admin/logout'", $routes);
        self::assertStringContainsString("post('/admin/visibility'", $routes);
        self::assertStringNotContainsString("get('/admin/logout'", $routes);
        self::assertStringNotContainsString("get('/admin/visibility'", $routes);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
        self::assertIsString($source);

        return $source;
    }
}
