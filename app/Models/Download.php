<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

#[Fillable(['order_item_id', 'token', 'download_count', 'last_downloaded_at'])]
class Download extends Model
{
    protected function casts(): array
    {
        return ['last_downloaded_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function url(): string
    {
        return URL::signedRoute('downloads.get', ['token' => $this->token]);
    }
}
