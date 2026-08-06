<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Download;
use App\Models\OrderItem;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    protected $model = Download::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'product_file_id' => ProductFile::factory(),
            'token' => Str::uuid()->toString(),
            'download_count' => 0,
            'last_downloaded_at' => null,
        ];
    }
}
