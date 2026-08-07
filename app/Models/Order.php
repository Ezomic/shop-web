<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $coupon_id
 * @property string $status
 * @property string $currency
 * @property string|null $country
 * @property string|null $country_source
 * @property string|null $ip_address
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $vat_rate
 * @property int $vat_amount
 * @property int $net_total
 * @property string $payment_provider
 * @property string|null $payment_id
 * @property string|null $payment_method
 * @property CarbonImmutable|null $paid_at
 * @property string|null $invoice_number
 * @property CarbonImmutable|null $invoiced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'customer_id', 'coupon_id', 'status', 'currency',
    'country', 'country_source', 'ip_address',
    'subtotal', 'discount', 'total',
    'vat_rate', 'vat_amount', 'net_total',
    'payment_provider', 'payment_id', 'payment_method', 'paid_at',
    'invoice_number', 'invoiced_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'invoiced_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function totalFormatted(): string
    {
        return '€ '.number_format($this->total / 100, 2, ',', '.');
    }
}
