<?php

namespace Database\Factories;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '011'.fake()->numerify('########'),
            'national_id' => fake()->numerify('29############'),
            'vehicle_type' => fake()->randomElement(['Motorcycle', 'Van', 'Car']),
            'vehicle_plate' => fake()->bothify('???-####'),
            'status' => fake()->randomElement(['active', 'inactive']),
            'notes' => null,
        ];
    }
}
