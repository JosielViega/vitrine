<?php

declare(strict_types=1);

namespace App\Services;

interface ProductImageProcessorInterface
{
    public function process(array $upload, string $destination): array;
}
