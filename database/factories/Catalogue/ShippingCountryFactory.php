<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:35:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\Catalogue;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Ordering\ShippingCountry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCountry>
 */
class ShippingCountryFactory extends Factory
{
    protected $model = ShippingCountry::class;

    public function definition(): array
    {
        // Ensure there is at least one country; create a minimal one if none exists
        $country = Country::query()->inRandomOrder()->first() ?? Country::query()->create([
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'iso3' => strtoupper($this->faker->lexify('???')),
            'phone_code' => $this->faker->numerify('###'),
            'name' => $this->faker->country(),
            'continent' => $this->faker->randomElement(['EU', 'AS', 'AF', 'NA', 'SA', 'OC']),
            'data' => [],
        ]);

        return [
            'shop_id' => Shop::factory(),
            'country_id' => $country->id,
            'territories' => $this->faker->boolean() ? [
                'states' => $this->faker->randomElements(['A', 'B', 'C', 'D'], $this->faker->numberBetween(0, 3)),
                'exclusions' => $this->faker->randomElements(['X', 'Y', 'Z'], $this->faker->numberBetween(0, 2)),
            ] : null,
        ];
    }
}
