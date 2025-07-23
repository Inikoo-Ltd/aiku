<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 12:42:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrafficSourceFromUrl
{
    use AsAction;

    public function handle($url): ?array
    {
        $urlComponents = parse_url($url);
        $queryParams   = [];

        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $queryParams);
        }

        if (array_key_exists('gad_source', $queryParams) && array_key_exists('gad_campaignid', $queryParams) && array_key_exists('gclid', $queryParams)) {
            return [
                now()->utc()->toDateTimeString(),
                TrafficSourcesTypeEnum::GOOGLE_ADS->value,
                Arr::get($queryParams, 'gad_campaignid'),
                Arr::get($queryParams, 'gclid')
            ];
        }

        if (array_key_exists('fbclid', $queryParams) && array_key_exists('utm_medium', $queryParams) && $queryParams['utm_medium'] == 'paid') {
            return [
                now()->utc()->toDateTimeString(),
                TrafficSourcesTypeEnum::META_ADS->value,
                Arr::get($queryParams, 'utm_campaign'),
                Arr::get($queryParams, 'fbclid')
            ];
        }


        return null;
    }

}
