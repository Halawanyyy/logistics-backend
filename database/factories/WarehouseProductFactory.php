<?php

namespace Database\Factories;

use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseProduct>
 */
class WarehouseProductFactory extends Factory
{
    protected $model = WarehouseProduct::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-###-???')),
            'barcode' => fake()->numerify('622##########'),
            'description' => fake()->sentence(),
            'unit_weight' => fake()->randomFloat(2, 0.1, 5),
            'is_fragile' => fake()->boolean(20),
            'requires_packaging' => fake()->boolean(60),
            'status' => 'active',
        ];
    }
}
