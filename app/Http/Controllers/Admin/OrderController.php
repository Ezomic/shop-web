<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::with('customer')->latest()->paginate(25);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders->through(fn ($o) => [
                'id' => $o->id,
                'customer_name' => $o->customer->name,
                'customer_email' => $o->customer->email,
                'status' => $o->status,
                'total_formatted' => $o->totalFormatted(),
                'payment_provider' => $o->payment_provider,
                'paid_at' => $o->paid_at?->toDateString(),
            ]),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load('customer', 'items.download', 'coupon');

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'customer' => ['name' => $order->customer->name, 'email' => $order->customer->email],
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'total' => $order->total,
                'total_formatted' => $order->totalFormatted(),
                'payment_provider' => $order->payment_provider,
                'payment_method' => $order->payment_method,
                'coupon_code' => $order->coupon?->code,
                'paid_at' => $order->paid_at?->toDateString(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'price' => $item->price,
                    'downloads' => $item->download?->download_count ?? 0,
                ]),
            ],
        ]);
    }

    public function refund(Order $order, PaymentService $payment): RedirectResponse
    {
        abort_if(! $order->isPaid(), 422);

        if ($order->payment_provider === 'stripe') {
            $payment->refundStripe($order);
        } else {
            $payment->refundMollie($order);
        }

        return back()->with('success', 'Order refunded.');
    }
}
