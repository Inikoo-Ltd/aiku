<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 27 Apr 2023 16:51:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Dispatch;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShipperFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'         => fake()->lexify(),
            'name'         => fake()->name,
            'contact_name' => fake()->name,
            'company_name' => fake()->company,
            'email'        => fake()->email,
            'website'      => fake()->url,
            'tracking_url' => fake()->url,
        ];
    }
}
