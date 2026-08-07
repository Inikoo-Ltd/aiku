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

    public function handle($url): ?string
    {
        $urlComponents = parse_url($url);
        $queryParams   = [];

        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $queryParams);
        }

        /* gclid alone is enough to prove a paid Google click: custom tracking templates and some
           placement types omit the gad_* parameters, and demanding all three sent that paid traffic
           into organic via the referer fallback. The campaign id rides along when present. */
        if (array_key_exists('gclid', $queryParams)) {
            return
                TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::GOOGLE_ADS->value].
                $this->sanitizeCampaignRef(Arr::get($queryParams, 'gad_campaignid'));
        }

        if (array_key_exists('fbclid', $queryParams) && array_key_exists('utm_medium', $queryParams) && $queryParams['utm_medium'] == 'paid') {
            return
                TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::META_ADS->value].
                $this->sanitizeCampaignRef(Arr::get($queryParams, 'utm_campaign'));
        }

        /* No campaign reference for Bing: msclkid is unique per click, so recording it as a campaign
           made every Bing click a distinct campaign, over-weighting Bing in the share split and
           matching no imported cost row ever. */
        if (array_key_exists('msclkid', $queryParams)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::BING_ADS->value];
        }




        return null;
    }

    /**
     * The cookie is a pipe/comma-joined touch string, so a campaign name containing either separator
     * would shatter the whole history into garbage segments when parsed back.
     */
    private function sanitizeCampaignRef(?string $reference): string
    {
        return preg_replace('/[|,\s]+/', '-', trim((string) $reference)) ?? '';
    }
}
