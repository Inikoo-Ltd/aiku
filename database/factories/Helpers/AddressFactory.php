<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Reviewed: Sun, 25 Jun 2023 11:12:57 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Helpers;

use App\Models\Helpers\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        $country = Country::latest()->first();

        return [
            'address_line_1' => fake()->streetAddress,
            'address_line_2' => fake()->buildingNumber,
            'sorting_code' => '',
            'postal_code' => fake()->postcode,
            'locality' => fake()->city,
            'dependent_locality' => '',
            'administrative_area' => fake('en_US')->state(),
            'country_id' => $country->id,
        ];
    }
}
