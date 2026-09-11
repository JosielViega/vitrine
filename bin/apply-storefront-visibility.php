<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Environment;
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Environment::load($root);

$config = require $root . '/config/database.php';
$tables = ['categories', 'subcategories', 'products'];
$column = 'storefront_visible';

try {
    if ((string) $config['database'] === '') {
        throw new RuntimeException('Database name is not configured.');
    }

    $pdo = (new Database($config))->connection();
    $tableStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    );
    $columnStatement = $pdo->prepare(
        'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT '
        . 'FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
    );

    foreach ($tables as $table) {
        $tableStatement->execute(['schema' => $config['database'], 'table' => $table]);
        if ((int) $tableStatement->fetchColumn() !== 1) {
            throw new RuntimeException($table . ': required table not found; no changes were applied.');
        }
    }

    foreach ($tables as $table) {
        $definition = columnDefinition($columnStatement, (string) $config['database'], $table, $column);
        if ($definition !== null) {
            assertExpectedDefinition($table, $column, $definition);
            fwrite(STDOUT, $table . '.' . $column . ': already exists' . PHP_EOL);
            continue;
        }

        $pdo->exec(
            sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` TINYINT(1) NOT NULL DEFAULT 1',
                $table,
                $column,
            ),
        );
        $definition = columnDefinition($columnStatement, (string) $config['database'], $table, $column);
        if ($definition === null) {
            throw new RuntimeException($table . '.' . $column . ': column verification failed.');
        }
        assertExpectedDefinition($table, $column, $definition);
        fwrite(STDOUT, $table . '.' . $column . ': added' . PHP_EOL);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

/** @return array<string, mixed>|null */
function columnDefinition(PDOStatement $statement, string $schema, string $table, string $column): ?array
{
    $statement->execute(['schema' => $schema, 'table' => $table, 'column' => $column]);
    $definition = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($definition) ? $definition : null;
}

/** @param array<string, mixed> $definition */
function assertExpectedDefinition(string $table, string $column, array $definition): void
{
    $valid = strtolower((string) ($definition['DATA_TYPE'] ?? '')) === 'tinyint'
        && strtolower((string) ($definition['COLUMN_TYPE'] ?? '')) === 'tinyint(1)'
        && strtoupper((string) ($definition['IS_NULLABLE'] ?? '')) === 'NO'
        && (string) ($definition['COLUMN_DEFAULT'] ?? '') === '1';

    if (!$valid) {
        throw new RuntimeException($table . '.' . $column . ': exists with an unexpected definition.');
    }
}
