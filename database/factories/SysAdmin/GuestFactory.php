<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 17:00:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\SysAdmin;

use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'alias' => fake()->lexify(),
            'contact_name' => fake()->company(),
            'email' => fake()->email,
            'identity_document_number' => fake('en_GB')->vat(),
            'identity_document_type' => 'passport',
            'user' => [
                'username' => fake()->userName,
                'password' => 'password',
            ],

        ];
    }
}
