<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use App\Services\PaymentService;

class RefundOrderAction
{
    public function __construct(private readonly PaymentService $payment) {}

    public function handle(Order $order): void
    {
        if ($order->payment_provider === 'stripe') {
            $this->payment->refundStripe($order);
        } else {
            $this->payment->refundMollie($order);
        }

        $coupon = $order->coupon;

        if ($coupon && $coupon->uses_count > 0) {
            $coupon->decrement('uses_count');
        }
    }
}
