<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Keeps queued mail under the transport's own rate limit.
 *
 * Without this a burst is rejected by the SMTP host with a 554, the job exhausts its attempts and
 * the message is lost. For an order email that means the download link never arrives, so a
 * throttled message is delayed and retried for hours rather than dropped.
 */
trait Throttled
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('mail')];
    }

    /**
     * Retry against a deadline rather than a count. Every throttled release counts as an attempt,
     * so a tries limit would be spent waiting in the queue rather than on real failures.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(max(1, (int) config('shop.mail.retry_for_hours')));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 120, 300, 600];
    }
}
