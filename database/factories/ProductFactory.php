<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 10, 1000);
        $sellingPrice = $costPrice * $this->faker->randomFloat(2, 1.2, 2.0);

        return [
            'name' => $this->faker->words(3, true),
            'slug' => Str::slug($this->faker->words(3, true)),
            'sku' => 'SKU-' . strtoupper($this->faker->bothify('???-###')),
            'description' => $this->faker->sentence(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => $costPrice,
            'selling_price' => round($sellingPrice, 2),
            'stock_quantity' => $this->faker->numberBetween(0, 500),
            'reorder_level' => $this->faker->numberBetween(5, 20),
            'reorder_quantity' => $this->faker->numberBetween(50, 200),
            'track_stock' => true,
            'is_active' => true,
        ];
    }
}
