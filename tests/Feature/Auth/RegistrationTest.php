<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

// Deliberately not faking notifications: the verification mail is rendered for real against
// the array transport, which is what caught the missing verification.verify route.
it('registers a customer and lands on the orders page', function (): void {
    $response = $this->post('/register', [
        'name' => 'Nora Vermeer',
        'email' => 'nora@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertRedirect(route('orders.index'));

    $customer = Customer::where('email', 'nora@example.com')->firstOrFail();

    expect($customer->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($customer, 'customer');
});

it('sends a verification notification on registration', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Nora Vermeer',
        'email' => 'nora@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    Notification::assertSentTo(Customer::where('email', 'nora@example.com')->firstOrFail(), VerifyEmail::class);
});

it('rejects a duplicate email', function (): void {
    Customer::factory()->create(['email' => 'nora@example.com']);

    $this->post('/register', [
        'name' => 'Nora Vermeer',
        'email' => 'nora@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});
