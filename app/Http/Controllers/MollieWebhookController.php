<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Actions\FailOrderAction;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MollieWebhookController extends Controller
{
    public function handle(Request $request, CompleteOrderAction $complete, PaymentService $payment, FailOrderAction $fail): Response
    {
        $paymentId = $request->input('id');

        if (! is_string($paymentId) || $paymentId === '') {
            return response('Missing id', 400);
        }

        $status = $payment->molliePaymentStatus($paymentId);

        $order = Order::where('payment_id', $paymentId)->first();

        if ($order && $status?->isPaid()) {
            $complete->handle($order, $status->method, $status->country);
        }

        if ($order && $status?->hasFailed()) {
            $fail->handle($order);
        }

        return response('OK');
    }
}
