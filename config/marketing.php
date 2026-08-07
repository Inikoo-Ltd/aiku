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
    | already on is excluded separately, as is every domain in the websites table.
    |
    */

    'internal_referrer_hosts' => [
        'aiku.io',
        'aiku.test',
        /* The mailshot template editor: staff previewing an email and clicking
           through are not visitors from a website called getbee. */
        'getbee.io',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webmail referrers
    |--------------------------------------------------------------------------
    |
    | A visitor arriving from webmail clicked a link in an email. If it was our
    | mailshot, RecordEmailClickTouchpoint already recorded that click
    | server-side; recording the webmail host as well would count one click
    | twice and split the credit away from the newsletter it belongs to. If it
    | was somebody else's email, "orange.fr webmail" is still not a channel
    | anyone can act on.
    |
    | Either way these produce no touch. They stay visible in the rejected
    | referrers list on the Data quality tab, where a lot of them means our
    | mailshot links are losing their tracking.
    |
    | Matched against the whole host, after `www.` is stripped.
    |
    */

    'webmail_referrer_patterns' => [
        '/^mail[0-9]*\./',
        '/^webmail\./',
        '/^messagerie/',
        '/^email\./',
        '/^outlook\.(live|office|office365)\.com$/',
        '/^mail\.(google|yahoo|proton|zoho)\.com$/',
    ],

];
