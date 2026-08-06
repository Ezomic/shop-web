<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int|null $product_file_id
 * @property string $token
 * @property int $download_count
 * @property CarbonImmutable|null $last_downloaded_at
 */
#[Fillable(['order_item_id', 'product_file_id', 'token', 'download_count', 'last_downloaded_at'])]
class Download extends Model
{
    protected function casts(): array
    {
        return ['last_downloaded_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<ProductFile, $this>
     */
    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    public function url(): string
    {
        return URL::signedRoute('downloads.get', ['token' => $this->token]);
    }
}
