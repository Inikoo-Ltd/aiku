<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Dec 2025 00:37:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\GoodsIn;

use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockDeliveryFactory extends Factory
{
    public function definition(): array
    {
        /** @var Currency $currency */
        $currency = Currency::where('code', 'USD')->firstOrFail();

        return [
            'reference'   => fake()->numberBetween(0, 9999),
            'date'        => fake()->date,
            'currency_id' => $currency->id,
            'exchange'    => 12350
        ];
    }
}
