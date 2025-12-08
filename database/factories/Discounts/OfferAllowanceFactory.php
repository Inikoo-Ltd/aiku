<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 19:43:11 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\Discounts;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discounts\OfferAllowance>
 */
class OfferAllowanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify,
            'name' => fake()->name,
            'trigger_scope' => 'NA',
        ];
    }
}
