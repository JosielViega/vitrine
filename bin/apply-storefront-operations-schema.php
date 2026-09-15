<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Environment;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Environment::load($root);

$databaseConfig = require $root . '/config/database.php';
$businessConfig = require $root . '/config/business.php';

try {
    if ((string) $databaseConfig['database'] === '') {
        throw new RuntimeException('Database name is not configured.');
    }
    $pdo = (new Database($databaseConfig))->connection();
    $sql = file_get_contents($root . '/database/patches/003_add_storefront_operations.sql');
    if (!is_string($sql)) {
        throw new RuntimeException('Storefront operations schema file could not be read.');
    }
    $pdo->exec($sql);

    $settings = $pdo->prepare(
        "INSERT INTO storefront_settings (id, notice_enabled, notice_title, notice_message) VALUES (1, 0, '', '') "
        . 'ON DUPLICATE KEY UPDATE id = id',
    );
    $settings->execute();

    $insertDay = $pdo->prepare(
        'INSERT INTO storefront_business_hours (weekday, enabled, open_time, close_time) '
        . 'VALUES (:weekday, :enabled, :open_time, :close_time) ON DUPLICATE KEY UPDATE weekday = weekday',
    );
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $period = $businessConfig['schedule'][$weekday][0] ?? null;
        $insertDay->execute([
            'weekday' => $weekday,
            'enabled' => $period === null ? 0 : 1,
            'open_time' => $period['open'] ?? null,
            'close_time' => $period['close'] ?? null,
        ]);
    }

    foreach (['storefront_settings', 'storefront_business_hours'] as $table) {
        $verify = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
        );
        $verify->execute(['schema' => $databaseConfig['database'], 'table' => $table]);
        if ((int) $verify->fetchColumn() !== 1) {
            throw new RuntimeException($table . ': table verification failed.');
        }
        fwrite(STDOUT, $table . ': ready' . PHP_EOL);
    }
    fwrite(STDOUT, 'storefront operations seed: ready' . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
