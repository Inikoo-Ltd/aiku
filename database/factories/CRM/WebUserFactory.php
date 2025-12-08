<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Jan 2025 02:33:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\CRM;

use Illuminate\Database\Eloquent\Factories\Factory;

class WebUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => fake()->userName,
            'password' => fake()->password,
            'email' => fake()->email,
        ];
    }
}
