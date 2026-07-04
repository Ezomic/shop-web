<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mollie\Laravel\Facades\Mollie;

class MollieWebhookController extends Controller
{
    public function handle(Request $request, CompleteOrderAction $complete): Response
    {
        $paymentId = $request->input('id');

        if (! $paymentId) {
            return response('Missing id', 400);
        }

        $payment = Mollie::api()->payments->get($paymentId);

        if ($payment->isPaid()) {
            $order = Order::where('payment_id', $paymentId)->first();

            if ($order) {
                $complete->handle($order, $payment->method);
            }
        }

        return response('OK');
    }
}
