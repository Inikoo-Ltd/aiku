<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $status
 * @property mixed $channel_type
 * @property mixed $budget_amount
 * @property mixed $currency_code
 * @property mixed $impressions
 * @property mixed $clicks
 * @property mixed $ctr
 * @property mixed $avg_cpc
 * @property mixed $conversions
 * @property mixed $spend
 * @property mixed $roas
 */
class GoogleAdsCampaignsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\CRM\TrafficSourceCampaign $campaign */
        $campaign = $this->resource;

        return [
            'id'        => $campaign->id,
            'slug'      => $campaign->slug,
            'reference' => $campaign->reference,
            'name'      => $campaign->name,
            'route'     => [
                'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    ['trafficSourceCampaign' => $campaign->slug]
                ),
            ],
            'status'        => $campaign->status,
            'channel_type'  => $campaign->channel_type,
            'budget_amount' => $campaign->budget_amount,

            /* The account's currency, which is what the budget and Google's own figures are quoted in.
               Spend is the shop's currency instead, so the two are never formatted from one code. */
            'currency_code' => $campaign->currency_code,

            'impressions' => (int) $campaign->impressions,
            'clicks'      => (int) $campaign->clicks,
            'ctr'         => $campaign->ctr !== null ? (float) $campaign->ctr : null,
            'avg_cpc'     => $campaign->avg_cpc !== null ? (float) $campaign->avg_cpc : null,
            'conversions' => (float) $campaign->conversions,
            'spend'       => (float) $campaign->spend,
            'roas'        => $campaign->roas !== null ? (float) $campaign->roas : null,
        ];
    }
}
