<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the campaign a piece of ad spend belongs to, by the reference the touch history already
 * stores: the ad platform's own campaign id, so no mapping table is needed.
 *
 * A campaign that has had spend but no click yet has no row, hence the create; `reference` is globally
 * unique, so a reference already claimed by another shop's source falls back to the source-level total
 * rather than mis-crediting.
 *
 * `$channelType` is the ad platform's own label for what kind of campaign it is (Google Ads' VIDEO,
 * SEARCH, PERFORMANCE_MAX...). It is recorded, never acted on: a click carries no channel type, so the
 * label only ever arrives with the next day's spend and cannot be used to route a touch to a different
 * channel without contradicting the touch history. It exists to answer, from data rather than from
 * memory, how much of a bundled channel is actually video before anyone builds a split.
 */
class GetTrafficSourceCampaign
{
    use AsAction;

    public function handle(TrafficSource $trafficSource, ?string $reference, ?string $name = null, ?string $channelType = null): ?int
    {
        if (blank($reference)) {
            return null;
        }

        $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

        if ($campaign) {
            if ($campaign->traffic_source_id !== $trafficSource->id) {
                return null;
            }

            /* Rows born from a click carry no channel type; the first cost import that names one fills
               it in. A later import must still be able to correct it, since a campaign can be rebuilt
               under a different channel and keep its id. */
            if (filled($channelType) && $campaign->channel_type !== $channelType) {
                $campaign->update(['channel_type' => $channelType]);
            }

            return $campaign->id;
        }

        return TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'reference'         => $reference,
            'name'              => $name ?: $reference,
            'type'              => $trafficSource->type,
            'channel_type'      => $channelType,
        ])->id;
    }
}
