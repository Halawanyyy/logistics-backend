<?php

namespace Database\Factories;

use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shipment_number' => 'SHP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'customer_name' => fake()->name(),
            'customer_phone' => '010'.fake()->numerify('########'),
            'delivery_address' => fake()->streetAddress(),
            'cod_amount' => fake()->numberBetween(200, 2500),
            'shipping_fee' => fake()->randomElement([55, 65, 75, 90]),
            'status' => fake()->randomElement([
                'pending_pickup',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
                'cancelled',
            ]),
        ];
    }
}
