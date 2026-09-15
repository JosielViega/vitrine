<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class StorefrontOperationsSchemaTest extends TestCase
{
    public function testDedicatedPatchCreatesOnlyTheTwoStorefrontTables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/database/patches/003_add_storefront_operations.sql');

        self::assertSame(2, substr_count(strtoupper($sql), 'CREATE TABLE IF NOT EXISTS'));
        self::assertStringContainsString('storefront_settings', $sql);
        self::assertStringContainsString('storefront_business_hours', $sql);
        self::assertStringNotContainsString('CREATE TABLE migrations', $sql);
        self::assertStringNotContainsString('admin_users', $sql);
        self::assertStringNotContainsString('products', $sql);
    }

    public function testApplicatorSeedsMissingRowsWithoutOverwritingExistingValues(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/bin/apply-storefront-operations-schema.php');
        $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertStringContainsString("ON DUPLICATE KEY UPDATE id = id", $script);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE weekday = weekday', $script);
        self::assertStringContainsString("for (\$weekday = 1; \$weekday <= 7; \$weekday++)", $script);
        self::assertStringContainsString("require \$root . '/config/business.php'", $script);
        self::assertSame('@php bin/apply-storefront-operations-schema.php', $composer['scripts']['storefront:operations-schema']);
    }
}
