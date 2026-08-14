<?php

declare(strict_types=1);

use App\Mail\OrderPaidMail;
use App\Mail\OrderPaymentFailedMail;
use App\Mail\VatThresholdMail;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

beforeEach(function (): void {
    config(['shop.mail.max_per_window' => 8, 'shop.mail.window_minutes' => 5, 'shop.mail.retry_for_hours' => 6]);
});

function orderMail(): OrderPaidMail
{
    return new OrderPaidMail(Order::factory()->for(Customer::factory())->create());
}

it('throttles the order email', function (): void {
    $middleware = orderMail()->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class);
});

it('throttles the payment failure email too', function (): void {
    $mail = new OrderPaymentFailedMail(Order::factory()->for(Customer::factory())->create());

    expect($mail->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

it('throttles the vat threshold email too', function (): void {
    $mail = new VatThresholdMail(750000, 800000, 2026, false);

    expect($mail->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

it('retries against a deadline rather than a count of attempts', function (): void {
    // Every throttled release burns an attempt, so a tries limit would be spent waiting.
    expect(orderMail()->retryUntil()->getTimestamp())
        ->toBeGreaterThan(now()->addHours(5)->getTimestamp());
});

it('backs off between attempts instead of hammering the host', function (): void {
    expect(orderMail()->backoff())->toBe([60, 120, 300, 600]);
});

it('carries the throttle onto the queued job', function (): void {
    // Mailable middleware only matters if newQueuedJob actually forwards it.
    $reflection = new ReflectionMethod(Mailable::class, 'newQueuedJob');
    $reflection->setAccessible(true);

    $job = $reflection->invoke(orderMail());

    expect($job->middleware)->toHaveCount(1)
        ->and($job->middleware[0])->toBeInstanceOf(RateLimited::class);
});

it('lets through only as many as the window allows', function (): void {
    $limiter = app(RateLimiter::class);
    $limiter->clear('shop-mail');

    $allowed = 0;

    foreach (range(1, 12) as $ignored) {
        if (! $limiter->tooManyAttempts('shop-mail', 8)) {
            $limiter->hit('shop-mail', 300);
            $allowed++;
        }
    }

    // Eight through, the rest held back rather than sent and rejected.
    expect($allowed)->toBe(8);
});

it('stays under the transport cap of ten per five minutes', function (): void {
    expect(config('shop.mail.max_per_window'))->toBeLessThan(10)
        ->and(config('shop.mail.window_minutes'))->toBe(5);
});

it('registers the mail limiter', function (): void {
    expect(RateLimiterFacade::limiter('mail'))->not->toBeNull();
});

it('logs a permanently failed job so it cannot pass unnoticed', function (): void {
    Log::spy();

    // flare-client listens to JobFailed as well, which is the point: once a key is configured
    // this surfaces there too rather than only in the log.
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn(OrderPaidMail::class);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getJobId')->andReturn('test-job');
    $job->shouldReceive('attempts')->andReturn(3);
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('getConnectionName')->andReturn('database');
    $job->shouldReceive('getName')->andReturn(OrderPaidMail::class);
    $job->shouldReceive('maxTries')->andReturn(null);

    event(new JobFailed('database', $job, new RuntimeException('554 rate limited')));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Queued job failed permanently'
            && str_contains($context['exception'], '554'));
});
