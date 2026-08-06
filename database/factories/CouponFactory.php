<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????????')),
            'type' => 'percent',
            'amount' => 10,
            'max_uses' => null,
            'uses_count' => 0,
            'expires_at' => null,
            'active' => true,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'fixed',
            'amount' => $cents,
        ]);
    }

    public function percent(int $percent): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'percent',
            'amount' => $percent,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => 5,
            'uses_count' => 5,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
