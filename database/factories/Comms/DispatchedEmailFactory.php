<?php

namespace Database\Factories\Comms;

use Illuminate\Database\Eloquent\Factories\Factory;

class DispatchedEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify('??????'),
        ];
    }
}
