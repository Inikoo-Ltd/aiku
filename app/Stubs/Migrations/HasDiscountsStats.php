<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 17:38:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasDiscountsStats
{
    public function offerCampaignsStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_offer_campaigns')->default(0);
        $table->unsignedInteger('number_current_offer_campaigns')->default(0);
        foreach (OfferCampaignStateEnum::cases() as $case) {
            $table->unsignedInteger('number_offer_campaigns_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function offersStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_offers')->default(0);
        $table->unsignedInteger('number_current_offers')->default(0);
        foreach (OfferStateEnum::cases() as $case) {
            $table->unsignedInteger('number_offers_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function offerAllowancesStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_offer_components')->default(0);
        $table->unsignedInteger('number_current_offer_components')->default(0);
        foreach (OfferAllowanceStateEnum::cases() as $case) {
            $table->unsignedInteger('number_offer_components_state_'.$case->snake())->default(0);
        }

        return $table;
    }

}
