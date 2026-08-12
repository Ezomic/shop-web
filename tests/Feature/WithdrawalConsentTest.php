<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Mail\OrderPaidMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\WithdrawalConsent;
use Illuminate\Support\Facades\Mail;

function checkoutReady(): Customer
{
    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create(['price' => 2500]));

    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('createStripeSession')->andReturn('https://stripe.test/session');
    app()->instance(PaymentService::class, $payment);

    return $customer;
}

it('refuses checkout without the acknowledgement', function (): void {
    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe'])
        ->assertSessionHasErrors('withdrawal_consent');

    expect(Order::count())->toBe(0);
});

it('refuses checkout when the box is explicitly unticked', function (): void {
    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => false])
        ->assertSessionHasErrors('withdrawal_consent');

    expect(Order::count())->toBe(0);
});

it('records the exact wording and the moment it was agreed', function (): void {
    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => true]);

    $order = Order::firstOrFail();

    expect($order->withdrawal_consent_text)->toBe(app(WithdrawalConsent::class)->text())
        ->and($order->withdrawal_consent_version)->toBe(WithdrawalConsent::VERSION)
        ->and($order->withdrawal_consent_at)->not->toBeNull();
});

it('keeps the wording that was agreed to when the text later changes', function (): void {
    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => true]);

    $agreed = Order::firstOrFail()->withdrawal_consent_text;

    app('translator')->addLines(['shop.withdrawal_consent' => 'Completely different wording'], 'en');

    expect(Order::firstOrFail()->withdrawal_consent_text)->toBe($agreed)
        ->not->toBe(app(WithdrawalConsent::class)->text());
});

it('shows the consent text on the checkout page', function (): void {
    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('withdrawal_consent_text', app(WithdrawalConsent::class)->text()));
});

it('repeats the confirmation in the order email, which is the durable medium', function (): void {
    Mail::fake();

    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create([
        'withdrawal_consent_text' => 'I want my download straight away.',
        'withdrawal_consent_version' => WithdrawalConsent::VERSION,
        'withdrawal_consent_at' => now(),
    ]);

    $html = (new OrderPaidMail($order->load('items', 'customer')))->render();

    expect($html)->toContain('I want my download straight away.');
});

it('leaves the email alone for an order with no recorded consent', function (): void {
    $order = Order::factory()->for(Customer::factory())->create(['withdrawal_consent_text' => null]);

    $html = (new OrderPaidMail($order->load('items', 'customer')))->render();

    expect($html)->not->toContain('You confirmed at checkout');
});

it('sends an empty cart back to the shop rather than complaining about the box', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe'])
        ->assertRedirect(route('shop.index'))
        ->assertSessionHasNoErrors();
});

it('serves the terms page with the supplier details and the consent wording', function (): void {
    config(['shop.supplier.name' => 'Thijssen Software', 'shop.supplier.vat_number' => 'NL123456789B01']);

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/Terms')
            ->where('supplier.name', 'Thijssen Software')
            ->where('supplier.vat_number', 'NL123456789B01')
            ->where('withdrawalConsentText', app(WithdrawalConsent::class)->text()));
});

it('serves the privacy and contact pages', function (): void {
    $this->get(route('legal.privacy'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('legal/Privacy'));

    $this->get(route('legal.contact'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('legal/Contact'));
});

it('keeps the legal pages public', function (): void {
    foreach (['legal.terms', 'legal.privacy', 'legal.contact'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

it('completes an order that carried consent', function (): void {
    Mail::fake();

    $customer = checkoutReady();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => true]);

    $order = Order::firstOrFail();
    app(CompleteOrderAction::class)->handle($order, 'card');

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and($order->fresh()->withdrawal_consent_at)->not->toBeNull();
});
