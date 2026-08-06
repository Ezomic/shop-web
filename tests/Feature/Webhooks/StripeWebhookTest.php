<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\PaymentCredentials;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function (): void {
    Mail::fake();

    app(PaymentCredentials::class)->store([
        'stripe_secret' => 'sk_test_secret',
        'stripe_webhook_secret' => WEBHOOK_SECRET,
    ]);
});

function stripePayload(int $orderId): string
{
    return json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_method_types' => ['card'],
            'metadata' => ['order_id' => (string) $orderId],
        ]],
    ], JSON_THROW_ON_ERROR);
}

function stripeSignature(string $payload, string $secret = WEBHOOK_SECRET): string
{
    $timestamp = time();

    return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
}

function payableOrder(): Order
{
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    return $order;
}

function postStripeWebhook(string $payload, string $signature): TestResponse
{
    return test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );
}

it('completes the order on a signed checkout.session.completed', function (): void {
    $order = payableOrder();
    $payload = stripePayload($order->id);

    postStripeWebhook($payload, stripeSignature($payload))->assertOk();

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(Download::count())->toBe(1);
});

it('rejects a payload with a bad signature', function (): void {
    $order = payableOrder();
    $payload = stripePayload($order->id);

    postStripeWebhook($payload, stripeSignature($payload, 'whsec_wrong'))->assertStatus(400);

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(Download::count())->toBe(0);
});

it('rejects a payload with no signature header', function (): void {
    $order = payableOrder();

    postStripeWebhook(stripePayload($order->id), '')->assertStatus(400);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('rejects a tampered payload', function (): void {
    $order = payableOrder();
    $payload = stripePayload($order->id);
    $signature = stripeSignature($payload);

    postStripeWebhook(str_replace('cs_test_123', 'cs_test_evil', $payload), $signature)->assertStatus(400);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('is idempotent when the same event is delivered twice', function (): void {
    $order = payableOrder();
    $payload = stripePayload($order->id);

    postStripeWebhook($payload, stripeSignature($payload))->assertOk();
    postStripeWebhook($payload, stripeSignature($payload))->assertOk();

    expect(Download::count())->toBe(1);

    Mail::assertQueuedCount(1);
});

it('acknowledges an event for an order it does not know', function (): void {
    $payload = stripePayload(99999);

    postStripeWebhook($payload, stripeSignature($payload))->assertOk();

    expect(Download::count())->toBe(0);
});

it('ignores an event type it does not handle', function (): void {
    $order = payableOrder();
    $payload = str_replace('checkout.session.completed', 'payment_intent.created', stripePayload($order->id));

    postStripeWebhook($payload, stripeSignature($payload))->assertOk();

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('is exempt from csrf', function (): void {
    $order = payableOrder();
    $payload = stripePayload($order->id);

    postStripeWebhook($payload, stripeSignature($payload))->assertOk();
})->group('csrf');
