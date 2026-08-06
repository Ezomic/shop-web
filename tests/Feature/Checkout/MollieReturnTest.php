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

function fakeMollieStatus(?PaymentStatus $status): void
{
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('molliePaymentStatus')->andReturn($status);

    app()->instance(PaymentService::class, $payment);
}

function mollieOrder(Customer $customer): Order
{
    $order = Order::factory()->mollie()->for($customer)->create();
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    return $order;
}

it('sends a cancelled payment to the cancel page', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = mollieOrder($customer);

    fakeMollieStatus(new PaymentStatus($order->id, PaymentState::Failed, 'ideal'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.mollie', $order))
        ->assertRedirect(route('checkout.cancel'));

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(Download::count())->toBe(0);

    Mail::assertNothingQueued();
});

it('sends a paid payment to the success page and completes the order', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = mollieOrder($customer);

    fakeMollieStatus(new PaymentStatus($order->id, PaymentState::Paid, 'ideal'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.mollie', $order))
        ->assertRedirect(route('checkout.success', ['order' => $order->id]));

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->payment_method)->toBe('ideal')
        ->and(Download::count())->toBe(1);

    Mail::assertQueued(OrderPaidMail::class);
});

it('keeps an open payment on the pending success page', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = mollieOrder($customer);

    fakeMollieStatus(new PaymentStatus($order->id, PaymentState::Pending, 'ideal'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.mollie', $order))
        ->assertRedirect(route('checkout.success', ['order' => $order->id]));

    expect($order->fresh()->isPaid())->toBeFalse();

    Mail::assertNothingQueued();
});

it('reports the pending state on the success page for an unpaid order', function (): void {
    $customer = Customer::factory()->create();
    $order = mollieOrder($customer);

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('checkout/Success')->where('paid', false));
});

it('reports the paid state on the success page once the webhook has landed', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->mollie()->paid()->for($customer)->create();

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paid', true));
});

it('refuses another customer order on the return route', function (): void {
    $customer = Customer::factory()->create();
    $order = mollieOrder(Customer::factory()->create());

    fakeMollieStatus(new PaymentStatus($order->id, PaymentState::Paid, 'ideal'));

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.mollie', $order))
        ->assertForbidden();

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('refuses another customer order on the success page', function (): void {
    $customer = Customer::factory()->create();
    $order = mollieOrder(Customer::factory()->create());

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.success', ['order' => $order->id]))
        ->assertForbidden();
});

it('falls back to the pending page when mollie does not know the payment', function (): void {
    $customer = Customer::factory()->create();
    $order = mollieOrder($customer);

    fakeMollieStatus(null);

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.mollie', $order))
        ->assertRedirect(route('checkout.success', ['order' => $order->id]));
});
