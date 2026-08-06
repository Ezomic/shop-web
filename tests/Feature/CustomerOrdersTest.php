<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;

it('lists only the signed in customer orders', function (): void {
    $customer = Customer::factory()->create();
    Order::factory()->count(2)->for($customer)->create();
    Order::factory()->for(Customer::factory())->create();

    $this->actingAs($customer, 'customer')
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('orders/Index')->has('orders', 2));
});

it('shows an order that belongs to the customer', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->create(['product_name' => 'Stage Fright']);

    $this->actingAs($customer, 'customer')
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('order.items.0.product_name', 'Stage Fright'));
});

it('refuses an order belonging to another customer', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

it('requires a signed in customer', function (): void {
    $order = Order::factory()->for(Customer::factory())->create();

    $this->get(route('orders.index'))->assertRedirect(route('login'));
    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
});
