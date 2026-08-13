<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SalesReport;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

function paidSale(int $gross, int $vat, string $country = 'NL', ?string $paidAt = null): Order
{
    $order = Order::factory()->for(Customer::factory())->create([
        'status' => 'paid',
        'paid_at' => $paidAt ?? now(),
        'total' => $gross,
        'vat_amount' => $vat,
        'net_total' => $gross - $vat,
        'vat_rate' => 21,
        'country' => $country,
        'invoice_number' => now()->year.'-'.str_pad((string) (Order::count() + 1), 4, '0', STR_PAD_LEFT),
    ]);

    OrderItem::factory()->for($order)->create([
        'price' => $gross,
        'net_price' => $gross - $vat,
        'vat_amount' => $vat,
        'product_name' => 'Stage Fright',
    ]);

    return $order;
}

it('totals paid orders', function (): void {
    paidSale(12100, 2100);
    paidSale(6050, 1050);

    $totals = app(SalesReport::class)->totals();

    expect($totals['orders'])->toBe(2)
        ->and($totals['gross'])->toBe(18150)
        ->and($totals['vat'])->toBe(3150)
        ->and($totals['net'])->toBe(15000)
        ->and($totals['average'])->toBe(9075);
});

it('leaves refunded money out of revenue and reports it separately', function (): void {
    paidSale(12100, 2100);

    $refunded = Order::factory()->for(Customer::factory())->create([
        'status' => 'refunded',
        'paid_at' => now(),
        'total' => 5000,
    ]);

    $report = app(SalesReport::class);

    expect($report->totals()['gross'])->toBe(12100)
        ->and($report->refunded()['orders'])->toBe(1)
        ->and($report->refunded()['gross'])->toBe(5000)
        ->and($refunded->status)->toBe('refunded');
});

it('leaves pending orders out entirely', function (): void {
    paidSale(12100, 2100);
    Order::factory()->for(Customer::factory())->create(['status' => 'pending', 'total' => 9999]);

    expect(app(SalesReport::class)->totals()['gross'])->toBe(12100);
});

it('honours the period', function (): void {
    paidSale(10000, 1735, 'NL', now()->subMonths(3)->toDateTimeString());
    paidSale(20000, 3471);

    $totals = app(SalesReport::class)->totals(now()->startOfMonth()->toImmutable(), now()->toImmutable());

    expect($totals['orders'])->toBe(1)->and($totals['gross'])->toBe(20000);
});

it('reports an average of zero rather than dividing by nothing', function (): void {
    expect(app(SalesReport::class)->totals()['average'])->toBe(0);
});

it('ranks best sellers by how many sold', function (): void {
    paidSale(1000, 174);
    paidSale(1000, 174);

    $order = paidSale(5000, 868);
    $order->items()->update(['product_name' => 'Closing Night']);

    $best = app(SalesReport::class)->bestSellers();

    expect($best[0]['name'])->toBe('Stage Fright')
        ->and($best[0]['sold'])->toBe(2);
});

it('groups by quarter and country the way an oss return wants', function (): void {
    paidSale(12100, 2100, 'NL', now()->startOfYear()->addDays(5)->toDateTimeString());
    paidSale(12100, 2100, 'BE', now()->startOfYear()->addDays(6)->toDateTimeString());
    paidSale(12100, 2100, 'BE', now()->startOfYear()->addDays(7)->toDateTimeString());

    $rows = collect(app(SalesReport::class)->byQuarterAndCountry(now()->year));

    $belgium = $rows->firstWhere('country', 'BE');

    expect($rows)->toHaveCount(2)
        ->and($belgium['orders'])->toBe(2)
        ->and($belgium['gross'])->toBe(24200)
        ->and($belgium['vat'])->toBe(4200)
        ->and($belgium['net'])->toBe(20000);
});

it('shows the report to an admin', function (): void {
    paidSale(12100, 2100);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Reports')
            ->where('year.gross', 12100)
            ->has('quarters', 1));
});

it('keeps the report behind the admin guard', function (): void {
    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.reports.export'))->assertRedirect(route('admin.login'));
});

it('exports a csv that reconciles with the invoices', function (): void {
    $order = paidSale(12100, 2100, 'BE');

    $response = $this->actingAs($this->admin)->get(route('admin.reports.export'));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($lines[0])->toBe('order,invoice,paid_on,country,net,vat_rate,vat,gross,provider')
        ->and($lines[1])->toContain((string) $order->id)
        ->and($lines[1])->toContain($order->invoice_number)
        ->and($lines[1])->toContain('BE')
        ->and($lines[1])->toContain('100.00')
        ->and($lines[1])->toContain('21.00')
        ->and($lines[1])->toContain('121.00');
});

it('exports only the selected period', function (): void {
    paidSale(10000, 1735, 'NL', now()->subYear()->toDateTimeString());
    paidSale(20000, 3471);

    $csv = $this->actingAs($this->admin)
        ->get(route('admin.reports.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->streamedContent();

    $lines = array_values(array_filter(explode("\n", trim($csv))));

    // Header plus exactly one row.
    expect($lines)->toHaveCount(2);
});

it('exports a header even with nothing to report', function (): void {
    $csv = $this->actingAs($this->admin)->get(route('admin.reports.export'))->streamedContent();

    expect(trim($csv))->toBe('order,invoice,paid_on,country,net,vat_rate,vat,gross,provider');
});
