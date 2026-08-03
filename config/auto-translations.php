<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Translations\ChatGPT5Driver;

return [

    /*
    |--------------------------------------------------------------------------
    | Language Files Path
    |--------------------------------------------------------------------------
    |
    | The base path where your language files are stored. By default, it's
    | the 'lang' directory. You can change this to match your application's
    | structure.
    |
    */

    'lang_path' => lang_path(),

    /*
    |--------------------------------------------------------------------------
    | Default Translation Driver
    |--------------------------------------------------------------------------
    |
    | The default translation driver to use when none is specified. You can
    | set this to any of the drivers defined in the 'drivers' array below.
    |
    */

    'default_driver'                 => env('TRANSLATION_DEFAULT_DRIVER', 'gpt-4o-mini'),
    'default_driver_detect_language' => env('DETECT_LANGUAGE_DEFAULT_DRIVER', 'gpt-5-nano'),

    /*
    |--------------------------------------------------------------------------
    | Source Language Code
    |--------------------------------------------------------------------------
    |
    | The default source language code of your application. This will be used
    | as the source language for translations unless specified otherwise.
    |
    */

    'source_language' => env('TRANSLATION_SOURCE_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Translation Drivers
    |--------------------------------------------------------------------------
    |
    | Configure as many translation drivers as you wish. Each driver should
    | have a unique name and its own configuration settings.
    |
    */

    'drivers' => [

        'gpt-3.5-turbo' => [
            'api_key'      => env('CHATGPT_TRANSLATIONS_API_KEY'),
            'model'        => env('CHATGPT_MODEL', 'gpt-3.5-turbo'),
            'temperature'  => (float)env('CHATGPT_TEMPERATURE', 0.7),
            'max_tokens'   => (int)env('CHATGPT_MAX_TOKENS', 4096),
            'http_timeout' => (int)env('CHATGPT_HTTP_TIMEOUT', 300),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],

        'deepl'      => [
            'api_key' => env('DEEPL_API_KEY'),
            'api_url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate'),
        ],
        'gpt-5-nano' => [
            'class'        => ChatGPT5Driver::class,
            'api_key'      => env('CHATGPT_TRANSLATIONS_API_KEY'),
            'model'        => env('CHATGPT_MODEL', 'gpt-5-nano'),
            'max_tokens'   => (int)env('CHATGPT_MAX_TOKENS', 16384),
            'http_timeout' => (int)env('CHATGPT_HTTP_TIMEOUT', 740),
        ],
        'gpt-4o-mini' => [
            'class'        => ChatGPT5Driver::class,
            'api_key'      => env('CHATGPT_TRANSLATIONS_API_KEY'),
            'model'        => env('CHATGPT_MODEL', 'gpt-4o-mini'),
            'max_tokens'   => (int)env('CHATGPT_MAX_TOKENS', 16384),
            'http_timeout' => (int)env('CHATGPT_HTTP_TIMEOUT', 500),
        ],
    ],
];
