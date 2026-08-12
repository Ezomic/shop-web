<?php

declare(strict_types=1);

use App\Mail\OrderPaidMail;
use App\Models\Customer;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\PaymentService;
use App\Services\PaymentState;
use App\Services\PaymentStatus;
use Illuminate\Support\Facades\Mail;

function fakeStripeStatus(?PaymentStatus $status): void
{
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('stripeSessionStatus')->andReturn($status);

    app()->instance(PaymentService::class, $payment);
}

function orderForCustomer(Customer $customer): Order
{
    $order = Order::factory()->for($customer)->create();
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    return $order;
}

it('does not complete an order when the stripe session is unpaid', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = orderForCustomer($customer);

    fakeStripeStatus(new PaymentStatus(orderId: $order->id, state: PaymentState::Pending, method: 'card'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['session_id' => 'cs_test_unpaid']))
        ->assertOk();

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(Download::count())->toBe(0);

    Mail::assertNothingQueued();
});

it('completes an order when the stripe session is paid', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = orderForCustomer($customer);

    fakeStripeStatus(new PaymentStatus(orderId: $order->id, state: PaymentState::Paid, method: 'card'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['session_id' => 'cs_test_paid']))
        ->assertOk();

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(Download::count())->toBe(1);

    Mail::assertQueued(OrderPaidMail::class);
});

it('refuses to touch an order belonging to another customer', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $victim = Customer::factory()->create();
    $order = orderForCustomer($victim);

    fakeStripeStatus(new PaymentStatus(orderId: $order->id, state: PaymentState::Paid, method: 'card'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['session_id' => 'cs_test_someone_else']))
        ->assertForbidden();

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(Download::count())->toBe(0);

    Mail::assertNothingQueued();
});

it('renders the pending state when no session id is given', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success'))
        ->assertOk();
});

it('survives a session id stripe does not recognise', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();

    fakeStripeStatus(null);

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['session_id' => 'cs_test_bogus']))
        ->assertOk();

    Mail::assertNothingQueued();
});

it('is reachable without an account and shows nothing about anyone else', function (): void {
    $order = orderForCustomer(Customer::factory()->create());

    $this->get(route('checkout.success'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paid', false)->where('claimable', false));

    // A stranger naming somebody else order gets nothing.
    $this->get(route('checkout.success', ['order' => $order->id]))->assertForbidden();
});
