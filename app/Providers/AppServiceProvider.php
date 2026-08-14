<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Kept under the SMTP host's own cap so a burst is delayed rather than rejected. See
        // config/shop.php and SHOP-32.
        RateLimiter::for('mail', fn () => Limit::perMinutes(
            max(1, (int) config('shop.mail.window_minutes')),
            max(1, (int) config('shop.mail.max_per_window')),
        )->by('shop-mail'));

        // A queued mail that gives up is a customer who never got their download links, so it has
        // to be noticed rather than sitting quietly in failed_jobs.
        Queue::failing(function (JobFailed $event): void {
            Log::error('Queued job failed permanently', [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
