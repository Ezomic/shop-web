<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\PaymentState;
use App\Services\PaymentStatus;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config(['shop.vat.home_country' => 'NL']);
});

it('records the ip address when the order is created', function (): void {
    app(CartService::class)->add(Product::factory()->create());

    $order = app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe', '203.0.113.7');

    expect($order->ip_address)->toBe('203.0.113.7');
});

it('records the ip address through the checkout endpoint', function (): void {
    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create());

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('createStripeSession')->andReturn('https://stripe.test/session');
    app()->instance(PaymentService::class, $payment);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe']);

    expect(Order::firstOrFail()->ip_address)->not->toBeNull();
});

it('takes the country from the payment provider', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();

    app(CompleteOrderAction::class)->handle($order, 'card', 'BE');

    $order->refresh();

    expect($order->country)->toBe('BE')
        ->and($order->country_source)->toBe('stripe');
});

it('labels a mollie sourced country with its provider', function (): void {
    $order = Order::factory()->mollie()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();

    app(CompleteOrderAction::class)->handle($order, 'ideal', 'DE');

    expect($order->fresh()->country_source)->toBe('mollie');
});

it('falls back to the home country and says so when the provider gives nothing', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();

    app(CompleteOrderAction::class)->handle($order, 'card', null);

    $order->refresh();

    expect($order->country)->toBe('NL')
        ->and($order->country_source)->toBe('fallback');
});

it('gives every paid order a country and a source', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();

    app(CompleteOrderAction::class)->handle($order, 'card');

    $order->refresh();

    expect($order->country)->not->toBeNull()
        ->and($order->country_source)->not->toBeNull();
});

it('carries the country through the mollie webhook', function (): void {
    $order = Order::factory()->mollie()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('molliePaymentStatus')
        ->andReturn(new PaymentStatus($order->id, PaymentState::Paid, 'ideal', 'FR'));
    app()->instance(PaymentService::class, $payment);

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();

    expect($order->fresh()->country)->toBe('FR');
});

it('carries the country through the stripe return', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('stripeSessionStatus')
        ->andReturn(new PaymentStatus($order->id, PaymentState::Paid, 'card', 'ES'));
    app()->instance(PaymentService::class, $payment);

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['session_id' => 'cs_test_es']))
        ->assertOk();

    expect($order->fresh()->country)->toBe('ES');
});

it('groups paid orders by country for a vat return', function (): void {
    foreach (['NL', 'NL', 'BE'] as $country) {
        $order = Order::factory()->for(Customer::factory())->create();
        OrderItem::factory()->for($order)->create();
        app(CompleteOrderAction::class)->handle($order, 'card', $country);
    }

    $byCountry = Order::where('status', 'paid')
        ->selectRaw('country, count(*) as orders')
        ->groupBy('country')
        ->pluck('orders', 'country');

    expect($byCountry['NL'])->toBe(2)->and($byCountry['BE'])->toBe(1);
});

it('does not ask the customer for an address at checkout', function (): void {
    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create());

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('address')->missing('country'));
});

it('shows the country and its source to an admin', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->create();
    app(CompleteOrderAction::class)->handle($order, 'card', 'BE');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.country', 'BE')
            ->where('order.country_source', 'stripe'));
});
