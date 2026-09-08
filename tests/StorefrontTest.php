<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\HomeController;
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
    }

    public function testMockMenuHasEveryPublicCategory(): void
    {
        $menu = require dirname(__DIR__) . '/config/menu.php';
        $categoriesWithProducts = array_unique(array_column($menu['products'], 'category'));

        self::assertEqualsCanonicalizing(array_keys($menu['categories']), $categoriesWithProducts);
    }
}
