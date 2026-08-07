<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\RefundOrderAction;
use App\Actions\ReissueDownloadAction;
use App\Http\Controllers\Controller;
use App\Mail\OrderPaidMail;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
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
        $order->load('customer', 'items.downloads.productFile', 'coupon');

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'customer' => ['name' => $order->customer->name, 'email' => $order->customer->email],
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'total' => $order->total,
                'total_formatted' => $order->totalFormatted(),
                'vat_rate' => $order->vat_rate,
                'vat_amount' => $order->vat_amount,
                'net_total' => $order->net_total,
                'payment_provider' => $order->payment_provider,
                'payment_method' => $order->payment_method,
                'coupon_code' => $order->coupon?->code,
                'paid_at' => $order->paid_at?->toDateString(),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'price' => $item->price,
                    'downloads' => $item->downloads->map(fn (Download $download): array => [
                        'id' => $download->id,
                        'filename' => $download->productFile?->original_filename,
                        'count' => $download->download_count,
                    ])->values()->all(),
                ]),
            ],
        ]);
    }

    public function resend(Order $order): RedirectResponse
    {
        abort_if(! $order->isPaid(), 422);

        $order->load('items.downloads.productFile', 'customer');

        Mail::to($order->customer->email)->send(new OrderPaidMail($order));

        return back()->with('success', 'Order email resent.');
    }

    public function reissue(Order $order, Download $download, ReissueDownloadAction $reissue): RedirectResponse
    {
        abort_if($download->orderItem->order_id !== $order->id, 404);

        $reissue->handle($download);

        return back()->with('success', 'Download link regenerated. The old link no longer works.');
    }

    public function refund(Order $order, RefundOrderAction $refund): RedirectResponse
    {
        abort_if(! $order->isPaid(), 422);

        $refund->handle($order);

        return back()->with('success', 'Order refunded.');
    }
}
