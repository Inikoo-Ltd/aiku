<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Nov 2024 09:44:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

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

        $modelData = $this->prepareOfferDate($parent, $modelData);

        if ($parent instanceof OfferCampaign && !Arr::get($modelData, 'state')) {
            data_set(
                $modelData,
                'state',
                Offer::stateForDates(
                    Arr::get($modelData, 'start_at'),
                    Arr::get($modelData, 'end_at')
                )
            );
        }

        return $modelData;
    }

    /**
     * A date typed in by a shop's staff means midnight in the shop's own timezone, not in UTC.
     */
    protected function prepareOfferDate(OfferCampaign|Offer $parent, array $modelData): array
    {
        $timezone = $parent->shop->timezoneName();

        if (Arr::has($modelData, 'start_at') && Arr::get($modelData, 'start_at') != '' && is_string(Arr::get($modelData, 'start_at'))) {
            $startAt = Carbon::parse(Arr::get($modelData, 'start_at'), $timezone)->startOfDay()->utc();
            data_set($modelData, 'start_at', $startAt);
        }
        if (Arr::has($modelData, 'end_at') && Arr::get($modelData, 'end_at') != '' && is_string(Arr::get($modelData, 'end_at'))) {
            $endAt = Carbon::parse(Arr::get($modelData, 'end_at'), $timezone)->endOfDay()->utc();
            data_set($modelData, 'end_at', $endAt);
        }

        return $modelData;
    }
}
