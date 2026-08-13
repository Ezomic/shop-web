<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('shows the account page to a signed in customer', function (): void {
    $customer = Customer::factory()->create(['name' => 'Nora Vermeer']);

    $this->actingAs($customer, 'customer')
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/Edit')
            ->where('customer.name', 'Nora Vermeer')
            ->where('customer.has_password', true));
});

it('keeps the account page behind the login', function (): void {
    $this->get(route('account.edit'))->assertRedirect(route('login'));
    $this->put(route('account.update'), [])->assertRedirect(route('login'));
    $this->put(route('account.password'), [])->assertRedirect(route('login'));
});

it('updates the name', function (): void {
    $customer = Customer::factory()->create(['name' => 'Nora Vermeer']);

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => 'Nora V', 'email' => $customer->email])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('Nora V');
});

it('leaves the verified state alone when only the name changes', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => 'Renamed', 'email' => $customer->email]);

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

it('clears verification and sends a fresh link when the email changes', function (): void {
    $customer = Customer::factory()->create(['email' => 'old@example.test']);

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => $customer->name, 'email' => 'new@example.test'])
        ->assertRedirect();

    $customer->refresh();

    expect($customer->email)->toBe('new@example.test')
        ->and($customer->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($customer, VerifyEmail::class);
});

it('refuses an email that belongs to somebody else', function (): void {
    Customer::factory()->create(['email' => 'taken@example.test']);
    $customer = Customer::factory()->create(['email' => 'mine@example.test']);

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => $customer->name, 'email' => 'taken@example.test'])
        ->assertSessionHasErrors('email');

    expect($customer->fresh()->email)->toBe('mine@example.test');
});

it('lets a customer keep their own email address', function (): void {
    $customer = Customer::factory()->create(['email' => 'mine@example.test']);

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => 'Renamed', 'email' => 'mine@example.test'])
        ->assertSessionHasNoErrors();
});

it('requires a name and a valid email', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => '', 'email' => 'nonsense'])
        ->assertSessionHasErrors(['name', 'email']);
});

it('changes the password when the current one is right', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])
        ->assertRedirect();

    expect(Hash::check('correct-horse-battery', $customer->fresh()->password))->toBeTrue();
});

it('refuses a password change with the wrong current password', function (): void {
    $customer = Customer::factory()->create();
    $before = $customer->password;

    $this->actingAs($customer, 'customer')
        ->put(route('account.password'), [
            'current_password' => 'not-the-password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])
        ->assertSessionHasErrors('current_password');

    expect($customer->fresh()->password)->toBe($before);
});

it('requires the confirmation to match', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');
});

it('rejects a weak new password', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])
        ->assertSessionHasErrors('password');
});

it('lets a claimed guest set a first password without a current one', function (): void {
    $customer = Customer::factory()->create(['password' => null]);

    $this->actingAs($customer, 'customer')
        ->get(route('account.edit'))
        ->assertInertia(fn ($page) => $page->where('customer.has_password', false));

    $this->actingAs($customer, 'customer')
        ->put(route('account.password'), [
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])
        ->assertRedirect();

    expect(Hash::check('correct-horse-battery', $customer->fresh()->password))->toBeTrue();
});

it('cannot edit anybody else', function (): void {
    $customer = Customer::factory()->create(['name' => 'Mine']);
    $other = Customer::factory()->create(['name' => 'Theirs']);

    $this->actingAs($customer, 'customer')
        ->put(route('account.update'), ['name' => 'Changed', 'email' => $customer->email]);

    expect($other->fresh()->name)->toBe('Theirs')
        ->and($customer->fresh()->name)->toBe('Changed');
});
