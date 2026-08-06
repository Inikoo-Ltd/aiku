<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncCustomerTrafficSourcesFromDevice implements ShouldBeUnique
{
    use AsAction;

    /* Collapses the page-view burst of a browsing session into one job per customer. */
    public int $jobUniqueFor = 600;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Customer $customer): string
    {
        return (string) $customer->id;
    }

    /**
     * Folds the touch history a device's cookie accumulated into the customer's server-side history.
     * The cookie is per browser, the customer is not: the ad clicked on a phone and the search made
     * on a desktop belong to one journey, and the customer row is the only place both can meet.
     * Touches already known are deduplicated by the merge, so replaying the same cookie is a no-op.
     */
    public function handle(Customer $customer, string $deviceTouches): void
    {
        $merged = MergeTrafficSourceTouchHistories::run($customer->traffic_sources, $deviceTouches);

        if ($merged === $customer->traffic_sources) {
            return;
        }

        $customer->update(['traffic_sources' => $merged]);

        RecalculateTrafficSourceAttribution::run($customer->fresh());
    }
}
