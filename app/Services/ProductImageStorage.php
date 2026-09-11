<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

final class ProductImageStorage implements ProductImageStorageInterface
{
    private const PUBLIC_PREFIX = '/uploads/products/';
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly string $publicRoot,
        private readonly ?\Closure $randomBytes = null,
    ) {
    }

    public function baseDirectory(): string
    {
        return rtrim($this->publicRoot, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    }

    public function generatePublicPath(string $extension, ?DateTimeInterface $date = null): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported product image extension.');
        }
        $date ??= new DateTimeImmutable('now');
        $bytes = $this->randomBytes instanceof \Closure ? ($this->randomBytes)(16) : random_bytes(16);
        if (!is_string($bytes) || strlen($bytes) !== 16) {
            throw new RuntimeException('Unable to generate a safe product image name.');
        }

        return self::PUBLIC_PREFIX . $date->format('Y/m') . '/' . bin2hex($bytes) . '.' . $extension;
    }

    public function physicalPath(string $publicPath): string
    {
        if (str_contains($publicPath, "\0")
            || str_contains($publicPath, '..')
            || str_contains($publicPath, '\\')
            || preg_match('#^/uploads/products/[0-9]{4}/(?:0[1-9]|1[0-2])/[a-f0-9]{32}\.(?:jpe?g|png|webp)$#', $publicPath) !== 1
        ) {
            throw new InvalidArgumentException('Invalid product image path.');
        }

        return rtrim($this->publicRoot, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
    }

    public function ensureDirectory(string $publicPath): string
    {
        $directory = dirname($this->physicalPath($publicPath));
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the product image directory.');
        }

        return $directory;
    }

    public function remove(string $publicPath): bool
    {
        $physicalPath = $this->physicalPath($publicPath);
        if (!is_file($physicalPath)) {
            return false;
        }
        $realBase = realpath($this->baseDirectory());
        $realFile = realpath($physicalPath);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Refusing to remove a file outside product image storage.');
        }

        return unlink($realFile);
    }
}
