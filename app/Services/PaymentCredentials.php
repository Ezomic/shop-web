<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Payment credentials, read from the settings table with the environment as the fallback.
 *
 * Deliberately not written back into config: the admin screen used to rewrite .env at runtime,
 * which cannot work under config:cache and fails on a deploy-owned filesystem.
 */
class PaymentCredentials
{
    private const CACHE_KEY = 'shop.payment_credentials';

    public const KEYS = ['stripe_secret', 'stripe_webhook_secret', 'mollie_key'];

    public function stripeSecret(): ?string
    {
        return $this->get('stripe_secret') ?? config('services.stripe.secret');
    }

    public function stripeWebhookSecret(): ?string
    {
        return $this->get('stripe_webhook_secret') ?? config('services.stripe.webhook_secret');
    }

    public function mollieKey(): ?string
    {
        return $this->get('mollie_key') ?? config('services.mollie.key');
    }

    public function isStored(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function store(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, strict: true) || $value === null || $value === '') {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();

        Cache::forget(self::CACHE_KEY);
    }

    private function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $values = [];

            foreach (Setting::whereIn('key', self::KEYS)->get() as $setting) {
                if (is_string($setting->value) && $setting->value !== '') {
                    $values[$setting->key] = $setting->value;
                }
            }

            return $values;
        });
    }
}
