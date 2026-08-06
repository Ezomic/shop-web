<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Mail\OrderPaidMail;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Download;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

it('snapshots the product name, slug and price onto the order item', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts'],
        'price' => 2500,
        'slug' => 'stage-fright',
    ]);

    app(CartService::class)->add($product);

    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');
    $item = $order->items->firstOrFail();

    expect($item->product_name)->toBe('Stage Fright')
        ->and($item->product_slug)->toBe('stage-fright')
        ->and($item->price)->toBe(2500);

    $product->update(['name' => ['en' => 'Renamed', 'nl' => 'Hernoemd'], 'price' => 9999]);

    expect($item->fresh()->product_name)->toBe('Stage Fright')
        ->and($item->fresh()->price)->toBe(2500);
});

it('records the totals and the provider on the order', function (): void {
    $customer = Customer::factory()->create();
    $coupon = Coupon::factory()->percent(20)->create();

    $cart = app(CartService::class);
    $cart->add(Product::factory()->create(['price' => 1000]));
    $cart->add(Product::factory()->create(['price' => 1500]));
    $cart->applyCoupon($coupon->code);

    $order = app(CreateOrderAction::class)->handle($customer, 'mollie');

    expect($order->subtotal)->toBe(2500)
        ->and($order->discount)->toBe(500)
        ->and($order->total)->toBe(2000)
        ->and($order->currency)->toBe('EUR')
        ->and($order->status)->toBe('pending')
        ->and($order->payment_provider)->toBe('mollie')
        ->and($order->coupon_id)->toBe($coupon->id)
        ->and($order->items)->toHaveCount(2);
});

it('creates an order without a coupon', function (): void {
    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create(['price' => 1000]));

    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    expect($order->coupon_id)->toBeNull()
        ->and($order->discount)->toBe(0);
});

it('marks the order paid and mails the customer', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    app(CartService::class)->add($product);
    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'ideal');

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->payment_method)->toBe('ideal')
        ->and($order->paid_at)->not->toBeNull()
        ->and(Download::count())->toBe(1);

    Mail::assertSent(OrderPaidMail::class, fn (OrderPaidMail $mail) => $mail->hasTo($customer->email));
});

it('does nothing when the order is already paid', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    app(CartService::class)->add($product);
    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'card');
    $firstPaidAt = $order->fresh()->paid_at;

    app(CompleteOrderAction::class)->handle($order->fresh(), 'ideal');

    $order->refresh();

    expect($order->payment_method)->toBe('card')
        ->and($order->paid_at->timestamp)->toBe($firstPaidAt->timestamp)
        ->and(Download::count())->toBe(1);

    Mail::assertSentCount(1);
});

it('sends the order mail with a link per purchased file', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $product = Product::factory()->has(ProductFile::factory()->count(2), 'files')->create();

    app(CartService::class)->add($product);
    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'card');

    Mail::assertSent(OrderPaidMail::class);
    expect(Download::count())->toBe(2);
});

it('renders the order mail without blowing up on a product with no file', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    app(CartService::class)->add($product);
    $order = app(CreateOrderAction::class)->handle($customer, 'stripe');

    app(CompleteOrderAction::class)->handle($order, 'card');

    expect(Download::count())->toBe(0)
        ->and($order->fresh()->isPaid())->toBeTrue();
});

it('requires a logged in customer to reach checkout', function (): void {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
    $this->post(route('checkout.store'), ['provider' => 'stripe'])->assertRedirect(route('login'));
});

it('sends an empty cart back to the shop instead of to checkout', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.index'))
        ->assertRedirect(route('shop.index'));

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe'])
        ->assertRedirect(route('shop.index'));

    expect(Order::count())->toBe(0);
});

it('rejects an unknown payment provider', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'bitcoin'])
        ->assertSessionHasErrors('provider');
});
