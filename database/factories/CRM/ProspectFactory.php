<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:47:20 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\CRM;

use App\Models\Helpers\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_name' => fake()->name,
            'company_name' => fake()->company,
            'email' => fake()->email,
            'contact_website' => fake()->domainName,
            'address' => Address::factory()->definition(),
        ];
    }
}
