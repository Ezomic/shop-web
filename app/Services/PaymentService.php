<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Mollie\Laravel\Facades\Mollie;
use Stripe\Checkout\Session as StripeSession;
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
            'metadata' => ['order_id' => $order->id],
        ]);

        $order->update(['payment_id' => $session->id]);

        return $session->url;
    }

    public function createMolliePayment(Order $order): string
    {
        $payment = Mollie::api()->payments->create([
            'amount' => [
                'currency' => $order->currency,
                'value' => number_format($order->total / 100, 2, '.', ''),
            ],
            'description' => 'Order #'.$order->id,
            'redirectUrl' => route('mollie.redirect'),
            'webhookUrl' => route('webhooks.mollie'),
            'metadata' => ['order_id' => $order->id],
        ]);

        $order->update(['payment_id' => $payment->id]);

        return $payment->getCheckoutUrl();
    }

    public function refundStripe(Order $order): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeSession::retrieve($order->payment_id);

        \Stripe\Refund::create(['payment_intent' => $session->payment_intent]);

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
