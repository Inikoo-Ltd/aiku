<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Database\Factories\Production;

use Illuminate\Database\Eloquent\Factories\Factory;

class ManufacturePayBandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'              => '0',
            'name'              => 'Band 0',
            'hourly_rate'       => 12.71,
            'target_multiplier' => 1.0,
            'requires_approval' => false,
            'effective_from'    => now()->subYear(),
        ];
    }
}
