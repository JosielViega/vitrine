<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Environment;
use Dotenv\Exception\InvalidEncodingException;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private string $directory;
    private string $key;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vitrine-dotenv-' . bin2hex(random_bytes(6));
        $this->key = 'VITRINE_UTF8_TEST_' . strtoupper(bin2hex(random_bytes(4)));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        unset($_ENV[$this->key], $_SERVER[$this->key]);
        if (is_file($this->directory . '/.env')) {
            unlink($this->directory . '/.env');
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testLoadsUtf8FixtureExplicitlyWithoutWritingSensitiveOutput(): void
    {
        $value = 'São Jorge – café';
        file_put_contents($this->directory . '/.env', $this->key . '="' . $value . '"' . PHP_EOL);

        ob_start();
        $loaded = Environment::load($this->directory);
        $output = ob_get_clean();

        self::assertSame('UTF-8', Environment::FILE_ENCODING);
        self::assertSame($value, $loaded[$this->key]);
        self::assertSame($value, $_ENV[$this->key]);
        self::assertSame('', $output);
    }

    public function testAllVersionedEntryPointsUseTheProjectEnvironmentLoader(): void
    {
        $root = dirname(__DIR__);
        foreach ([
            'bootstrap/app.php',
            'bin/serve.php',
            'bin/apply-storefront-visibility.php',
            'bin/apply-storefront-images-schema.php',
            'bin/migrate.php',
        ] as $file) {
            $source = file_get_contents($root . '/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('Environment::load(', $source, $file);
            self::assertStringNotContainsString('Dotenv::create', $source, $file);
        }
    }

    public function testRejectsAnEnvironmentFileThatIsNotUtf8(): void
    {
        file_put_contents($this->directory . '/.env', $this->key . "=\xC3\x28" . PHP_EOL);

        $this->expectException(InvalidEncodingException::class);

        Environment::load($this->directory);
    }

    public function testProjectLoaderValidatesUtf8BeforePassingContentsToDotenv(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/app/Core/Environment.php');

        self::assertIsString($source);
        self::assertStringContainsString('mb_check_encoding($contents, self::FILE_ENCODING)', $source);
        self::assertStringContainsString('new StringStore($contents)', $source);
    }
}