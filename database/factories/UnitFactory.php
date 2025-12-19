<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Piece', 'Kilogram', 'Liter', 'Box', 'Dozen']),
            'short_name' => $this->faker->randomElement(['pcs', 'kg', 'L', 'box', 'dz']),
            'type' => $this->faker->randomElement(['quantity', 'weight', 'volume']),
        ];
    }
}
