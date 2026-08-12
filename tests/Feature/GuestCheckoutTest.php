<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function stubStripe(): void
{
    $payment = Mockery::mock(PaymentService::class);
    $payment->shouldReceive('createStripeSession')->andReturn('https://stripe.test/session');
    app()->instance(PaymentService::class, $payment);
}

function guestBuys(string $email = 'guest@example.test', string $name = 'Guest Buyer'): Order
{
    Mail::fake();
    stubStripe();

    app(CartService::class)->add(Product::factory()->has(ProductFile::factory(), 'files')->create(['price' => 2500]));

    test()->post(route('checkout.store'), [
        'provider' => 'stripe',
        'withdrawal_consent' => true,
        'email' => $email,
        'name' => $name,
    ]);

    return Order::firstOrFail();
}

it('lets somebody buy without an account', function (): void {
    $order = guestBuys();

    expect($order->customer->email)->toBe('guest@example.test')
        ->and($order->customer->isGuest())->toBeTrue()
        ->and($order->customer->password)->toBeNull();
});

it('requires an email and a name from a guest', function (): void {
    app(CartService::class)->add(Product::factory()->create());

    $this->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => true])
        ->assertSessionHasErrors(['email', 'name']);

    expect(Order::count())->toBe(0);
});

it('rejects a malformed email', function (): void {
    app(CartService::class)->add(Product::factory()->create());

    $this->post(route('checkout.store'), [
        'provider' => 'stripe',
        'withdrawal_consent' => true,
        'email' => 'not-an-email',
        'name' => 'Guest',
    ])->assertSessionHasErrors('email');
});

it('does not ask a logged in customer for an email again', function (): void {
    Mail::fake();
    stubStripe();

    $customer = Customer::factory()->create();
    app(CartService::class)->add(Product::factory()->create());

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), ['provider' => 'stripe', 'withdrawal_consent' => true])
        ->assertRedirect();

    expect(Order::firstOrFail()->customer_id)->toBe($customer->id);
});

it('delivers download links to the guest by email', function (): void {
    $order = guestBuys();

    app(CompleteOrderAction::class)->handle($order, 'card');

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and($order->fresh()->items->first()->downloads)->toHaveCount(1);
});

it('lets the guest see the success page for the order they just made', function (): void {
    $order = guestBuys();

    $this->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('claimable', true)->where('email', 'guest@example.test'));
});

it('refuses the success page for an order a different session made', function (): void {
    $order = guestBuys();

    // A fresh session, as a different visitor entirely.
    $this->flushSession();

    $this->get(route('checkout.success', ['order' => $order->id]))->assertForbidden();
});

it('attaches a guest order to an existing account without handing over a session', function (): void {
    $existing = Customer::factory()->create(['email' => 'nora@example.test']);
    Order::factory()->for($existing)->create();

    $order = guestBuys('nora@example.test', 'Someone Else');

    expect($order->customer_id)->toBe($existing->id);

    // The guest is not logged in, so the account order history stays out of reach.
    $this->assertGuest('customer');
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

it('never offers to claim an address that already has a real account', function (): void {
    Customer::factory()->create(['email' => 'nora@example.test']);

    $order = guestBuys('nora@example.test', 'Someone Else');

    $this->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('claimable', false));
});

it('refuses to overwrite the password of an existing account', function (): void {
    $existing = Customer::factory()->create(['email' => 'nora@example.test']);
    $originalHash = $existing->password;

    $order = guestBuys('nora@example.test', 'Someone Else');

    $this->post(route('checkout.claim', $order), [
        'password' => 'attacker-chosen-password',
        'password_confirmation' => 'attacker-chosen-password',
    ])->assertForbidden();

    expect($existing->fresh()->password)->toBe($originalHash);
});

it('turns the guest row into a real account when a password is set', function (): void {
    $order = guestBuys();

    $this->post(route('checkout.claim', $order), [
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('orders.index'));

    $customer = $order->customer->fresh();

    expect($customer->isGuest())->toBeFalse()
        ->and(Hash::check('correct-horse-battery', $customer->password))->toBeTrue();

    $this->assertAuthenticatedAs($customer, 'customer');
});

it('shows the claimed order in the new account', function (): void {
    $order = guestBuys();

    $this->post(route('checkout.claim', $order), [
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

it('refuses to claim an order from another session', function (): void {
    $order = guestBuys();

    $this->flushSession();

    $this->post(route('checkout.claim', $order), [
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertForbidden();

    expect($order->customer->fresh()->isGuest())->toBeTrue();
});

it('rejects a weak password when claiming', function (): void {
    $order = guestBuys();

    $this->post(route('checkout.claim', $order), ['password' => 'abc', 'password_confirmation' => 'abc'])
        ->assertSessionHasErrors('password');

    expect($order->customer->fresh()->isGuest())->toBeTrue();
});

it('cannot log in as a guest row before a password is set', function (): void {
    guestBuys();

    $this->post('/login', ['email' => 'guest@example.test', 'password' => ''])
        ->assertSessionHasErrors();

    $this->assertGuest('customer');
});
