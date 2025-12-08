<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 07 Apr 2024 10:03:45 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Dispatch;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->lexify(),
        ];
    }
}
