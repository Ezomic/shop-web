<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Models\Order;
use App\Services\PaymentCredentials;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, CompleteOrderAction $complete, PaymentCredentials $credentials): Response
    {
        Stripe::setApiKey($credentials->stripeSecret());

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                (string) $credentials->stripeWebhookSecret(),
            );
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        $session = $event->data->object;

        if ($event->type === 'checkout.session.completed' && $session instanceof Session) {
            $order = Order::find((int) $session->metadata['order_id']);

            if ($order) {
                $complete->handle($order, $session->payment_method_types[0] ?? null);
            }
        }

        return response('OK');
    }
}
