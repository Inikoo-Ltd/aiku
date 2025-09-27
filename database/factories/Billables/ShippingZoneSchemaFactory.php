<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 12:24:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\Billables;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingZoneSchemaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->name,
        ];
    }
}
