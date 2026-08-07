<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReissueDownloadAction;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user('customer')
            ->orders()
            ->with('items.downloads.productFile')
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'status' => $order->status,
                'total_formatted' => $order->totalFormatted(),
                'paid_at' => $order->paid_at?->toDateString(),
                'items_count' => $order->items->count(),
            ]);

        return Inertia::render('orders/Index', ['orders' => $orders]);
    }

    public function reissue(Request $request, Order $order, Download $download, ReissueDownloadAction $reissue): RedirectResponse
    {
        abort_if($order->customer_id !== $request->user('customer')->id, 403);
        abort_if($download->orderItem->order_id !== $order->id, 404);

        $reissue->handle($download);

        return back()->with('success', 'A fresh download link is ready.');
    }

    public function invoice(Request $request, Order $order, InvoiceRenderer $invoices): SymfonyResponse
    {
        abort_if($order->customer_id !== $request->user('customer')->id, 403);
        abort_if($order->invoice_number === null, 404);

        return $invoices->pdf($order)->download($invoices->filename($order));
    }

    public function show(Request $request, Order $order): Response
    {
        abort_if($order->customer_id !== $request->user('customer')->id, 403);

        $order->load('items.downloads.productFile');

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'total' => $order->total,
                'total_formatted' => $order->totalFormatted(),
                'invoice_number' => $order->invoice_number,
                'vat_rate' => $order->vat_rate,
                'vat_amount' => $order->vat_amount,
                'net_total' => $order->net_total,
                'paid_at' => $order->paid_at?->toDateString(),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'price' => $item->price,
                    'downloads' => $item->downloads->map(fn (Download $download): array => [
                        'id' => $download->id,
                        'filename' => $download->productFile?->original_filename,
                        'url' => $download->url(),
                        'uses_left' => $download->usesLeft(),
                        'exhausted' => $download->isExhausted(),
                    ])->values()->all(),
                ]),
            ],
        ]);
    }
}
