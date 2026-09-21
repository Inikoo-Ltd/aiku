<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    'presence' => [

        'heartbeat_seconds' => (int) env('CHAT_AGENT_HEARTBEAT_SECONDS', 120),
        'offline_after_seconds' => (int) env('CHAT_AGENT_OFFLINE_AFTER_SECONDS', 7200),
        'away_after_seconds' => (int) env('CHAT_AGENT_AWAY_AFTER_SECONDS', 3600),
        'abandon_after_seconds' => (int) env('CHAT_AGENT_ABANDON_AFTER_SECONDS', 14400),

    ],

    'summary_model' => env('CHAT_SUMMARY_MODEL', 'gpt-4o'),

    'ask_guest_if_customer' => (bool) env('CHAT_ASK_GUEST_IF_CUSTOMER', true),

    'noise' => [

        'auto_put_aside' => (bool) env('CHAT_NOISE_AUTO_PUT_ASIDE', false),
        'put_aside_confidence' => (int) env('CHAT_NOISE_PUT_ASIDE_CONFIDENCE', 90),
        'hint_confidence' => (int) env('CHAT_NOISE_HINT_CONFIDENCE', 60),
        'min_whatsapp_chars' => 40,
        'greet_bare_hello' => (bool) env('CHAT_NOISE_GREET_BARE_HELLO', true),
        'supplier_phone_prefixes' => ['91', '86', '92', '880', '20'],

    ],

];
