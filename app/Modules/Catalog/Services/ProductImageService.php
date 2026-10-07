<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan foto barang dalam ukuran kecil (maks. 800px, JPEG ~80 KB)
 * supaya ringan di HP murah dan hemat kuota. Foto sudah dikompres di HP sebelum
 * diunggah; di sini dikecilkan lagi sebagai pengaman.
 */
class ProductImageService
{
    private const MAX_SIZE = 800;

    public function store(Product $product, UploadedFile $file): string
    {
        $disk = Storage::disk(config('hermes.media_disk'));
        $tenantUuid = $product->tenant()->value('uuid');
        $path = "products/{$tenantUuid}/{$product->uuid}-".Str::lower(Str::random(6)).'.jpg';

        $disk->put($path, $this->resize($file), ['visibility' => 'public']);

        if ($product->image_path) {
            $disk->delete($product->image_path);
        }

        return $path;
    }

    public function delete(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk(config('hermes.media_disk'))->delete($product->image_path);
        }
    }

    private function resize(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($contents);

        // Kalau GD tidak bisa membaca (format tidak dikenal), simpan apa adanya.
        if (! $image) {
            return $contents;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIZE / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 80);
        imagedestroy($image);

        return ob_get_clean();
    }
}
