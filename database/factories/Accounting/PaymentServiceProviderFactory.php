<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 25 Apr 2023 12:47:10 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace Database\Factories\Accounting;

use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentServiceProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify(),
            'name' => fake()->lexify(),
            'type' => PaymentServiceProviderTypeEnum::BANK->value,
        ];
    }
}
