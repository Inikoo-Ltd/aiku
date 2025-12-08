<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 07:55:38 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Catalogue;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify(),
            'name' => fake()->company(),
            'contact_name' => fake()->name,
            'company_name' => fake()->company,
            'email' => fake()->email,
            'identity_document_number' => fake('en_GB')->vat(),
            'identity_document_type' => 'passport',
            'type' => ShopTypeEnum::B2B->value,
            'country_id' => 1,
            'currency_id' => 1,
            'language_id' => 1,
            'timezone_id' => 1,
        ];
    }
}
