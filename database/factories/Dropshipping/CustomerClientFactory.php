<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 20:20:38 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Dropshipping;

use App\Models\Helpers\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->lexify(),
            'contact_name' => fake()->name,
            'company_name' => fake()->company,
            'email' => fake()->email,
            'address' => Address::factory()->definition(),
        ];
    }
}
