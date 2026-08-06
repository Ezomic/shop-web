<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MollieWebhookController extends Controller
{
    public function handle(Request $request, CompleteOrderAction $complete, PaymentService $payment): Response
    {
        $paymentId = $request->input('id');

        if (! is_string($paymentId) || $paymentId === '') {
            return response('Missing id', 400);
        }

        $status = $payment->molliePaymentStatus($paymentId);

        if ($status?->isPaid()) {
            $order = Order::where('payment_id', $paymentId)->first();

            if ($order) {
                $complete->handle($order, $status->method);
            }
        }

        return response('OK');
    }
}
