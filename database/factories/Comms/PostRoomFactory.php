<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 25 Apr 2023 12:47:10 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace Database\Factories\Comms;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify('??????'),
        ];
    }
}
