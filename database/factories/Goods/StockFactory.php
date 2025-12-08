<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:14:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Goods;

use App\Models\Goods\TradeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify(),
            'name' => fake()->name(),
            'units' => 1,
            'trade_unit' => TradeUnit::factory()->definition(),
        ];
    }
}
