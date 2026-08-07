<?php

declare(strict_types=1);

use App\Actions\AllocateInvoiceNumberAction;
use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use App\Services\CartService;
use App\Services\InvoiceRenderer;
use Illuminate\Support\Facades\Mail;

function paidOrder(int $price = 12100): Order
{
    Mail::fake();

    app(CartService::class)->add(Product::factory()->has(ProductFile::factory(), 'files')->create(['price' => $price]));

    $order = app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe');
    app(CompleteOrderAction::class)->handle($order, 'card');

    return $order->fresh();
}

it('allocates an invoice number when the order is paid', function (): void {
    $order = paidOrder();

    expect($order->invoice_number)->toBe(now()->year.'-0001')
        ->and($order->invoiced_at)->not->toBeNull();
});

it('does not allocate a number to an unpaid order', function (): void {
    app(CartService::class)->add(Product::factory()->create());

    $order = app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe');

    expect($order->invoice_number)->toBeNull();
});

it('numbers invoices sequentially without gaps', function (): void {
    $numbers = collect(range(1, 3))->map(fn (): string => (string) paidOrder()->invoice_number);

    expect($numbers->all())->toBe([
        now()->year.'-0001',
        now()->year.'-0002',
        now()->year.'-0003',
    ]);
});

it('leaves no hole when a checkout is abandoned between two paid orders', function (): void {
    $first = paidOrder();

    app(CartService::class)->add(Product::factory()->create());
    app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe');

    $second = paidOrder();

    expect($first->invoice_number)->toBe(now()->year.'-0001')
        ->and($second->invoice_number)->toBe(now()->year.'-0002');
});

it('allocates exactly one number when the webhook is replayed', function (): void {
    $order = paidOrder();
    $number = $order->invoice_number;

    app(CompleteOrderAction::class)->handle($order->fresh(), 'card');

    expect($order->fresh()->invoice_number)->toBe($number)
        ->and(Order::whereNotNull('invoice_number')->count())->toBe(1);
});

it('restarts the sequence in a new year', function (): void {
    $this->travelTo(now()->setDate(2026, 12, 31));
    $last = paidOrder();

    $this->travelTo(now()->setDate(2027, 1, 2));
    $first = paidOrder();

    expect($last->invoice_number)->toBe('2026-0001')
        ->and($first->invoice_number)->toBe('2027-0001');
});

it('never reissues a number to an order that already has one', function (): void {
    $order = paidOrder();

    app(AllocateInvoiceNumberAction::class)->handle($order->fresh());

    expect($order->fresh()->invoice_number)->toBe(now()->year.'-0001');
});

it('renders a pdf the customer can download', function (): void {
    $order = paidOrder();

    $response = $this->actingAs($order->customer, 'customer')->get(route('orders.invoice', $order));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

it('refuses an invoice belonging to another customer', function (): void {
    $order = paidOrder();

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(route('orders.invoice', $order))
        ->assertForbidden();
});

it('404s when the order has no invoice yet', function (): void {
    app(CartService::class)->add(Product::factory()->create());
    $order = app(CreateOrderAction::class)->handle(Customer::factory()->create(), 'stripe');

    $this->actingAs($order->customer, 'customer')
        ->get(route('orders.invoice', $order))
        ->assertNotFound();
});

it('lets an admin download any invoice', function (): void {
    $order = paidOrder();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.orders.invoice', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('keeps the admin invoice behind the admin guard', function (): void {
    $order = paidOrder();

    $this->get(route('admin.orders.invoice', $order))->assertRedirect(route('admin.login'));
});

it('prints the snapshot, not live product data', function (): void {
    $order = paidOrder();
    $product = Product::firstOrFail();

    $product->update(['name' => ['en' => 'Renamed Later', 'nl' => 'Later hernoemd'], 'price' => 99999]);
    config(['shop.vat.rate' => 9]);

    $html = view('invoices.order', ['order' => $order->fresh()->load('customer', 'items')])->render();

    expect($html)->toContain($order->items->first()->product_name)
        ->not->toContain('Renamed Later')
        ->and($html)->toContain('21%');
});

it('carries the supplier identity onto the invoice', function (): void {
    config([
        'shop.supplier.name' => 'Thijssen Software',
        'shop.supplier.vat_number' => 'NL123456789B01',
        'shop.supplier.coc_number' => '12345678',
    ]);

    $order = paidOrder();
    $html = view('invoices.order', ['order' => $order->load('customer', 'items')])->render();

    expect($html)->toContain('Thijssen Software')
        ->toContain('NL123456789B01')
        ->toContain('12345678');
});

it('names the file after the invoice number', function (): void {
    $order = paidOrder();

    expect(app(InvoiceRenderer::class)->filename($order))->toBe('invoice-'.$order->invoice_number.'.pdf');
});
