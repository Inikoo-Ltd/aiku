<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Attribution window
    |--------------------------------------------------------------------------
    |
    | How many days after a marketing touch its revenue may still be credited to
    | that channel. Without a window a click today claims a customer's entire
    | purchase history, including years of trade that predate it.
    |
    | 90 days suits wholesale reorder cycles. Google Ads defaults to 30 for
    | comparison. Override per shop with settings.marketing.attribution_window_days.
    |
    */

    'attribution_window_days' => env('MARKETING_ATTRIBUTION_WINDOW_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Internal referrer hosts
    |--------------------------------------------------------------------------
    |
    | Traffic arriving from our own systems is not a marketing source. A staff
    | member opening a storefront from the admin app would otherwise be recorded
    | as a referral, and one shop linking to another as an acquisition.
    |
    | Matched as domain suffixes; a visitor arriving from the same host they are
    | already on is excluded separately.
    |
    */

    'internal_referrer_hosts' => [
        'aiku.io',
        'aiku.test',
    ],

];
