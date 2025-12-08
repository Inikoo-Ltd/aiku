<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 07:56:55 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Ordering;

use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {

        $grossAmount = fake()->randomFloat(2, 0, 100);

        return [
            'quantity_bonus' => fake()->numberBetween(0, 10),
            'quantity_ordered' => fake()->numberBetween(0, 10),
            'gross_amount' => $grossAmount,
            'net_amount' => $grossAmount,

        ];
    }
}
