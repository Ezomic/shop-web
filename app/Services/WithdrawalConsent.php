<?php

declare(strict_types=1);

namespace App\Services;

/**
 * EU consumers have a 14 day right of withdrawal. For downloadable content it only falls away if
 * the buyer gives prior express consent to immediate supply, acknowledges that they thereby lose
 * the right, and receives that confirmation on a durable medium (the order email).
 *
 * The version is bumped whenever the wording changes, so an old order can still be shown the text
 * that was actually agreed to rather than today's.
 */
class WithdrawalConsent
{
    public const VERSION = '2026-08-12';

    public function version(): string
    {
        return self::VERSION;
    }

    public function text(?string $locale = null): string
    {
        return trans('shop.withdrawal_consent', locale: $locale ?? app()->getLocale());
    }
}
