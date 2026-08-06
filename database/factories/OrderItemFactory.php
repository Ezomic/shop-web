<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->sentence(3),
            'product_slug' => fake()->unique()->slug(),
            'price' => fake()->numberBetween(500, 9500),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => $product->id,
            'product_name' => $product->getTranslation('name', 'en'),
            'product_slug' => $product->slug,
            'price' => $product->price,
        ]);
    }
}
