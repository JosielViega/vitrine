<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;

interface ProductImageStorageInterface
{
    public function generatePublicPath(string $extension, ?DateTimeInterface $date = null): string;
    public function ensureDirectory(string $publicPath): string;
    public function physicalPath(string $publicPath): string;
    public function remove(string $publicPath): bool;
}
