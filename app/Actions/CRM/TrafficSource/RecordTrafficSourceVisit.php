<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordTrafficSourceVisit
{
    use AsAction;

    /**
     * One person arriving from one channel, counted per shop and day whether or not they ever buy.
     *
     * Called from both places a visit can happen, because they are genuinely different events: a
     * storefront arrival where the referrer names the channel, and a click on an email we sent. The
     * second never reaches the storefront with anything to identify it — the referrer is the reader's
     * webmail, which we deliberately ignore — so counting only landings left every email channel
     * showing no visits at all, when a mailshot click is as much a visit as any other.
     *
     * A counter, not a row: this is on the storefront hot path and in the middle of SES event bursts,
     * and `traffic-source:collect-visits` folds the counters into the table hourly.
     */
    public function handle(?int $shopId, ?TrafficSourcesTypeEnum $type): void
    {
        if (!$shopId || !$type) {
            return;
        }

        try {
            $key = 'traffic_visits:'.now()->toDateString().':'.$shopId.':'.$type->value;

            /* add() first so the counter carries an expiry; a bare increment on a missing key leaves
               it in the store forever. */
            Cache::add($key, 0, now()->addDays(8));
            Cache::increment($key);
        } catch (\Throwable $e) {
            /* A lost count is a lost count; it must never cost a page view or an SES event. */
            report($e);
        }
    }
}
