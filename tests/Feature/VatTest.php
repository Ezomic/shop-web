<?php

declare(strict_types=1);

use App\Actions\CreateOrderAction;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\VatCalculator;

beforeEach(function (): void {
    config(['shop.vat.rate' => 21]);
});

function orderFor(array $prices, ?Coupon $coupon = null): Order
{
    $cart = app(CartService::class);

    foreach ($prices as $price) {
        $cart->add(Product::factory()->create(['price' => $price]));
    }

    if ($coupon) {
        $cart->applyCoupon($coupon->code);
    }

    return app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe');
}

it('extracts vat from a vat inclusive price', function (): void {
    // 121.00 gross at 21% is 100.00 net plus 21.00 vat.
    expect(app(VatCalculator::class)->vatOn(12100))->toBe(2100);
});

it('records the rate, vat and net on the order', function (): void {
    $order = orderFor([12100]);

    expect($order->vat_rate)->toBe(21)
        ->and($order->vat_amount)->toBe(2100)
        ->and($order->net_total)->toBe(10000)
        ->and($order->net_total + $order->vat_amount)->toBe($order->total);
});

it('records the same breakdown on the order item', function (): void {
    $order = orderFor([12100]);
    $item = $order->items->firstOrFail();

    expect($item->vat_rate)->toBe(21)
        ->and($item->vat_amount)->toBe(2100)
        ->and($item->net_price)->toBe(10000);
});

it('keeps the item breakdown adding up to the order total', function (): void {
    $order = orderFor([1999, 2500, 749]);

    expect($order->items->sum('vat_amount'))->toBe($order->vat_amount)
        ->and($order->items->sum('net_price') + $order->items->sum('vat_amount'))->toBe($order->total);
});

it('lets a discount pull the vat down with it', function (): void {
    $coupon = Coupon::factory()->percent(50)->create();
    $order = orderFor([10000], $coupon);

    // Half of 100.00 is 50.00 gross, which at 21% is 41.32 net plus 8.68 vat.
    expect($order->total)->toBe(5000)
        ->and($order->vat_amount)->toBe(868)
        ->and($order->net_total)->toBe(4132)
        ->and($order->net_total + $order->vat_amount)->toBe($order->total);
});

it('shares a discount across items so the lines still sum to the total', function (): void {
    $coupon = Coupon::factory()->fixed(1000)->create();
    $order = orderFor([3333, 3333, 3334], $coupon);

    expect($order->total)->toBe(9000)
        ->and($order->items->sum('net_price') + $order->items->sum('vat_amount'))->toBe(9000)
        ->and($order->items->sum('vat_amount'))->toBe($order->vat_amount);
});

it('never loses a cent to rounding when sharing a discount', function (): void {
    $coupon = Coupon::factory()->percent(33)->create();
    $order = orderFor([999, 1999, 2999, 4999], $coupon);

    $lineGross = $order->items->sum('net_price') + $order->items->sum('vat_amount');

    expect($lineGross)->toBe($order->total);
});

it('records no vat when the rate is zero', function (): void {
    config(['shop.vat.rate' => 0]);

    $order = orderFor([12100]);

    expect($order->vat_rate)->toBe(0)
        ->and($order->vat_amount)->toBe(0)
        ->and($order->net_total)->toBe($order->total);
});

it('follows the configured rate', function (): void {
    config(['shop.vat.rate' => 9]);

    $order = orderFor([10900]);

    expect($order->vat_rate)->toBe(9)
        ->and($order->vat_amount)->toBe(900)
        ->and($order->net_total)->toBe(10000);
});

it('keeps the historic breakdown when the rate later changes', function (): void {
    $order = orderFor([12100]);

    config(['shop.vat.rate' => 9]);

    expect($order->fresh()->vat_rate)->toBe(21)
        ->and($order->fresh()->vat_amount)->toBe(2100);
});

it('shows the vat line on the checkout page', function (): void {
    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create(['price' => 12100]));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('vat_rate', 21)->where('vat_amount', 2100));
});

it('shows the vat breakdown on the customer order page', function (): void {
    $order = orderFor([12100]);

    $this->actingAs($order->customer, 'customer')
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.vat_rate', 21)
            ->where('order.vat_amount', 2100)
            ->where('order.net_total', 10000));
});

it('allocates nothing when the payable total is zero', function (): void {
    $lines = app(VatCalculator::class)->allocate([1000, 2000], 0);

    expect(array_sum(array_map(fn ($l): int => $l->gross, $lines)))->toBe(0)
        ->and(array_sum(array_map(fn ($l): int => $l->vat, $lines)))->toBe(0);
});
