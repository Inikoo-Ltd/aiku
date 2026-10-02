<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Translations\ChatGPT5Driver;

/*
 * Chosen by translations:evaluate-models on real catalogue texts and chats in every shop
 * language (30 Sep 2026). Set here, not in .env, so a server can not quietly run another model.
 */
$geminiCheckedBySonnet = [
    'class'           => ChatGPT5Driver::class,
    'model'           => 'google/gemini-3.1-flash-lite',
    'temperature'     => 0.2,
    'max_tokens'      => 16384,
    'http_timeout'    => 60,
    'fallback_models' => ['gpt-4o-mini'],
    'quality_check'   => [
        'min_score'    => 2.5,
        'retry_driver' => 'sonnet',
    ],
];

return [
    'lang_path'       => lang_path(),
    'source_language' => 'en',
    'default_driver'  => 'sonnet',

    'keep_in_english' => ['Green Man', 'Pocket Pod'],
    'terms_model'     => 'anthropic/claude-sonnet-5.5',
    'terms_in_brief'  => false,

    'drivers' => [
        'sonnet' => [
            'class'           => ChatGPT5Driver::class,
            'model'           => 'anthropic/claude-sonnet-5.5',
            'temperature'     => 0.2,
            'max_tokens'      => 16384,
            'http_timeout'    => 60,
            'fallback_models' => ['google/gemini-3.1-flash-lite', 'gpt-4o-mini'],
        ],
        'catalogue' => $geminiCheckedBySonnet,
        'email'     => $geminiCheckedBySonnet,
    ],
];
