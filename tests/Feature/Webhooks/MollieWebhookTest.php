<?php

declare(strict_types=1);

use App\Mail\OrderPaymentFailedMail;
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

beforeEach(function (): void {
    Mail::fake();
});

function mollieOrderWithFile(): Order
{
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();
    $order = Order::factory()->mollie()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    return $order;
}

function stubMollie(?PaymentStatus $status): void
{
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('molliePaymentStatus')->andReturn($status);

    app()->instance(PaymentService::class, $payment);
}

it('completes the order when Mollie reports it paid', function (): void {
    $order = mollieOrderWithFile();

    stubMollie(new PaymentStatus($order->id, PaymentState::Paid, 'ideal'));

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and($order->fresh()->payment_method)->toBe('ideal')
        ->and(Download::count())->toBe(1);
});

it('leaves the order alone when the payment is not paid', function (): void {
    $order = mollieOrderWithFile();

    stubMollie(new PaymentStatus($order->id, PaymentState::Failed, 'ideal'));

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(Download::count())->toBe(0);

    // Not silence: the customer hears once that the payment failed (SHOP-29).
    Mail::assertQueued(OrderPaymentFailedMail::class);
});

it('rejects a call with no payment id', function (): void {
    $this->post(route('webhooks.mollie'), [])->assertStatus(400);
});

it('acknowledges a payment id it cannot match to an order', function (): void {
    stubMollie(new PaymentStatus(null, PaymentState::Paid, 'ideal'));

    $this->post(route('webhooks.mollie'), ['id' => 'tr_unknown'])->assertOk();

    expect(Download::count())->toBe(0);
});

it('acknowledges a payment Mollie does not recognise', function (): void {
    stubMollie(null);

    $this->post(route('webhooks.mollie'), ['id' => 'tr_bogus'])->assertOk();

    expect(Download::count())->toBe(0);
});

it('is idempotent across repeat deliveries', function (): void {
    $order = mollieOrderWithFile();

    stubMollie(new PaymentStatus($order->id, PaymentState::Paid, 'ideal'));

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();
    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();

    expect(Download::count())->toBe(1);

    Mail::assertQueuedCount(1);
});
