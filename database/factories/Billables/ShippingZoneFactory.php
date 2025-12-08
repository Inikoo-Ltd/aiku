<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 12:24:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\Billables;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Billables\ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'code' => fake()->lexify,
            'name' => fake()->name,
            'status' => fake()->boolean,
            'price' => [
                'amount' => fake()->randomFloat(2, 1, 100),
                'currency' => fake()->currencyCode,
            ],
            'territories' => [
                'territory' => fake()->countryCode,
            ],
            'position' => fake()->numberBetween(1, 100),

        ];
    }
}
