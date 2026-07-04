<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\OrderPaidMail;
use App\Models\Download;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CompleteOrderAction
{
    public function handle(Order $order, ?string $paymentMethod = null): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
        ]);

        $order->load('items.download');

        foreach ($order->items as $item) {
            if (! $item->download) {
                Download::create([
                    'order_item_id' => $item->id,
                    'token' => Str::uuid()->toString(),
                ]);
            }
        }

        $order->load('items.download', 'customer');

        Mail::to($order->customer->email)->send(new OrderPaidMail($order));
    }
}
