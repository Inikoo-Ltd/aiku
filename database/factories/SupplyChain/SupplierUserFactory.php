<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 12:18:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\SupplyChain;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierUserFactory extends Factory
{
    public function definition(): array
    {

        return [
            'username' => fake()->userName,
            'password' => fake()->password,
            'email' => fake()->email,
        ];
    }
}
