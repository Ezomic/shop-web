<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['slug', 'translations', 'price', 'currency', 'status', 'preview_url', 'sort_order'])]
class Product extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function priceFormatted(): string
    {
        return '€ '.number_format($this->price / 100, 2, ',', '.');
    }
}
