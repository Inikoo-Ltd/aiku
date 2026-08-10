<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
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
        $deviceTouches = self::sanitize($deviceTouches);

        if (blank($deviceTouches)) {
            return;
        }

        /* Read-merge-write under a row lock: RecordEmailClickTouchpoint appends to the same column
           from another queue, and merging against a stale read would silently erase its click. */
        $changed = DB::transaction(function () use ($customer, $deviceTouches): bool {
            $locked = Customer::lockForUpdate()->find($customer->id);

            if (!$locked) {
                return false;
            }

            $merged = MergeTrafficSourceTouchHistories::run($locked->traffic_sources, $deviceTouches);

            if ($merged === $locked->traffic_sources) {
                return false;
            }

            $locked->update(['traffic_sources' => $merged]);

            return true;
        });

        if ($changed) {
            RecalculateTrafficSourceAttribution::run($customer->fresh());
        }
    }

    /**
     * The cookie is raw client data and this job writes it into server-side attribution state, so
     * only touches that would survive parsing get through, and only with credible timestamps: a
     * forged epoch-zero segment would otherwise claim the permanent first-touch slot, and garbage
     * would sit in the customer's history forever. The touch string is rebuilt from the parsed form,
     * never passed through verbatim.
     */
    public static function sanitize(?string $deviceTouches): ?string
    {
        $floor   = 1577836800; // 2020-01-01, comfortably before any touch this system could have made
        $ceiling = now()->addDay()->timestamp;

        $segments = collect(ParseTrafficSourceTouches::run($deviceTouches))
            ->filter(fn (array $touch) => $touch['timestamp'] !== null
                && $touch['timestamp'] >= $floor
                && $touch['timestamp'] <= $ceiling)
            /* A referral's campaign reference is a hostname taken from a client-controlled header,
               so it is re-validated here rather than trusted into the customer's history. */
            ->filter(fn (array $touch) => !in_array($touch['type'], [
                TrafficSourcesTypeEnum::REFERRAL,
                TrafficSourcesTypeEnum::ORGANIC_SEARCH,
            ], true)
                || GetTrafficSourceFromRefererHeader::normaliseHost($touch['campaign_ref']) === $touch['campaign_ref'])
            ->map(fn (array $touch) => $touch['timestamp'].$touch['abbr'].($touch['campaign_ref'] ?? ''));

        return $segments->isEmpty() ? null : $segments->implode('|');
    }
}
