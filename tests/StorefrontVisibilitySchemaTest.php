<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class StorefrontVisibilitySchemaTest extends TestCase
{
    public function testPatchContainsOnlyTheThreeExpectedColumnAdditions(): void
    {
        $sql = file_get_contents(dirname(__DIR__) . '/database/patches/001_add_storefront_visibility.sql');

        self::assertIsString($sql);
        self::assertSame(3, substr_count($sql, 'ADD COLUMN storefront_visible TINYINT(1) NOT NULL DEFAULT 1'));
        self::assertSame(3, substr_count($sql, 'ALTER TABLE'));
        self::assertStringNotContainsString('CREATE TABLE', strtoupper($sql));
        self::assertStringNotContainsString('UPDATE ', strtoupper($sql));
    }

    public function testDedicatedCommandIsIdempotentAndDoesNotUseMigrationRunner(): void
    {
        $script = file_get_contents(dirname(__DIR__) . '/bin/apply-storefront-visibility.php');
        $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($script);
        self::assertStringContainsString('information_schema.COLUMNS', $script);
        self::assertStringContainsString(': already exists', $script);
        self::assertStringContainsString('ADD COLUMN `%s` TINYINT(1) NOT NULL DEFAULT 1', $script);
        self::assertStringNotContainsString('CREATE TABLE', strtoupper($script));
        self::assertStringNotContainsString('migrations', $script);
        self::assertSame('@php bin/apply-storefront-visibility.php', $composer['scripts']['storefront:visibility-schema']);
    }
}
