<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Services\CartService;

class CreateOrderAction
{
    public function __construct(private readonly CartService $cart) {}

    public function handle(Customer $customer, string $provider): Order
    {
        $totals = $this->cart->totals();
        $products = $this->cart->contents();

        $order = Order::create([
            'customer_id' => $customer->id,
            'coupon_id' => $totals['coupon']?->id,
            'status' => 'pending',
            'currency' => 'EUR',
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'payment_provider' => $provider,
        ]);

        foreach ($products as $product) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'price' => $product->price,
            ]);
        }

        if ($totals['coupon']) {
            $totals['coupon']->increment('uses_count');
        }

        return $order->load('items');
    }
}
