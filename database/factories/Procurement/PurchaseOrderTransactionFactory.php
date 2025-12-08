<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 05 May 2023 09:23:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Procurement;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderTransactionFactory extends Factory
{
    public function definition(): array
    {

        return [
            'quantity_ordered' => fake()->numberBetween(1, 100),
        ];
    }
}
