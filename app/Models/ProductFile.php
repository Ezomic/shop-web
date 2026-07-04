<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'disk', 'path', 'original_filename', 'size'])]
class ProductFile extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
