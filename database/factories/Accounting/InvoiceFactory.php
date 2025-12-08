<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 26 May 2023 16:53:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $currency = Currency::first();

        return [
            'reference' => uniqid(),
            'type' => InvoiceTypeEnum::INVOICE,
            'currency_id' => $currency->id,
            'gross_amount' => 10,
            'net_amount' => 10,
            'tax_amount' => 0,
            'total_amount' => 10,
            'payment_amount' => 0,
            'data' => [],
        ];
    }
}
