<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Nov 2024 09:44:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Carbon\Carbon;
use Illuminate\Support\Arr;

trait WithStoreOffer
{
    protected function prepareOfferData(OfferCampaign|Offer $parent, array $modelData): array
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'shop_id', $parent->shop_id);

        $status = false;
        if ($parent instanceof Offer) {
            if (Arr::get($modelData, 'state') == OfferStateEnum::ACTIVE) {
                $status = true;
            }
        } else {
            $status = true;
        }


        if (Arr::has($modelData, 'start_at') && Arr::get($modelData, 'start_at') != '' && is_string(Arr::get($modelData, 'start_at'))) {
            $startAt = Carbon::parse(Arr::get($modelData, 'start_at'))->startOfDay();
            data_set($modelData, 'start_at', $startAt);
        }
        if (Arr::has($modelData, 'end_at') && Arr::get($modelData, 'end_at') != '' && is_string(Arr::get($modelData, 'end_at'))) {
            $endAt = Carbon::parse(Arr::get($modelData, 'end_at'))->endOfDay();
            data_set($modelData, 'end_at', $endAt);
        }


        data_set($modelData, 'status', $status);


        return $modelData;
    }
}
