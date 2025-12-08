<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Apr 2023 20:31:30 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\SysAdmin;

use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        $country = Country::where('code', 'US')->firstOrFail();
        $language = Language::where('code', 'en')->firstOrFail();
        $timezone = Timezone::where('name', fake()->timezone('US'))->firstOrFail();
        $currency = Currency::where('code', 'USD')->firstOrFail();

        return [
            'code' => fake()->lexify(),
            'name' => fake()->company(),
            'country_id' => $country->id,
            'language_id' => $language->id,
            'timezone_id' => $timezone->id,
            'currency_id' => $currency->id,
        ];
    }
}
