<?php

declare(strict_types=1);

use App\Mail\VatThresholdMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\VatThresholdMonitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();

    config([
        'shop.vat.home_country' => 'NL',
        'shop.vat.threshold' => 1000000,
        'shop.vat.threshold_warning' => 750000,
        'shop.vat.threshold_notify' => 'owner@example.test',
    ]);
});

function sale(string $country, int $total, string $status = 'paid', ?string $paidAt = null): Order
{
    return Order::factory()->for(Customer::factory())->create([
        'country' => $country,
        'total' => $total,
        'status' => $status,
        'paid_at' => $status === 'paid' ? ($paidAt ?? now()) : null,
    ]);
}

it('counts only sales outside the home country', function (): void {
    sale('NL', 500000);
    sale('BE', 200000);
    sale('DE', 100000);

    expect(app(VatThresholdMonitor::class)->crossBorderTotal())->toBe(300000);
});

it('ignores orders that were never paid', function (): void {
    sale('BE', 800000, 'pending');

    expect(app(VatThresholdMonitor::class)->crossBorderTotal())->toBe(0);
});

it('ignores refunded orders', function (): void {
    sale('BE', 800000, 'refunded');

    expect(app(VatThresholdMonitor::class)->crossBorderTotal())->toBe(0);
});

it('ignores sales from a previous year', function (): void {
    sale('BE', 800000, 'paid', now()->subYear()->toDateTimeString());

    expect(app(VatThresholdMonitor::class)->crossBorderTotal())->toBe(0);
});

it('ignores orders with no recorded country', function (): void {
    Order::factory()->for(Customer::factory())->create([
        'country' => null,
        'total' => 900000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(app(VatThresholdMonitor::class)->crossBorderTotal())->toBe(0);
});

it('stays quiet below the warning threshold', function (): void {
    sale('BE', 749999);

    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('warns exactly at the warning threshold', function (): void {
    sale('BE', 750000);

    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueued(VatThresholdMail::class, fn (VatThresholdMail $mail): bool => $mail->isHardLimit === false);
});

it('warns only once for the same threshold', function (): void {
    sale('BE', 750000);

    $this->artisan('shop:check-vat-threshold')->assertSuccessful();
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueuedCount(1);
});

it('stays quiet on a later run even when more sales land', function (): void {
    sale('BE', 750000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    sale('DE', 100000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueuedCount(1);
});

it('sends both warnings when the hard limit is passed in one go', function (): void {
    sale('BE', 1000000);

    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueuedCount(2);
    Mail::assertQueued(VatThresholdMail::class, fn (VatThresholdMail $mail): bool => $mail->isHardLimit === true);
});

it('warns again for the hard limit after the warning was already sent', function (): void {
    sale('BE', 800000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();
    Mail::assertQueuedCount(1);

    sale('DE', 300000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueuedCount(2);
});

it('warns again in a new calendar year', function (): void {
    sale('BE', 800000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    $this->travel(1)->years();
    sale('BE', 800000);
    $this->artisan('shop:check-vat-threshold')->assertSuccessful();

    Mail::assertQueuedCount(2);
});

it('fails clearly when no recipient is configured', function (): void {
    config(['shop.vat.threshold_notify' => null, 'shop.supplier.email' => '']);

    sale('BE', 800000);

    $this->artisan('shop:check-vat-threshold')->assertFailed();

    Mail::assertNothingQueued();
});

it('falls back to the supplier email', function (): void {
    config(['shop.vat.threshold_notify' => null, 'shop.supplier.email' => 'supplier@example.test']);

    expect(app(VatThresholdMonitor::class)->recipient())->toBe('supplier@example.test');
});

it('shows the running total to an admin', function (): void {
    sale('BE', 300000);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('vatThreshold.cross_border_total', 300000)
            ->where('vatThreshold.threshold', 1000000));
});

it('is scheduled weekly', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'shop:check-vat-threshold'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 8 * * 1');
});
