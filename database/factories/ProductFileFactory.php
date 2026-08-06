<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFile>
 */
class ProductFileFactory extends Factory
{
    protected $model = ProductFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->unique()->lexify('????????').'.pdf';

        return [
            'product_id' => Product::factory(),
            'disk' => 'shop',
            'path' => 'products/'.$filename,
            'original_filename' => $filename,
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
