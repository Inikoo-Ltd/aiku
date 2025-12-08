<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 12:18:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\SupplyChain;

use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        $currency = Currency::where('code', 'USD')->firstOrFail();

        return [
            'code' => fake()->lexify(),
            'name' => fake()->name,
            'company_name' => fake()->company,
            'contact_name' => fake()->name,
            'email' => fake()->email,
            'currency_id' => $currency->id,
            'address' => Address::factory()->definition(),
            'scope_type' => 'Group',
            'scope_id' => 1,
        ];
    }
}
