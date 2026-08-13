<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\OrderPaymentFailedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class FailOrderAction
{
    /**
     * Record that a payment did not complete and tell the customer once.
     *
     * A late failure webhook can arrive after a successful payment, so a paid order is left
     * strictly alone. The notified timestamp is what keeps this to one message per order rather
     * than a running commentary on every state change the provider reports.
     */
    public function handle(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->forceFill(['payment_failed_at' => now()])->save();

        if ($order->failure_notified_at !== null) {
            return;
        }

        $order->forceFill(['failure_notified_at' => now()])->save();

        $order->loadMissing('customer', 'items');

        Mail::to($order->customer->email)->send(new OrderPaymentFailedMail($order));
    }
}
