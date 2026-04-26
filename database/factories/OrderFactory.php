<?php

namespace Database\Factories;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'customer_name' => fake()->name(),
            'customer_phone' => '010'.fake()->numerify('########'),
            'delivery_address' => fake()->streetAddress(),
            'cod_amount' => fake()->numberBetween(200, 2500),
            'shipping_fee' => fake()->randomElement([55, 65, 75, 90]),
            'payment_type' => fake()->randomElement(['cod', 'prepaid']),
            'fulfillment_type' => fake()->randomElement(['pickup_from_merchant', 'from_warehouse']),
            'source' => 'manual',
            'requires_review' => false,
            'status' => fake()->randomElement(['draft', 'confirmed', 'pending_review']),
        ];
    }
}
