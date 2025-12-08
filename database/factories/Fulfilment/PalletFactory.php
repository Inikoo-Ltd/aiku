<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 10:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Fulfilment;

use Illuminate\Database\Eloquent\Factories\Factory;

class PalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_reference' => fake()->lexify,
        ];
    }
}
