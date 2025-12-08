<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class LostAndFoundStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify(),
            'quantity' => 4,
            'stock_value' => rand(100, 1000),
        ];
    }
}
