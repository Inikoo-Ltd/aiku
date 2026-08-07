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

    /*
    |--------------------------------------------------------------------------
    | Outboxes whose clicks are marketing
    |--------------------------------------------------------------------------
    |
    | We send far more than mailshots, and some of it is unmistakably marketing:
    | a reorder reminder, an abandoned basket chase, a back-in-stock notice. A
    | click on one of those brought the customer back and should be credited,
    | under the `email-automated` channel with the outbox code as the campaign
    | reference so each kind is reported on its own.
    |
    | Everything absent from this list stays uncredited, which is the safe
    | default: password reminders, order confirmations, dispatch notices and
    | invoices are service, not acquisition, and crediting them would hand
    | marketing a share of every engaged customer's revenue.
    |
    */

    'attributed_outbox_codes' => [
        'abandoned_cart',
        'abandoned_cart_reminder_1',
        'abandoned_cart_reminder_2',
        'abandoned_cart_reminder_3',
        'reorder_reminder',
        'reorder_reminder_2nd',
        'reorder_reminder_3rd',
        'gold_reward_reminder_1',
        'gold_reward_reminder_2',
        'gold_reward_reminder_3',
        'basket_low_stock',
        'basket_push',
        'new_customer_push',
        'oos_notification',
        'oos_in_order_notification',
        'new_offer',
        'finish_offer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer types that are not marketing
    |--------------------------------------------------------------------------
    |
    | A discretionary discount is a salesperson deciding to knock money off. It
    | is the largest giveaway on the books - £38,628 on the uk shop in thirty
    | days - and it would dominate the offer screen while telling marketing
    | nothing, since no campaign caused it and no email could have.
    |
    | Matched case-insensitively against offers.type.
    |
    */

    'non_marketing_offer_types' => [
        'discretionary',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webmail providers
    |--------------------------------------------------------------------------
    |
    | Hosts whose whole domain is a mailbox, where the subdomain gives nothing
    | away: abv.bg serves its webmail from nm20.abv.bg, which no sensible prefix
    | rule would catch. Matched as domain suffixes.
    |
    | Only for domains that are mail and nothing else. Seznam belongs in the
    | pattern list instead, because search.seznam.cz is a search engine.
    |
    */

    'webmail_referrer_domains' => [
        'abv.bg',
        'mail.ru',
        'gmx.net',
        'gmx.de',
        'web.de',
        'freenet.de',
        'laposte.net',
        'wanadoo.fr',
        'libero.it',
        'terra.es',
    ],

    'webmail_referrer_patterns' => [
        '/^mail[0-9]*\./',
        '/^webmail\./',
        '/^messagerie/',
        '/^email\./',
        '/^outlook\.(live|office|office365)\.com$/',
        '/^mail\.(google|yahoo|proton|zoho)\.com$/',
    ],

];
