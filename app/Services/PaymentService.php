<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Mollie\Api\Exceptions\MollieException;
use Mollie\Laravel\Facades\Mollie;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class PaymentService
{
    public function createStripeSession(Order $order): string
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $lineItems = $order->items->map(fn ($item) => [
            'price_data' => [
                'currency' => strtolower($order->currency),
                'unit_amount' => $item->price,
                'product_data' => ['name' => $item->product_name],
            ],
            'quantity' => 1,
        ])->values()->all();

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel'),
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->update(['payment_id' => $session->id]);

        return $session->url;
    }

    public function stripeSessionStatus(string $sessionId): ?PaymentStatus
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (ApiErrorException) {
            return null;
        }

        $orderId = $session->metadata['order_id'] ?? null;

        return new PaymentStatus(
            orderId: $orderId === null ? null : (int) $orderId,
            state: match (true) {
                $session->payment_status === 'paid' => PaymentState::Paid,
                $session->status === 'expired' => PaymentState::Failed,
                default => PaymentState::Pending,
            },
            method: $session->payment_method_types[0] ?? null,
        );
    }

    public function molliePaymentStatus(string $paymentId): ?PaymentStatus
    {
        try {
            $payment = Mollie::api()->payments->get($paymentId);
        } catch (MollieException) {
            return null;
        }

        $orderId = $payment->metadata->order_id ?? null;

        return new PaymentStatus(
            orderId: $orderId === null ? null : (int) $orderId,
            state: match (true) {
                $payment->isPaid() => PaymentState::Paid,
                $payment->isCanceled(), $payment->isExpired(), $payment->isFailed() => PaymentState::Failed,
                default => PaymentState::Pending,
            },
            method: $payment->method,
        );
    }

    public function createMolliePayment(Order $order): string
    {
        $payment = Mollie::api()->payments->create([
            'amount' => [
                'currency' => $order->currency,
                'value' => number_format($order->total / 100, 2, '.', ''),
            ],
            'description' => 'Order #'.$order->id,
            'redirectUrl' => route('checkout.mollie', ['order' => $order->id]),
            'webhookUrl' => route('webhooks.mollie'),
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->update(['payment_id' => $payment->id]);

        return $payment->getCheckoutUrl();
    }

    public function refundStripe(Order $order): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeSession::retrieve((string) $order->payment_id);
        $paymentIntent = $session->payment_intent;
        $paymentIntentId = $paymentIntent instanceof PaymentIntent
            ? $paymentIntent->id
            : $paymentIntent;

        abort_if(! is_string($paymentIntentId), 500, 'Stripe session has no payment intent to refund.');

        Refund::create(['payment_intent' => $paymentIntentId]);

        $order->update(['status' => 'refunded']);
    }

    public function refundMollie(Order $order): void
    {
        $payment = Mollie::api()->payments->get($order->payment_id);

        $payment->refund([
            'amount' => [
                'currency' => $order->currency,
                'value' => number_format($order->total / 100, 2, '.', ''),
            ],
        ]);

        $order->update(['status' => 'refunded']);
    }
}
