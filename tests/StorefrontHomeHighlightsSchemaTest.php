<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class StorefrontHomeHighlightsSchemaTest extends TestCase
{
    public function testPatchCreatesCanonicalHomeHighlightsTable(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/database/patches/004_add_storefront_home_highlights.sql');

        self::assertSame(1, substr_count(strtoupper($sql), 'CREATE TABLE IF NOT EXISTS'));
        self::assertStringContainsString('storefront_home_highlights', $sql);
        self::assertStringContainsString('featured_product_slug VARCHAR(255) NOT NULL', $sql);
        self::assertStringContainsString('popular_product_1_slug VARCHAR(255) NULL', $sql);
        self::assertStringContainsString('popular_product_2_slug VARCHAR(255) NULL', $sql);
        self::assertStringNotContainsString('FOREIGN KEY', strtoupper($sql));
    }

    public function testApplicatorUsesConfigSeedAndDoesNotOverwriteExistingRow(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/bin/apply-storefront-home-schema.php');
        $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertStringContainsString("require \$root . '/config/storefront.php'", $script);
        self::assertStringContainsString("ON DUPLICATE KEY UPDATE id = id", $script);
        self::assertStringContainsString("'featured' => (string) (\$storefrontConfig['featured']", $script);
        self::assertStringContainsString("'popular1' => isset(\$popular[0])", $script);
        self::assertStringContainsString("'popular2' => isset(\$popular[1])", $script);
        self::assertStringContainsString("WHERE id = 1", $script);
        self::assertSame('@php bin/apply-storefront-home-schema.php', $composer['scripts']['storefront:home-schema']);
    }
}
