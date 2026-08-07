<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

/**
 * Watches the EUR 10.000 a year of cross border B2C sales that forces destination based VAT and
 * an OSS registration. Everything it needs is already on paid orders: SHOP-15 put the totals
 * there and SHOP-17 put the country there.
 */
class VatThresholdMonitor
{
    public function homeCountry(): string
    {
        return (string) config('shop.vat.home_country');
    }

    /**
     * Paid, non refunded sales to customers outside the home country, this calendar year.
     */
    public function crossBorderTotal(?int $year = null): int
    {
        $year ??= now()->year;

        return (int) Order::query()
            ->where('status', 'paid')
            ->whereNotNull('country')
            ->where('country', '!=', $this->homeCountry())
            ->whereYear('paid_at', $year)
            ->sum('total');
    }

    /**
     * Thresholds already crossed but not yet announced, lowest first.
     *
     * @return list<int>
     */
    public function unannouncedThresholds(?int $year = null): array
    {
        $year ??= now()->year;
        $total = $this->crossBorderTotal($year);

        $thresholds = array_filter([
            (int) config('shop.vat.threshold_warning'),
            (int) config('shop.vat.threshold'),
        ], fn (int $threshold): bool => $threshold > 0);

        sort($thresholds);

        return array_values(array_filter(
            $thresholds,
            fn (int $threshold): bool => $total >= $threshold && ! $this->alreadyAnnounced($year, $threshold),
        ));
    }

    public function markAnnounced(int $year, int $threshold): void
    {
        Setting::updateOrCreate(
            ['key' => $this->announcementKey($year, $threshold)],
            ['value' => now()->toIso8601String()],
        );
    }

    public function alreadyAnnounced(int $year, int $threshold): bool
    {
        return Setting::where('key', $this->announcementKey($year, $threshold))->exists();
    }

    public function recipient(): ?string
    {
        $configured = config('shop.vat.threshold_notify') ?? config('shop.supplier.email');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function announcementKey(int $year, int $threshold): string
    {
        return "vat_threshold_announced_{$year}_{$threshold}";
    }
}
