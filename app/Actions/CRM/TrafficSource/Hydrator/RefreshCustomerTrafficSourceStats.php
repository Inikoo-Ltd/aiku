<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource\Hydrator;

use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RefreshCustomerTrafficSourceStats implements ShouldBeUnique
{
    use AsAction;

    public int $jobUniqueFor = 300;

    /* Statistics, never order processing: this must not compete with the work that actually ships
       goods. */
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(?Customer $customer): string
    {
        return (string) ($customer?->id ?? 'none');
    }

    /**
     * Refreshes every channel a customer is credited to, after something changed what that customer is
     * worth - an invoice raised, most of all.
     *
     * Attribution itself is written the moment a touch lands, so it never needs a sweep. The rollups
     * behind the traffic sources listing are the part that goes stale, because they are recalculated
     * only when a touch fires for that source: a channel that brought a customer in March and nothing
     * since kept March's revenue until somebody ran a hydrator by hand.
     */
    public function handle(?Customer $customer): void
    {
        if (!$customer) {
            return;
        }

        foreach ($customer->trafficSources as $trafficSource) {
            TrafficSourceHydrateCustomers::dispatch($trafficSource);

            $campaign = $trafficSource->pivot->traffic_source_campaign_id
                ? TrafficSourceCampaign::find($trafficSource->pivot->traffic_source_campaign_id)
                : null;

            if ($campaign) {
                TrafficSourceCampaignHydrateStats::dispatch($campaign);
            }
        }
    }
}
