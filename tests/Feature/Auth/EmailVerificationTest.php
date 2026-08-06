<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(Customer $customer): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $customer->getKey(),
        'hash' => sha1($customer->getEmailForVerification()),
    ]);
}

it('shows the notice to an unverified customer', function (): void {
    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('verification.notice'))
        ->assertOk();
});

it('redirects a verified customer away from the notice', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('verification.notice'))
        ->assertRedirect(route('orders.index'));
});

it('verifies the email from a signed link', function (): void {
    Event::fake();

    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->get(verificationUrl($customer))
        ->assertRedirect(route('orders.index'));

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('rejects an unsigned verification link', function (): void {
    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('verification.verify', ['id' => $customer->getKey(), 'hash' => sha1($customer->email)]))
        ->assertForbidden();

    expect($customer->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a link signed for another customer', function (): void {
    $customer = Customer::factory()->unverified()->create();
    $other = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->get(verificationUrl($other))
        ->assertForbidden();

    expect($customer->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification link on request', function (): void {
    Notification::fake();

    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('verification.send'))
        ->assertRedirect();

    Notification::assertSentTo($customer, VerifyEmail::class);
});

it('does not resend for an already verified customer', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('verification.send'))
        ->assertRedirect(route('orders.index'));

    Notification::assertNothingSent();
});
