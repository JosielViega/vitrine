<?php

declare(strict_types=1);

use App\Core\Database;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$config = require $root . '/config/database.php';
$schema = (string) ($config['database'] ?? '');
$definitions = [
    'storefront_product_images' => <<<'SQL'
CREATE TABLE storefront_product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_storefront_product_images_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'storefront_product_image_products' => <<<'SQL'
CREATE TABLE storefront_product_image_products (
    product_id INT UNSIGNED NOT NULL,
    image_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id),
    KEY idx_storefront_product_image_products_image_id (image_id),
    CONSTRAINT fk_storefront_product_image_products_image
        FOREIGN KEY (image_id) REFERENCES storefront_product_images (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
];

try {
    if ($schema === '') {
        throw new RuntimeException('Database name is not configured.');
    }

    $pdo = (new Database($config))->connection();
    $tableStatement = $pdo->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    );

    foreach ($definitions as $table => $sql) {
        $tableStatement->execute(['schema' => $schema, 'table' => $table]);
        if ($tableStatement->fetchColumn() === false) {
            $pdo->exec($sql);
            fwrite(STDOUT, $table . ': created' . PHP_EOL);
        } else {
            fwrite(STDOUT, $table . ': already exists' . PHP_EOL);
        }
    }

    validateImagesSchema($pdo, $schema);
    fwrite(STDOUT, 'Storefront product images schema is valid.' . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function validateImagesSchema(PDO $pdo, string $schema): void
{
    $expected = [
        'storefront_product_images' => [
            'id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'path' => ['type' => 'varchar', 'length' => 255, 'nullable' => 'NO'],
            'mime_type' => ['type' => 'varchar', 'length' => 50, 'nullable' => 'NO'],
            'width' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO'],
            'height' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO'],
            'size_bytes' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO'],
            'created_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default_current' => true],
            'updated_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default_current' => true, 'extra' => 'on update current_timestamp'],
        ],
        'storefront_product_image_products' => [
            'product_id' => ['type' => 'int', 'unsigned' => true, 'nullable' => 'NO'],
            'image_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => 'NO'],
        ],
    ];
    $columns = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA '
        . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION',
    );

    foreach ($expected as $table => $expectedColumns) {
        $columns->execute(['schema' => $schema, 'table' => $table]);
        $actual = [];
        foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $actual[(string) $column['COLUMN_NAME']] = $column;
        }
        if (array_keys($actual) !== array_keys($expectedColumns)) {
            throw new RuntimeException($table . ': unexpected columns.');
        }
        foreach ($expectedColumns as $name => $definition) {
            assertColumnDefinition($table, $name, $actual[$name], $definition);
        }
        assertInnoDb($pdo, $schema, $table);
    }

    assertIndex($pdo, $schema, 'storefront_product_images', 'PRIMARY', ['id'], false);
    assertIndex($pdo, $schema, 'storefront_product_images', 'uq_storefront_product_images_path', ['path'], false);
    assertIndex($pdo, $schema, 'storefront_product_image_products', 'PRIMARY', ['product_id'], false);
    assertIndex($pdo, $schema, 'storefront_product_image_products', 'idx_storefront_product_image_products_image_id', ['image_id'], true);
    assertImageForeignKey($pdo, $schema);
}

function assertColumnDefinition(string $table, string $name, array $actual, array $expected): void
{
    $valid = strtolower((string) $actual['DATA_TYPE']) === $expected['type']
        && strtoupper((string) $actual['IS_NULLABLE']) === $expected['nullable'];
    if (($expected['unsigned'] ?? false) === true) {
        $valid = $valid && str_contains(strtolower((string) $actual['COLUMN_TYPE']), 'unsigned');
    }
    if (isset($expected['length'])) {
        $valid = $valid && (int) $actual['CHARACTER_MAXIMUM_LENGTH'] === $expected['length'];
    }
    if (($expected['default_current'] ?? false) === true) {
        $valid = $valid && str_starts_with(strtolower((string) $actual['COLUMN_DEFAULT']), 'current_timestamp');
    }
    if (isset($expected['extra'])) {
        $valid = $valid && str_contains(strtolower((string) $actual['EXTRA']), $expected['extra']);
    }
    if (!$valid) {
        throw new RuntimeException($table . '.' . $name . ': unexpected definition.');
    }
}

function assertInnoDb(PDO $pdo, string $schema, string $table): void
{
    $statement = $pdo->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    );
    $statement->execute(['schema' => $schema, 'table' => $table]);
    if (strtolower((string) $statement->fetchColumn()) !== 'innodb') {
        throw new RuntimeException($table . ': expected InnoDB engine.');
    }
}

function assertIndex(PDO $pdo, string $schema, string $table, string $index, array $columns, bool $allowNonUnique): void
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND INDEX_NAME = :index ORDER BY SEQ_IN_INDEX',
    );
    $statement->execute(['schema' => $schema, 'table' => $table, 'index' => $index]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (array_column($rows, 'COLUMN_NAME') !== $columns || (!$allowNonUnique && array_sum(array_map('intval', array_column($rows, 'NON_UNIQUE'))) !== 0)) {
        throw new RuntimeException($table . '.' . $index . ': unexpected index.');
    }
}

function assertImageForeignKey(PDO $pdo, string $schema): void
{
    $statement = $pdo->prepare(
        'SELECT k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE '
        . 'FROM information_schema.KEY_COLUMN_USAGE k '
        . 'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
        . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
        . 'WHERE k.TABLE_SCHEMA = :schema AND k.TABLE_NAME = :table AND k.COLUMN_NAME = :column',
    );
    $statement->execute(['schema' => $schema, 'table' => 'storefront_product_image_products', 'column' => 'image_id']);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $valid = is_array($row)
        && $row['REFERENCED_TABLE_NAME'] === 'storefront_product_images'
        && $row['REFERENCED_COLUMN_NAME'] === 'id'
        && $row['UPDATE_RULE'] === 'RESTRICT'
        && $row['DELETE_RULE'] === 'CASCADE';
    if (!$valid) {
        throw new RuntimeException('storefront_product_image_products.image_id: unexpected foreign key.');
    }
}
