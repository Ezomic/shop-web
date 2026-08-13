<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Actions\FailOrderAction;
use App\Mail\OrderPaidMail;
use App\Mail\OrderPaymentFailedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\PaymentService;
use App\Services\PaymentState;
use App\Services\PaymentStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Mail::fake();
});

function pendingOrder(string $provider = 'mollie'): Order
{
    $order = $provider === 'mollie'
        ? Order::factory()->mollie()->for(Customer::factory())->create()
        : Order::factory()->for(Customer::factory())->create();

    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    return $order;
}

it('tells the customer once when a payment fails', function (): void {
    $order = pendingOrder();

    app(FailOrderAction::class)->handle($order);

    Mail::assertQueued(OrderPaymentFailedMail::class, fn ($mail) => $mail->hasTo($order->customer->email));

    expect($order->fresh()->payment_failed_at)->not->toBeNull()
        ->and($order->fresh()->failure_notified_at)->not->toBeNull();
});

it('does not send a second mail when the failure is reported again', function (): void {
    $order = pendingOrder();

    app(FailOrderAction::class)->handle($order);
    app(FailOrderAction::class)->handle($order->fresh());

    Mail::assertQueuedCount(1);
});

it('never mails about a failure for an order that was paid', function (): void {
    $order = pendingOrder();
    app(CompleteOrderAction::class)->handle($order, 'card');

    // A late failure webhook arriving after the payment succeeded.
    app(FailOrderAction::class)->handle($order->fresh());

    Mail::assertNotQueued(OrderPaymentFailedMail::class);
    Mail::assertQueued(OrderPaidMail::class);

    expect($order->fresh()->payment_failed_at)->toBeNull();
});

it('mails on a failed mollie webhook', function (): void {
    $order = pendingOrder();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('molliePaymentStatus')
        ->andReturn(new PaymentStatus($order->id, PaymentState::Failed, 'ideal'));
    app()->instance(PaymentService::class, $payment);

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id])->assertOk();

    Mail::assertQueued(OrderPaymentFailedMail::class);
});

it('sends one mail even when the webhook is delivered twice', function (): void {
    $order = pendingOrder();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('molliePaymentStatus')
        ->andReturn(new PaymentStatus($order->id, PaymentState::Failed, 'ideal'));
    app()->instance(PaymentService::class, $payment);

    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id]);
    $this->post(route('webhooks.mollie'), ['id' => $order->payment_id]);

    Mail::assertQueuedCount(1);
});

it('retries the same order rather than creating a new one', function (): void {
    $order = pendingOrder();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('createMolliePayment')->once()->andReturn('https://mollie.test/pay');
    app()->instance(PaymentService::class, $payment);

    $this->get(URL::temporarySignedRoute('checkout.retry', now()->addDay(), ['order' => $order->id]))
        ->assertRedirect('https://mollie.test/pay');

    expect(Order::count())->toBe(1);
});

it('sends a retry of an already paid order to the success page', function (): void {
    $order = pendingOrder();
    app(CompleteOrderAction::class)->handle($order, 'card');

    $this->get(URL::temporarySignedRoute('checkout.retry', now()->addDay(), ['order' => $order->id]))
        ->assertRedirect(route('checkout.success', ['order' => $order->id]));
});

it('refuses a retry link that is not signed and not owned', function (): void {
    $order = pendingOrder();

    $this->get(route('checkout.retry', $order))->assertForbidden();
});

it('lets the owner retry from their own session', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->mollie()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('createMolliePayment')->andReturn('https://mollie.test/pay');
    app()->instance(PaymentService::class, $payment);

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.retry', $order))
        ->assertRedirect('https://mollie.test/pay');
});

it('refuses to retry an expired order', function (): void {
    $order = pendingOrder();
    $order->forceFill(['status' => 'expired'])->save();

    $this->get(URL::temporarySignedRoute('checkout.retry', now()->addDay(), ['order' => $order->id]))
        ->assertStatus(410);
});

it('carries a working retry link in the failure mail', function (): void {
    $order = pendingOrder();

    $html = (new OrderPaymentFailedMail($order->load('customer', 'items')))->render();

    expect($html)->toContain('/checkout/'.$order->id.'/retry')
        ->toContain('signature=');
});

it('expires pending orders that were never paid', function (): void {
    $stale = pendingOrder();
    $stale->forceFill(['created_at' => now()->subDays(40)])->save();

    $recent = pendingOrder();

    $this->artisan('shop:expire-orders', ['--days' => 30])->assertSuccessful();

    expect($stale->fresh()->status)->toBe('expired')
        ->and($recent->fresh()->status)->toBe('pending');
});

it('never expires an order that was paid', function (): void {
    $order = pendingOrder();
    app(CompleteOrderAction::class)->handle($order, 'card');
    $order->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->artisan('shop:expire-orders', ['--days' => 30])->assertSuccessful();

    expect($order->fresh()->status)->toBe('paid');
});

it('leaves everything alone when expiry is switched off', function (): void {
    $order = pendingOrder();
    $order->forceFill(['created_at' => now()->subYears(2)])->save();

    $this->artisan('shop:expire-orders', ['--days' => 0])->assertSuccessful();

    expect($order->fresh()->status)->toBe('pending');
});

it('schedules the expiry daily', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'shop:expire-orders'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('40 3 * * *');
});
