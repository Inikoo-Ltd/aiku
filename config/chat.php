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

    'summary_model' => env('CHAT_SUMMARY_MODEL', 'openai/gpt-5.6-luna'),
    // Chat and long-email summaries: on 30 real chats it matched Sonnet's topic 29 times to gpt-4o's 27, and says what we answered too.
    'summary_writer_model' => env('CHAT_SUMMARY_WRITER_MODEL', 'deepseek/deepseek-v4.1-flash'),
    // On 71 real emails it put aside 25 of 34 staff-marked spam to gpt-4o's 20, and one real customer to gpt-4o's two.
    'noise_model' => env('CHAT_NOISE_MODEL', 'openai/gpt-5.6-luna'),
    'page_answer_model' => env('CHAT_PAGE_ANSWER_MODEL'),
    // Suggested replies are written by one of these, a third of the conversations each, so staff choices show which writes better.
    // CHAT_SUGGESTION_MODEL set to one of them ends the comparison.
    'suggestion_model' => env('CHAT_SUGGESTION_MODEL'),
    'suggestion_models' => ['openai/gpt-5.6-luna', 'deepseek/deepseek-v4.1-flash', 'openai/gpt-6-luna'],
    // A version Jev finds weak is rewritten from the critic's notes, up to this many times; the best version is kept.
    // The cheap writer rewrites; only the last try goes to the strong model, and only when Jev says the facts cover most of what is asked.
    'suggestion_rewrites' => (int) env('CHAT_SUGGESTION_REWRITES', 2),
    'suggestion_strong_model' => env('CHAT_SUGGESTION_STRONG_MODEL', 'anthropic/claude-sonnet-5.5'),
    // Between Jev's scores and the rewrite, this cheap model says what exactly to change.
    'suggestion_critic_model' => env('CHAT_SUGGESTION_CRITIC_MODEL', 'deepseek/deepseek-v4.1-flash'),
    'learning_model' => env('CHAT_LEARNING_MODEL', 'gpt-4o-mini'),

    // Hiding a real customer is worse than showing a junk mail, so the bar is low.
    'spam_rescue_min_probability' => (float) env('CHAT_SPAM_RESCUE_MIN_PROBABILITY', 0.3),

    // An email from Gmail spam that came in anyway is tagged a possible scam only when a scam is probable.
    'spam_rescue_scam_tag_probability' => (float) env('CHAT_SPAM_RESCUE_SCAM_TAG_PROBABILITY', 0.5),

    'urgent_model' => env('CHAT_URGENT_MODEL', 'openai/gpt-5.6-luna'),

    'draft_review_model' => env('CHAT_DRAFT_REVIEW_MODEL', 'gpt-6-sol'),

    'close_after_thanks' => (bool) env('CHAT_CLOSE_AFTER_THANKS', true),

    // With an agent in the chat, a thanks is closed only after this long with nobody writing.
    'close_after_thanks_minutes' => (int) env('CHAT_CLOSE_AFTER_THANKS_MINUTES', 2),

    // An email that only thanks us waits this long for the customer before it closes.
    'wait_for_customer_hours' => (int) env('CHAT_WAIT_FOR_CUSTOMER_HOURS', 72),

    'carrier_domains' => [
        'apc-overnight.com', 'courierlogistics.co.uk', 'bensaude.pt', 'cttexpress.com', 'dhl.com', 'dpd.co.uk', 'dpd.com',
        'dsv.com', 'fedex.com', 'gibcargo.com', 'gls-group.eu', 'gls-spain.com', 'gls-spain.es', 'parcelforce.co.uk', 'royalmail.com',
        'salem-transitarios.pt', 'tnt.com', 'transaher.es', 'ups.com', 'correosexpress.com', 'packeta.sk', 'packeta.com',
    ],

    'marketplace_notice_domains' => ['info.faire.com', 'orderchamp.com'],

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

        'auto_put_aside' => (bool) env('CHAT_NOISE_AUTO_PUT_ASIDE', true),
        'put_aside_confidence' => (int) env('CHAT_NOISE_PUT_ASIDE_CONFIDENCE', 90),
        'hint_confidence' => (int) env('CHAT_NOISE_HINT_CONFIDENCE', 60),
        'min_whatsapp_chars' => 40,
        'greet_bare_hello' => (bool) env('CHAT_NOISE_GREET_BARE_HELLO', true),
        'supplier_phone_prefixes' => ['91', '86', '92', '880', '20'],

    ],

];
