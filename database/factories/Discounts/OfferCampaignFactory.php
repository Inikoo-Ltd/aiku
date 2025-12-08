<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 27 Apr 2023 16:51:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Discounts;

use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->lexify,
            'name' => fake()->name,
            'type' => OfferCampaignTypeEnum::SHOP_OFFERS,
        ];
    }
}
