<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontProductImageRepositoryInterface;
use Throwable;

final class ProductImageService
{
    public function __construct(
        private readonly StorefrontProductImageRepositoryInterface $repository,
        private readonly StorefrontProductGroupingService $grouping,
        private readonly ProductImageProcessorInterface $processor,
        private readonly ProductImageStorageInterface $storage,
        private readonly array $presentation,
    ) {
    }

    public function groups(): array
    {
        return array_map($this->presentGroup(...), $this->grouping->group($this->repository->administrativeProducts()));
    }

    public function upload(string $groupKey, array $upload): void
    {
        $group = $this->resolveGroup($groupKey);
        $publicPath = $this->storage->generatePublicPath('webp');
        $this->storage->ensureDirectory($publicPath);
        $finalPath = $this->storage->physicalPath($publicPath);
        $temporaryPath = $finalPath . '.' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            $metadata = $this->processor->process($upload, $temporaryPath);
            if (!is_file($temporaryPath) || !rename($temporaryPath, $finalPath)) {
                throw new ProductImageException('Não foi possível processar a imagem.');
            }
            try {
                $result = $this->repository->replaceGroupImage(
                    ['path' => $publicPath, ...$metadata],
                    $this->productIds($group),
                );
            } catch (Throwable $exception) {
                $this->storage->remove($publicPath);
                throw $exception;
            }
            $this->cleanupImages($result['previous_images']);
        } catch (ProductImageException $exception) {
            $this->removeTemporary($temporaryPath);
            throw $exception;
        } catch (Throwable) {
            $this->removeTemporary($temporaryPath);
            if (is_file($finalPath)) {
                $this->storage->remove($publicPath);
            }
            throw new ProductImageException('Não foi possível processar a imagem.');
        }
    }

    public function remove(string $groupKey): void
    {
        $group = $this->resolveGroup($groupKey);
        $previous = $this->repository->removeGroupAssociations($this->productIds($group));
        $this->cleanupImages($previous);
    }

    public function environmentWarnings(): array
    {
        $warnings = [];
        if (!class_exists('finfo')) {
            $warnings[] = 'Fileinfo não está disponível; uploads serão recusados até a extensão ser habilitada.';
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $warnings[] = 'GD com WebP não está disponível; uploads serão recusados até o suporte ser habilitado.';
        }
        $uploadLimit = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postLimit = $this->iniBytes((string) ini_get('post_max_size'));
        if ($uploadLimit > 0 && $uploadLimit < ProductImageProcessor::MAX_BYTES) {
            $warnings[] = 'upload_max_filesize do PHP está abaixo dos 8 MB desejados.';
        }
        if ($postLimit > 0 && $postLimit <= ProductImageProcessor::MAX_BYTES) {
            $warnings[] = 'post_max_size do PHP deve ser maior que 8 MB.';
        }
        return $warnings;
    }

    private function resolveGroup(string $groupKey): array
    {
        foreach ($this->grouping->group($this->repository->administrativeProducts()) as $group) {
            if (hash_equals($group['key'], $groupKey)) {
                return $group;
            }
        }
        throw new ProductImageException('Produto não encontrado.');
    }

    private function presentGroup(array $group): array
    {
        $primary = $group['primary_row'];
        $secondary = $group['secondary_row'];
        $managed = $this->managedRow($primary, $secondary);
        $slug = $this->grouping->slug($group['name']);
        $editorial = (array) ($this->presentation['products'][$slug] ?? []);
        $categorySlug = $this->grouping->slug((string) $primary['category_name']);
        $image = $managed['storefront_image_path'] ?? $editorial['image']
            ?? $this->presentation['fallback_images'][$categorySlug]
            ?? $this->presentation['fallback_image']
            ?? '/assets/images/products/mixed-portion-placeholder.jpg';
        $source = $managed !== null ? 'managed' : (isset($editorial['image']) ? 'editorial' : 'fallback');
        $labels = array_map(static fn (array $variant): string => $variant['label'] ?: 'Única', $group['variants']);
        $rows = array_column($group['variants'], 'row');

        return [
            'key' => $group['key'],
            'name' => $group['name'],
            'category_name' => (string) $primary['category_name'],
            'subcategory_name' => (string) $primary['subcategory_name'],
            'product_ids' => array_map(static fn (array $row): int => (int) $row['id'], $rows),
            'variant_labels' => $labels,
            'variant_names' => array_map(static fn (array $row): string => (string) $row['name'], $rows),
            'image' => (string) $image,
            'image_source' => $source,
            'image_id' => $managed === null ? null : (int) $managed['storefront_image_id'],
            'mime_type' => $managed['storefront_image_mime_type'] ?? null,
            'width' => $managed === null ? null : (int) $managed['storefront_image_width'],
            'height' => $managed === null ? null : (int) $managed['storefront_image_height'],
            'size_bytes' => $managed === null ? null : (int) $managed['storefront_image_size_bytes'],
            'active' => count(array_filter($rows, static fn (array $row): bool => (int) $row['active'] === 1)),
            'visible' => count(array_filter($rows, static fn (array $row): bool => (int) $row['storefront_visible'] === 1)),
        ];
    }

    private function managedRow(array $primary, ?array $secondary): ?array
    {
        foreach ([$primary, $secondary] as $row) {
            if (is_array($row) && trim((string) ($row['storefront_image_path'] ?? '')) !== '') {
                return $row;
            }
        }
        return null;
    }

    private function productIds(array $group): array
    {
        return array_map(static fn (array $variant): int => (int) $variant['row']['id'], $group['variants']);
    }

    private function cleanupImages(array $images): void
    {
        foreach ($images as $image) {
            $id = (int) $image['id'];
            if ($id <= 0 || $this->repository->productIdsForImage($id) !== []) {
                continue;
            }
            try {
                $this->storage->remove((string) $image['path']);
                $this->repository->deleteImageIfUnlinked($id);
            } catch (Throwable) {
                // Preserve unlinked metadata when safe physical cleanup cannot be confirmed.
            }
        }
    }

    private function removeTemporary(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $number = (float) $value;
        return (int) ($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        });
    }
}
