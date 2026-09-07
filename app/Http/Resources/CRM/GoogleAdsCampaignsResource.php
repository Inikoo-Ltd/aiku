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
 * @property mixed $spend_30d
 * @property mixed $spend_total
 */
class GoogleAdsCampaignsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\CRM\TrafficSourceCampaign $campaign */
        $campaign = $this->resource;

        return [
            'id'            => $campaign->id,
            'slug'          => $campaign->slug,
            'reference'     => $campaign->reference,
            'name'          => $campaign->name,
            'route'         => [
                'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    ['trafficSourceCampaign' => $campaign->slug]
                ),
            ],
            'status'        => $campaign->status,
            'channel_type'  => $campaign->channel_type,
            'budget_amount' => $campaign->budget_amount,
            'currency_code' => $campaign->currency_code,
            'spend_30d'     => $campaign->spend_30d,
            'spend_total'   => $campaign->spend_total,
        ];
    }
}
