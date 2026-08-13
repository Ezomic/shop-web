<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything here counts paid orders only. A refunded order is money that was given back, so
 * quietly leaving it in revenue would overstate the takings; refunds are reported separately.
 */
class SalesReport
{
    /**
     * @return array{orders: int, gross: int, net: int, vat: int, average: int}
     */
    public function totals(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $query = $this->paid($from, $to);

        $orders = (clone $query)->count();
        $gross = (int) (clone $query)->sum('total');
        $vat = (int) (clone $query)->sum('vat_amount');

        return [
            'orders' => $orders,
            'gross' => $gross,
            'net' => $gross - $vat,
            'vat' => $vat,
            'average' => $orders === 0 ? 0 : (int) round($gross / $orders),
        ];
    }

    /**
     * @return array{orders: int, gross: int}
     */
    public function refunded(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $query = Order::query()->where('status', 'refunded');

        $this->constrainToPeriod($query, $from, $to);

        return [
            'orders' => (clone $query)->count(),
            'gross' => (int) (clone $query)->sum('total'),
        ];
    }

    /**
     * @return list<array{name: string, sold: int, gross: int}>
     */
    public function bestSellers(?CarbonImmutable $from = null, ?CarbonImmutable $to = null, int $limit = 5): array
    {
        $rows = OrderItem::query()
            ->selectRaw('product_name, count(*) as sold_count, sum(net_price + vat_amount) as gross_total')
            ->whereHas('order', function (Builder $query) use ($from, $to): void {
                /** @var Builder<Order> $query */
                $query->where('status', 'paid');
                $this->constrainToPeriod($query, $from, $to);
            })
            ->groupBy('product_name')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get()
            ->map(fn (OrderItem $row): array => [
                'name' => $row->product_name,
                'sold' => (int) $row->getAttribute('sold_count'),
                'gross' => (int) $row->getAttribute('gross_total'),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * The shape an OSS return asks for: per quarter, per country, net and VAT.
     *
     * @return list<array{quarter: string, country: string, orders: int, net: int, vat: int, gross: int}>
     */
    public function byQuarterAndCountry(?int $year = null): array
    {
        $year ??= now()->year;

        $grouped = $this->paid(
            CarbonImmutable::create($year, 1, 1)->startOfDay(),
            CarbonImmutable::create($year, 12, 31)->endOfDay(),
        )
            ->get()
            ->groupBy(fn (Order $order): string => $year.'-Q'.$order->paid_at->quarter.'|'.($order->country ?? '??'))
            ->map(function ($orders, string $key): array {
                [$quarter, $country] = explode('|', $key);
                $gross = (int) $orders->sum('total');
                $vat = (int) $orders->sum('vat_amount');

                return [
                    'quarter' => $quarter,
                    'country' => $country,
                    'orders' => (int) $orders->count(),
                    'net' => $gross - $vat,
                    'vat' => $vat,
                    'gross' => $gross,
                ];
            })
            ->sortBy(fn (array $row): string => $row['quarter'].$row['country'])
            ->values()
            ->all();

        return array_values($grouped);
    }

    /**
     * @return list<array<int, string|int>>
     */
    public function rows(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        return array_values($this->paid($from, $to)
            ->orderBy('paid_at')
            ->get()
            ->map(fn (Order $order): array => [
                $order->id,
                (string) ($order->invoice_number ?? ''),
                $order->paid_at->toDateString(),
                (string) ($order->country ?? ''),
                $this->money($order->net_total),
                $order->vat_rate,
                $this->money($order->vat_amount),
                $this->money($order->total),
                $order->payment_provider,
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return ['order', 'invoice', 'paid_on', 'country', 'net', 'vat_rate', 'vat', 'gross', 'provider'];
    }

    /**
     * @return Builder<Order>
     */
    private function paid(?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        $query = Order::query()->where('status', 'paid');

        $this->constrainToPeriod($query, $from, $to);

        return $query;
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function constrainToPeriod(Builder $query, ?CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        if ($from !== null) {
            $query->where('paid_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('paid_at', '<=', $to);
        }
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
