<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear('login');
});

it('logs a customer in', function (): void {
    $customer = Customer::factory()->create(['email' => 'nora@example.com']);

    $this->post('/login', ['email' => 'nora@example.com', 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($customer, 'customer');
});

it('rejects a wrong password', function (): void {
    Customer::factory()->create(['email' => 'nora@example.com']);

    $this->post('/login', ['email' => 'nora@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors();

    $this->assertGuest('customer');
});

it('logs a customer out', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')->post('/logout')->assertRedirect();

    $this->assertGuest('customer');
});

it('throttles repeated customer login attempts', function (): void {
    Customer::factory()->create(['email' => 'nora@example.com']);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => 'nora@example.com', 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => 'nora@example.com', 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest('customer');
});

it('logs an admin in on the web guard, not the customer guard', function (): void {
    $admin = User::factory()->create(['email' => 'admin@shop.test']);

    $this->post(route('admin.login'), ['email' => 'admin@shop.test', 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($admin, 'web');
    $this->assertGuest('customer');
});

it('throttles repeated admin login attempts', function (): void {
    User::factory()->create(['email' => 'admin@shop.test']);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('admin.login'), ['email' => 'admin@shop.test', 'password' => 'wrong']);
    }

    $this->post(route('admin.login'), ['email' => 'admin@shop.test', 'password' => 'password'])
        ->assertStatus(429);
});

it('does not let a customer into the admin area', function (): void {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(route('admin.products.index'))
        ->assertRedirect(route('admin.login'));
});

it('does not let an admin session act as a customer', function (): void {
    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('orders.index'))
        ->assertRedirect(route('login'));
});
