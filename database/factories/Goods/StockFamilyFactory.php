<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:14:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Goods;

use Illuminate\Database\Eloquent\Factories\Factory;

class StockFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify('??????'),
            'name' => fake()->name,
        ];
    }
}
