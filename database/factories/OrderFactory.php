<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(500, 9500);

        return [
            'customer_id' => Customer::factory(),
            'coupon_id' => null,
            'status' => 'pending',
            'currency' => 'EUR',
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'payment_provider' => 'stripe',
            'payment_id' => 'cs_test_'.fake()->unique()->lexify('??????????'),
            'payment_method' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);
    }

    public function mollie(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_provider' => 'mollie',
            'payment_id' => 'tr_'.fake()->unique()->lexify('??????????'),
        ]);
    }
}
