<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AllocateInvoiceNumberAction
{
    private const ATTEMPTS = 5;

    /**
     * Allocate the next invoice number for the current year, as YYYY-NNNN.
     *
     * Numbers are only ever handed out to paid orders, so an abandoned checkout cannot leave a
     * gap in the sequence. The unique index on invoice_number is what makes this safe when two
     * webhook deliveries race; a loser retries and picks up the next number.
     */
    public function handle(Order $order): void
    {
        if ($order->invoice_number !== null) {
            return;
        }

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                DB::transaction(function () use ($order): void {
                    $year = now()->year;

                    $last = Order::query()
                        ->where('invoice_number', 'like', $year.'-%')
                        ->orderByDesc('invoice_number')
                        ->lockForUpdate()
                        ->value('invoice_number');

                    $next = $last === null ? 1 : ((int) substr((string) $last, 5)) + 1;

                    $order->forceFill([
                        'invoice_number' => sprintf('%d-%04d', $year, $next),
                        'invoiced_at' => now(),
                    ])->save();
                });

                return;
            } catch (QueryException $e) {
                if ($attempt === self::ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
