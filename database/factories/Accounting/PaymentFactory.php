<?php

namespace Database\Factories\Accounting;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->lexify(),
            'amount' => fake()->randomFloat(2),
            'currency_id' => 1,
            'date' => fake()->date(),
        ];
    }
}
