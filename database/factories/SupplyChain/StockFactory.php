<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 21:31:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\SupplyChain;

use App\Enums\SupplyChain\Stock\StockStateEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'     => fake()->lexify(),
            'name'     => fake()->name(),
            'state'    => StockStateEnum::ACTIVE->value
        ];
    }
}
