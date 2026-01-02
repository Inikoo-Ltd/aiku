<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 17:00:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\SysAdmin;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id'   => Group::factory(),
            'username'   => fake()->unique()->userName,
            'password'   => 'password',
            'email'      => fake()->email,
            'language_id' => 1,
        ];
    }
}
