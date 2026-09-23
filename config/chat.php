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

    'phone_call' => [

        'max_minutes' => (int) env('CHAT_PHONE_CALL_MAX_MINUTES', 60),
        'warn_before_minutes' => (int) env('CHAT_PHONE_CALL_WARN_BEFORE_MINUTES', 10),

    ],

    'ask_guest_if_customer' => (bool) env('CHAT_ASK_GUEST_IF_CUSTOMER', true),

    /*
     * How long a conversation may sit unclaimed before it joins the group-wide unclaimed queue.
     * Suggested starting numbers from the inbox spec, not confirmed by customer service.
     */
    'unclaimed' => [

        'after_seconds' => [
            'website'  => (int) env('CHAT_UNCLAIMED_WEBSITE_SECONDS', 120),
            'whatsapp' => (int) env('CHAT_UNCLAIMED_WHATSAPP_SECONDS', 1800),
            'email'    => (int) env('CHAT_UNCLAIMED_EMAIL_SECONDS', 7200),
        ],

        // Left empty until somebody owns a channel that is actually read: an alert sent to a
        // place nobody watches is the bug this queue exists to fix.
        'slack_channel' => env('CHAT_UNCLAIMED_SLACK_CHANNEL'),

    ],

    // A message that arrives while the shop is closed is answered once per wait, with when the
    // shop opens again. Email only to a person, never to mail that was generated or sent to a list.
    'out_of_hours_reply' => (bool) env('CHAT_OUT_OF_HOURS_REPLY', true),

    // Replies the AI writes from order and stock facts for staff to send, change or discard.
    // Never sent by themselves.
    'ai_drafts' => (bool) env('CHAT_AI_DRAFTS', true),

    // A draft goes to the customer without a person only out of hours, and only on a topic of a
    // shop where staff sent nearly all recent drafts exactly as written and none sent this way
    // was flagged as wrong. Off until switched on, and even then only where it is earned.
    'ai_auto_send' => [
        'enabled'        => (bool) env('CHAT_AI_AUTO_SEND', false),
        'window_days'    => 30,
        'min_decided'    => 50,
        'min_used_share' => 0.9,
    ],

    'noise' => [

        'auto_put_aside' => (bool) env('CHAT_NOISE_AUTO_PUT_ASIDE', false),
        'put_aside_confidence' => (int) env('CHAT_NOISE_PUT_ASIDE_CONFIDENCE', 90),
        'hint_confidence' => (int) env('CHAT_NOISE_HINT_CONFIDENCE', 60),
        'min_whatsapp_chars' => 40,
        'greet_bare_hello' => (bool) env('CHAT_NOISE_GREET_BARE_HELLO', true),
        'supplier_phone_prefixes' => ['91', '86', '92', '880', '20'],

    ],

];
