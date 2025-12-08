<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Procurement;

use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockDeliveryFactory extends Factory
{
    public function definition(): array
    {
        /** @var Currency $currency */
        $currency = Currency::where('code', 'USD')->firstOrFail();

        return [
            'reference' => fake()->numberBetween(0, 9999),
            'date' => fake()->date,
            'currency_id' => $currency->id,
            'exchange' => 12350,
        ];
    }
}
