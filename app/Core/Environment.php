<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidEncodingException;
use Dotenv\Loader\Loader;
use Dotenv\Parser\Parser;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Store\StringStore;
use RuntimeException;

final class Environment
{
    public const FILE_ENCODING = 'UTF-8';

    public static function load(string $root): array
    {
        $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the environment file.');
        }

        if (!mb_check_encoding($contents, self::FILE_ENCODING)) {
            throw new InvalidEncodingException('The environment file must use UTF-8 encoding.');
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $repository = RepositoryBuilder::createWithDefaultAdapters()
            ->immutable()
            ->make();

        $dotenv = new Dotenv(
            new StringStore($contents),
            new Parser(),
            new Loader(),
            $repository,
        );

        return $dotenv->load();
    }
}