<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Aug 2024 14:43:20 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Procurement;

use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        /** @var Currency $currency */
        $currency = Currency::where('code', 'USD')->firstOrFail();

        return [
            'reference' => fake()->numberBetween(100, 999),
            'date' => fake()->date,
            'currency_id' => $currency->id,
        ];
    }
}
