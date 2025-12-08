<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 25 Apr 2023 13:54:01 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace Database\Factories\Accounting;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify('????'),
            'name' => fake()->company(),
            'type' => PaymentAccountTypeEnum::CASH->value,
        ];
    }
}
