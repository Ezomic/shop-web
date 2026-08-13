<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ExpireStaleOrdersCommand extends Command
{
    protected $signature = 'shop:expire-orders {--days= : Override how long a pending order may sit}';

    protected $description = 'Retire orders that were created but never paid';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('shop.orders.expire_pending_after_days'));

        if ($days <= 0) {
            $this->line('Order expiry is switched off.');

            return self::SUCCESS;
        }

        // Only ever pending: a paid or refunded order is history and must not be touched.
        $expired = Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays($days))
            ->update(['status' => 'expired']);

        $this->info("Expired {$expired} order(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
