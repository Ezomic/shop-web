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
    public function __construct(
        private readonly AllocateInvoiceNumberAction $allocateInvoiceNumber,
    ) {}

    public function handle(Order $order, ?string $paymentMethod = null, ?string $country = null): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            // A provider sourced country is third party evidence of where the customer is.
            // Without one we fall back to the home country and say so, rather than dressing a
            // guess up as evidence.
            'country' => $country ?? config('shop.vat.home_country'),
            'country_source' => $country === null ? 'fallback' : $order->payment_provider,
        ]);

        // Counted here rather than at order creation: an abandoned checkout must not burn a use.
        $order->coupon?->increment('uses_count');

        // Same reasoning for the invoice number: only a paid order gets one, so the sequence
        // never has holes.
        $this->allocateInvoiceNumber->handle($order);

        $order->load('items.downloads', 'items.product.files');

        foreach ($order->items as $item) {
            $existing = $item->downloads->pluck('product_file_id')->all();

            foreach ($item->product->files as $file) {
                if (! in_array($file->id, $existing, strict: true)) {
                    Download::create([
                        'order_item_id' => $item->id,
                        'product_file_id' => $file->id,
                        'token' => Str::uuid()->toString(),
                    ]);
                }
            }
        }

        $order->load('items.downloads.productFile', 'customer');

        Mail::to($order->customer->email)->send(new OrderPaidMail($order));
    }
}
