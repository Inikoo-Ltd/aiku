<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 21:31:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Inventory;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrgStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'state' => OrgStockStateEnum::ACTIVE,
        ];
    }
}
