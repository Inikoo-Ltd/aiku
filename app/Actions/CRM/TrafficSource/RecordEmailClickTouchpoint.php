<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordEmailClickTouchpoint
{
    use AsAction;

    /**
     * Records a real (non-duplicate) email click as a `newsletter` marketing touch on the customer's
     * raw touch history, then rebuilds the customer's traffic-source attribution from that history.
     *
     * Deduplication: a click occurring within the same calendar day as the customer's most recent
     * `newsletter` touch is treated as a repeat click on an already-open email and is not recorded
     * again, so opening the same link/tab multiple times does not inflate the touch history.
     */
    public function handle(Customer $customer, ?Carbon $occurredAt = null): void
    {
        $occurredAt = $occurredAt ?? now();

        $touches = ParseTrafficSourceTouches::run($customer->traffic_sources);

        $lastNewsletterTouch = collect($touches)
            ->filter(fn (array $touch) => $touch['type'] === TrafficSourcesTypeEnum::NEWSLETTER)
            ->last();

        if ($lastNewsletterTouch && $lastNewsletterTouch['timestamp']
            && Carbon::createFromTimestamp($lastNewsletterTouch['timestamp'])->isSameDay($occurredAt)) {
            return;
        }

        $abbr     = TrafficSourcesTypeEnum::NEWSLETTER->abbr()[TrafficSourcesTypeEnum::NEWSLETTER->value];
        $newTouch = $occurredAt->getTimestamp() . $abbr;

        $customer->update([
            'traffic_sources' => $customer->traffic_sources
                ? $customer->traffic_sources . '|' . $newTouch
                : $newTouch,
        ]);

        RecalculateTrafficSourceAttribution::run($customer->fresh());
    }
}
