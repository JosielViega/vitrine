<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Environment;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Environment::load($root);

$databaseConfig = require $root . '/config/database.php';
$storefrontConfig = require $root . '/config/storefront.php';

try {
    if ((string) $databaseConfig['database'] === '') {
        throw new RuntimeException('Database name is not configured.');
    }
    $pdo = (new Database($databaseConfig))->connection();
    $sql = file_get_contents($root . '/database/patches/004_add_storefront_home_highlights.sql');
    if (!is_string($sql)) {
        throw new RuntimeException('Storefront home highlights schema file could not be read.');
    }
    $pdo->exec($sql);

    $popular = array_values((array) ($storefrontConfig['popular'] ?? []));
    $seed = $pdo->prepare(
        'INSERT INTO storefront_home_highlights '
        . '(id, featured_product_slug, popular_product_1_slug, popular_product_2_slug) '
        . 'VALUES (1, :featured, :popular1, :popular2) ON DUPLICATE KEY UPDATE id = id',
    );
    $seed->execute([
        'featured' => (string) ($storefrontConfig['featured'] ?? ''),
        'popular1' => isset($popular[0]) ? (string) $popular[0] : null,
        'popular2' => isset($popular[1]) ? (string) $popular[1] : null,
    ]);

    $verify = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    );
    $verify->execute(['schema' => $databaseConfig['database'], 'table' => 'storefront_home_highlights']);
    if ((int) $verify->fetchColumn() !== 1) {
        throw new RuntimeException('storefront_home_highlights: table verification failed.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM storefront_home_highlights WHERE id = 1')->fetchColumn() !== 1) {
        throw new RuntimeException('storefront_home_highlights: seed verification failed.');
    }

    fwrite(STDOUT, 'storefront_home_highlights: ready' . PHP_EOL);
    fwrite(STDOUT, 'storefront home highlights seed: ready' . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
