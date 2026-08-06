<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $product_id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property int $size
 */
#[Fillable(['product_id', 'disk', 'path', 'original_filename', 'size'])]
class ProductFile extends Model
{
    /** @use HasFactory<ProductFileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Download, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }
}
