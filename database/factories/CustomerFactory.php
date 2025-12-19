<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '2547' . $this->faker->numerify('########'),
            'billing_address' => $this->faker->address(),
            'shipping_address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'county' => $this->faker->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret']),
            'credit_limit' => $this->faker->randomFloat(2, 0, 100000),
            'is_active' => true,
        ];
    }
}
