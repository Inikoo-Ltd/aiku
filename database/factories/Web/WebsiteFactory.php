<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Jan 2024 13:37:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Factories\Web;

use Illuminate\Database\Eloquent\Factories\Factory;

class WebsiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'                     => fake()->lexify(),
            'name'                     => fake()->company(),
            'domain'                   => fake()->domainName,
        ];
    }
}
