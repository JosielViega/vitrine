<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use GdImage;
use Throwable;

final class ProductImageProcessor implements ProductImageProcessorInterface
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_PIXELS = 40_000_000;
    public const MAX_SIDE = 1600;
    public const WEBP_QUALITY = 82;
    private const MIME_LOADERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];

    public function process(array $upload, string $destination): array
    {
        $temporary = $this->validateUpload($upload);
        if (!class_exists(finfo::class)) {
            throw new ProductImageException('O servidor não possui suporte Fileinfo para validar a imagem.');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new ProductImageException('O servidor não possui suporte GD/WebP para processar a imagem.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        if (!is_string($mime) || !isset(self::MIME_LOADERS[$mime])) {
            throw new ProductImageException('Selecione uma imagem JPEG, PNG ou WebP.');
        }
        $dimensions = @getimagesize($temporary);
        if (!is_array($dimensions) || (int) $dimensions[0] <= 0 || (int) $dimensions[1] <= 0) {
            throw new ProductImageException('Selecione uma imagem JPEG, PNG ou WebP.');
        }
        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width > 40_000 || $height > 40_000 || $width > intdiv(self::MAX_PIXELS, $height)
            || !$this->fitsMemoryBudget($width, $height)
        ) {
            throw new ProductImageException('A imagem possui dimensões muito grandes.');
        }

        $loader = self::MIME_LOADERS[$mime];
        if (!function_exists($loader)) {
            throw new ProductImageException('Não foi possível processar a imagem.');
        }

        $source = null;
        $output = null;
        try {
            $source = @$loader($temporary);
            if (!$source instanceof GdImage) {
                throw new ProductImageException('Não foi possível processar a imagem.');
            }
            if ($mime === 'image/jpeg') {
                $source = $this->orientJpeg($source, $temporary);
            }
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, self::MAX_SIDE / max($sourceWidth, $sourceHeight));
            $finalWidth = max(1, (int) round($sourceWidth * $scale));
            $finalHeight = max(1, (int) round($sourceHeight * $scale));
            $output = imagecreatetruecolor($finalWidth, $finalHeight);
            if (!$output instanceof GdImage) {
                throw new ProductImageException('Não foi possível processar a imagem.');
            }
            imagealphablending($output, false);
            imagesavealpha($output, true);
            $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $transparent);
            if (!imagecopyresampled($output, $source, 0, 0, 0, 0, $finalWidth, $finalHeight, $sourceWidth, $sourceHeight)
                || !imagewebp($output, $destination, self::WEBP_QUALITY)
                || !is_file($destination)
            ) {
                throw new ProductImageException('Não foi possível processar a imagem.');
            }

            return ['mime_type' => 'image/webp', 'width' => $finalWidth, 'height' => $finalHeight, 'size_bytes' => filesize($destination)];
        } catch (ProductImageException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ProductImageException('Não foi possível processar a imagem.');
        } finally {
            unset($source, $output);
        }
    }

    private function fitsMemoryBudget(int $width, int $height): bool
    {
        $memory = trim((string) ini_get('memory_limit'));
        if ($memory === '' || $memory === '-1') {
            return true;
        }
        $number = (float) $memory;
        $limit = (int) ($number * match (strtolower(substr($memory, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        });
        $estimatedDecode = $width * $height * 5;
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $estimatedOutput = (int) round($width * $scale) * (int) round($height * $scale) * 5;

        return memory_get_usage(true) + $estimatedDecode + $estimatedOutput < (int) ($limit * 0.8);
    }
    private function validateUpload(array $upload): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ProductImageException('Selecione uma imagem JPEG, PNG ou WebP.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ProductImageException('A imagem excede o limite de 8 MB.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ProductImageException('O upload não foi concluído. Tente novamente.');
        }
        $temporary = (string) ($upload['tmp_name'] ?? '');
        $size = $temporary !== '' && is_file($temporary) ? filesize($temporary) : false;
        if ($size === false || $size <= 0) {
            throw new ProductImageException('Selecione uma imagem JPEG, PNG ou WebP.');
        }
        if ($size > self::MAX_BYTES) {
            throw new ProductImageException('A imagem excede o limite de 8 MB.');
        }

        return $temporary;
    }

    private function orientJpeg(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated instanceof GdImage) {
                return $rotated;
            }
        }

        return $image;
    }
}
