<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property int $price
 * @property string $currency
 * @property string $status
 * @property string|null $cover_path
 * @property string|null $cover_thumb_path
 * @property string|null $sample_path
 * @property string|null $sample_filename
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $name Locale-resolved via spatie/laravel-translatable
 * @property string $description Locale-resolved via spatie/laravel-translatable
 */
#[Fillable(['slug', 'name', 'description', 'price', 'currency', 'status', 'sort_order'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /**
     * @return HasMany<ProductFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Search the active locale only. name and description are JSON columns holding every locale
     * (SHOP-14), so a plain LIKE would match a Dutch title while the visitor is reading English.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $locale = app()->getLocale();
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($locale, $like): void {
            $query->where('name->'.$locale, 'like', $like)
                ->orWhere('description->'.$locale, 'like', $like);
        });
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeSorted(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            default => $query->ordered(),
        };
    }

    public function priceFormatted(): string
    {
        return '€ '.number_format($this->price / 100, 2, ',', '.');
    }
}
