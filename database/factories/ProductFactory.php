<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($name),
            'name' => ['en' => $name, 'nl' => 'NL '.$name],
            'description' => ['en' => fake()->paragraph(), 'nl' => fake()->paragraph()],
            'price' => fake()->numberBetween(500, 9500),
            'currency' => 'EUR',
            'status' => 'published',
            'cover_path' => null,
            'cover_thumb_path' => null,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'draft']);
    }
}
