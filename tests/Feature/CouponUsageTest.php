<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\RefundOrderAction;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

function cartWith(Product $product, ?Coupon $coupon = null): CartService
{
    $cart = app(CartService::class);
    $cart->add($product);

    if ($coupon) {
        $cart->applyCoupon($coupon->code);
    }

    return $cart;
}

it('does not count a coupon use when the order is only created', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 2000]);
    $coupon = Coupon::factory()->percent(10)->create();

    cartWith($product, $coupon);

    app(CreateOrderAction::class)->handle($customer, 'stripe');

    expect($coupon->fresh()->uses_count)->toBe(0);
});

it('counts the coupon use once the order is paid', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 2000]);
    $coupon = Coupon::factory()->percent(10)->create();

    cartWith($product, $coupon);

    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'card');

    expect($coupon->fresh()->uses_count)->toBe(1);
});

it('does not count the same order twice when the webhook is replayed', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 2000]);
    $coupon = Coupon::factory()->percent(10)->create();

    cartWith($product, $coupon);

    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'card');
    app(CompleteOrderAction::class)->handle($order->fresh(), 'card');

    expect($coupon->fresh()->uses_count)->toBe(1);
});

it('releases the use again when the order is refunded', function (): void {
    $coupon = Coupon::factory()->create(['uses_count' => 1]);
    $customer = Customer::factory()->create();

    $order = Order::factory()->paid()->for($customer)->create(['coupon_id' => $coupon->id]);
    OrderItem::factory()->for($order)->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('refundStripe')->once();
    app()->instance(PaymentService::class, $payment);

    app(RefundOrderAction::class)->handle($order);

    expect($coupon->fresh()->uses_count)->toBe(0);
});

it('never drops the use count below zero on refund', function (): void {
    $coupon = Coupon::factory()->create(['uses_count' => 0]);
    $customer = Customer::factory()->create();

    $order = Order::factory()->paid()->for($customer)->create(['coupon_id' => $coupon->id]);

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('refundStripe')->once();
    app()->instance(PaymentService::class, $payment);

    app(RefundOrderAction::class)->handle($order);

    expect($coupon->fresh()->uses_count)->toBe(0);
});

it('lets a max-uses coupon survive abandoned checkouts', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['price' => 2000]);
    $coupon = Coupon::factory()->create(['max_uses' => 1]);

    cartWith($product, $coupon);

    app(CreateOrderAction::class)->handle($customer, 'stripe');
    app(CreateOrderAction::class)->handle($customer, 'stripe');

    expect($coupon->fresh()->isValid())->toBeTrue();
});
