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
     * Dates and times typed in by a shop's staff are in the shop's own timezone, not in UTC.
     * A bare date covers the whole day: it starts at midnight and ends at 23:59:59.
     * A date with a time (e.g. 2026-11-07 14:00) starts or ends at exactly that time.
     */
    protected function prepareOfferDate(OfferCampaign|Offer $parent, array $modelData): array
    {
        $timezone = $parent->shop->timezoneName();

        foreach (['start_at', 'end_at'] as $field) {
            $value = Arr::get($modelData, $field);
            if (!is_string($value) || $value == '') {
                continue;
            }

            $date = Carbon::parse($value, $timezone);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = $field == 'start_at' ? $date->startOfDay() : $date->endOfDay();
            }

            data_set($modelData, $field, $date->utc());
        }

        return $modelData;
    }
}
