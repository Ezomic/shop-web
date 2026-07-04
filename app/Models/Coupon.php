<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'amount', 'max_uses', 'uses_count', 'expires_at', 'active'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function discountFor(int $subtotal): int
    {
        if ($this->type === 'percent') {
            return (int) round($subtotal * $this->amount / 100);
        }

        return min($this->amount, $subtotal);
    }
}
