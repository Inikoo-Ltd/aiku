<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 30 Sept 2022 11:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace Database\Factories\HumanResources;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'state' => EmployeeStateEnum::WORKING,
            'alias' => fake()->unique()->name,
            'contact_name' => fake()->name,
            'date_of_birth' => fake()->date,
            'email' => fake()->email,
            'worker_number' => fake()->unique()->numberBetween(1000, 9999),
            'type' => EmployeeTypeEnum::EMPLOYEE,
        ];
    }
}
