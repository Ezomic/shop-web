<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class CoverStorage
{
    private const DISPLAY_WIDTH = 1200;

    private const THUMB_WIDTH = 400;

    public function __construct(private readonly ImageManager $images = new ImageManager(new Driver)) {}

    public function store(Product $product, UploadedFile $upload): void
    {
        $this->forget($product);

        $base = 'covers/'.Str::uuid();

        $product->forceFill([
            'cover_path' => $this->write($upload, $base.'.webp', self::DISPLAY_WIDTH),
            'cover_thumb_path' => $this->write($upload, $base.'-thumb.webp', self::THUMB_WIDTH),
        ])->save();
    }

    public function forget(Product $product): void
    {
        foreach ([$product->cover_path, $product->cover_thumb_path] as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }

        $product->forceFill(['cover_path' => null, 'cover_thumb_path' => null])->save();
    }

    public function url(?string $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private function write(UploadedFile $upload, string $path, int $width): string
    {
        $image = $this->images->decodePath($upload->getRealPath());

        // scaleDown never enlarges, so a small upload stays as it is rather than being blown up.
        $image->scaleDown(width: $width);

        Storage::disk('public')->put($path, (string) $image->encode(new WebpEncoder(quality: 82)));

        return $path;
    }
}
