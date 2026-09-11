<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ProductImageException;
use App\Services\ProductImageProcessor;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductImageProcessorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vitrine-processor-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testValidJpegIsResizedProportionallyAndEncodedAsWebp(): void
    {
        $this->requireCodec('imagejpeg');
        $source = $this->image('source.jpg', 2000, 1000, 'imagejpeg');
        $destination = $this->directory . '/result.webp';

        $metadata = (new ProductImageProcessor())->process($this->upload($source), $destination);

        self::assertSame(['mime_type' => 'image/webp', 'width' => 1600, 'height' => 800], array_diff_key($metadata, ['size_bytes' => true]));
        self::assertGreaterThan(0, $metadata['size_bytes']);
        self::assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->file($destination));
        self::assertNotSame(hash_file('sha256', $source), hash_file('sha256', $destination));
    }

    public function testValidPngIsNotUpscaledAndPreservesTransparency(): void
    {
        $this->requireCodec('imagepng');
        $source = $this->transparentPng('source.png', 80, 60);
        $destination = $this->directory . '/result.webp';

        $metadata = (new ProductImageProcessor())->process($this->upload($source), $destination);
        $result = imagecreatefromwebp($destination);

        self::assertSame(80, $metadata['width']);
        self::assertSame(60, $metadata['height']);
        self::assertInstanceOf(GdImage::class, $result);
        self::assertGreaterThan(0, (imagecolorat($result, 0, 0) >> 24) & 0x7F);
        unset($result);
    }

    public function testValidWebpIsDecodedAndReencoded(): void
    {
        $this->requireCodec('imagewebp');
        $source = $this->image('source.webp', 120, 90, 'imagewebp');
        $destination = $this->directory . '/result.webp';

        $metadata = (new ProductImageProcessor())->process($this->upload($source), $destination);

        self::assertSame('image/webp', $metadata['mime_type']);
        self::assertSame([120, 90], [$metadata['width'], $metadata['height']]);
    }

    public function testNonImageIsRejected(): void
    {
        $this->requireCapabilities();
        $source = $this->directory . '/fake.jpg';
        file_put_contents($source, 'not an image');

        $this->expectException(ProductImageException::class);
        $this->expectExceptionMessage('Selecione uma imagem JPEG, PNG ou WebP.');
        (new ProductImageProcessor())->process($this->upload($source), $this->directory . '/result.webp');
    }

    public function testFileAboveEightMegabytesIsRejectedBeforeDecode(): void
    {
        $source = $this->directory . '/large.bin';
        $handle = fopen($source, 'wb');
        fseek($handle, ProductImageProcessor::MAX_BYTES);
        fwrite($handle, 'x');
        fclose($handle);

        $this->expectException(ProductImageException::class);
        $this->expectExceptionMessage('A imagem excede o limite de 8 MB.');
        (new ProductImageProcessor())->process($this->upload($source), $this->directory . '/result.webp');
    }

    #[DataProvider('uploadErrorProvider')]
    public function testUploadErrorsHaveSafeMessages(int $error, string $message): void
    {
        $this->expectException(ProductImageException::class);
        $this->expectExceptionMessage($message);
        (new ProductImageProcessor())->process(['error' => $error], $this->directory . '/result.webp');
    }

    public static function uploadErrorProvider(): array
    {
        return [
            'missing' => [UPLOAD_ERR_NO_FILE, 'Selecione uma imagem JPEG, PNG ou WebP.'],
            'ini limit' => [UPLOAD_ERR_INI_SIZE, 'A imagem excede o limite de 8 MB.'],
            'partial' => [UPLOAD_ERR_PARTIAL, 'O upload não foi concluído. Tente novamente.'],
        ];
    }

    public function testPixelBombDimensionsAreRejectedBeforeGdDecode(): void
    {
        $this->requireCapabilities();
        $source = $this->directory . '/huge.png';
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', 10000, 5000, 8, 6, 0, 0, 0);
        file_put_contents($source, $signature . $this->pngChunk('IHDR', $ihdr) . $this->pngChunk('IEND', ''));

        $this->expectException(ProductImageException::class);
        $this->expectExceptionMessage('A imagem possui dimensões muito grandes.');
        (new ProductImageProcessor())->process($this->upload($source), $this->directory . '/result.webp');
    }

    private function requireCapabilities(): void
    {
        if (!class_exists('finfo') || !extension_loaded('gd') || !function_exists('imagewebp')) {
            self::markTestSkipped('Fileinfo and GD WebP are required for this codec test.');
        }
    }

    private function requireCodec(string $function): void
    {
        $this->requireCapabilities();
        if (!function_exists($function) || !function_exists('imagecreatefrom' . substr($function, 5))) {
            self::markTestSkipped($function . ' codec is unavailable.');
        }
    }

    private function image(string $name, int $width, int $height, string $writer): string
    {
        $path = $this->directory . '/' . $name;
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 180, 60, 20);
        imagefill($image, 0, 0, $color);
        $writer($image, $path);
        unset($image);
        return $path;
    }

    private function transparentPng(string $name, int $width, int $height): string
    {
        $path = $this->directory . '/' . $name;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);
        unset($image);
        return $path;
    }

    private function upload(string $path): array
    {
        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => 1, 'name' => 'ignored.exe', 'type' => 'application/octet-stream'];
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
