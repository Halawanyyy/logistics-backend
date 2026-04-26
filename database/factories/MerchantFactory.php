<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'phone' => '010'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
