<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Jul 2023 12:02:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Providers\ElasticSearchServiceProvider;
use App\Providers\GDriveServiceProvider;
use App\Providers\NumberMacroServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'aiku'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool)env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_GB',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypted service and should be set
    | to a random, 32-character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */


    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\MacroServiceProvider::class,
        ElasticSearchServiceProvider::class,
        GDriveServiceProvider::class,
        NumberMacroServiceProvider::class,

    ])->toArray(),

    /*
    'providers' => [



        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Comms\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,


        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\MacroServiceProvider::class,
        ElasticSearchServiceProvider::class,

    ],
*/
    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded, so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

    'po_editor_api_key' => env('PO_EDITOR_READ_ONLY_API_KEY', ''),
    'aurora_image_path' => env('AURORA_IMAGE_PATH'),


    'domain'                => env('APP_DOMAIN'),
    'cloudflare_api_token'  => env('CLOUDFLARE_API_TOKEN'),
    'cloudflare_api_url'    => env('CLOUDFLARE_API_URL'),
    'cloudflare_account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'local'                 => [
        'retina_fulfilment_domain'   => env('LOCAL_FULFILMENT_RETINA_DOMAIN'),
        'retina_dropshipping_domain' => env('LOCAL_DROPSHIPPING_RETINA_DOMAIN'),
        'retina_b2b_domain'          => env('LOCAL_B2B_RETINA_DOMAIN'),
    ],


    'currency_exchange' => [
        'pivot'     => env('EXCHANGE_PIVOT_CURRENCY', 'GBP'),
        'providers' => [
            'currency_beacon' => env('CURRENCY_EXCHANGE_CURRENCY_BEACON_API_KEYS')
        ]
    ],

    'with_user_legacy_passwords'     => env('WITH_USER_LEGACY_PASSWORDS', false),
    'with_web_user_legacy_passwords' => env('WITH_WEB_USER_LEGACY_PASSWORDS', false),

    'dice_bear'         => [
        'mock' => env('DICE_BEAR_MOCK', false),
        'url'  => env('DICE_BEAR_API_URL', 'https://api.dicebear.com/9.x'),
    ],
    'log_user_requests' => env('LOG_USER_REQUESTS', false),

    'default_outbox_builder'              => env('DEFAULT_OUTBOX_BUILDER', 'beefree'),
    'send_email_in_non_production_env'    => env('SEND_EMAIL_IN_NON_PRODUCTION_ENV', false),
    'email_address_in_non_production_env' => env('EMAIL_ADDRESS_IN_NON_PRODUCTION_ENV', 'dev@aiku.io'),
    'test_email_to_address'               => env('TEST_EMAIL_TO_ADDRESS'),
    'unpaid_invoices_unknown_before'      => env('UNPAID_INVOICES_UNKNOWN_BEFORE'),

    'sandbox' => [

        'shipper_itd_token' => env('ITD_TOKEN'),
        'shipper_apc_token' => env('APC_TOKEN'),


        'local_share_url' => env('SANDBOX_SHARE_URL'),
        'checkout_com'    => [
            'public_key'      => env('CHECKOUT_COM_PUBLIC_KEY'),
            'secret_key'      => env('CHECKOUT_COM_SECRET_KEY'),
            'payment_channel' => env('CHECKOUT_COM_PAYMENT_CHANNEL'),
        ],
        'luigisbox' => [
            'tracking_id'      => env('LS_TRACKING_ID'),
            'private_key'      => env('LS_PRIVATE_KEY'),
        ],
    ]

];
