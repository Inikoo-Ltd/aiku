<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 07:56:46 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Ordering;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ordering\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->lexify(),
            'date' => fake()->date,
            'customer_id' => fake()->numberBetween(1, 100),
            'state' => OrderStateEnum::CREATING->value,
        ];
    }
}
