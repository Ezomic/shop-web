<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\PaymentService;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

it('lists orders newest first', function (): void {
    $older = Order::factory()->for(Customer::factory())->create(['created_at' => now()->subDay()]);
    $newer = Order::factory()->for(Customer::factory())->create();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/Index')
            ->where('orders.data.0.id', $newer->id)
            ->where('orders.data.1.id', $older->id));
});

it('shows an order with its customer and coupon', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SUMMER']);
    $customer = Customer::factory()->create(['name' => 'Nora']);
    $order = Order::factory()->paid()->for($customer)->create(['coupon_id' => $coupon->id]);
    OrderItem::factory()->for($order)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.customer.name', 'Nora')
            ->where('order.coupon_code', 'SUMMER')
            ->has('order.items', 1));
});

it('refunds a paid order', function (): void {
    $order = Order::factory()->paid()->for(Customer::factory())->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('refundStripe')->once()->andReturnUsing(
        fn (Order $refunded) => $refunded->update(['status' => 'refunded'])
    );
    app()->instance(PaymentService::class, $payment);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('refunded');
});

it('refuses to refund an unpaid order', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.refund', $order))
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe('pending');
});

it('routes a mollie order to the mollie refund', function (): void {
    $order = Order::factory()->mollie()->paid()->for(Customer::factory())->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('refundMollie')->once();
    $payment->shouldNotReceive('refundStripe');
    app()->instance(PaymentService::class, $payment);

    $this->actingAs($this->admin)->post(route('admin.orders.refund', $order))->assertRedirect();
});

it('keeps the admin order pages behind the admin guard', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();

    $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.orders.show', $order))->assertRedirect(route('admin.login'));
    $this->post(route('admin.orders.refund', $order))->assertRedirect(route('admin.login'));
});
