<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\VatCalculator;

class CreateOrderAction
{
    public function __construct(
        private readonly CartService $cart,
        private readonly VatCalculator $vat,
    ) {}

    public function handle(Customer $customer, string $provider): Order
    {
        $totals = $this->cart->totals();
        $products = $this->cart->contents()->values();

        $lines = $this->vat->allocate(
            array_values($products->map(fn (Product $product): int => $product->price)->all()),
            $totals['total'],
        );

        $vatAmount = array_sum(array_map(fn ($line): int => $line->vat, $lines));

        $order = Order::create([
            'customer_id' => $customer->id,
            'coupon_id' => $totals['coupon']?->id,
            'status' => 'pending',
            'currency' => 'EUR',
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'vat_rate' => $this->vat->rate(),
            'vat_amount' => $vatAmount,
            'net_total' => $totals['total'] - $vatAmount,
            'payment_provider' => $provider,
        ]);

        foreach ($products as $index => $product) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'price' => $product->price,
                'vat_rate' => $this->vat->rate(),
                'vat_amount' => $lines[$index]->vat,
                'net_price' => $lines[$index]->net,
            ]);
        }

        return $order->load('items');
    }
}
