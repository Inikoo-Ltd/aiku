<?php

namespace App\Providers;

use App\Events\BroadcastFulfilmentCustomerNotification;
use App\Listeners\MeasurementSharedListener;
use App\Listeners\RememberMcpClientName;
use App\Listeners\SetAuditUserContext;
use App\Listeners\SetAuditUserContextFromToken;
use Illuminate\Auth\Events\Authenticated;
use App\Listeners\ShopifyAuthenticatedShopWebhookRegister;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Mcp\Events\SessionInitialized;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Osiset\ShopifyApp\Messaging\Events\ShopAuthenticatedEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Authenticated::class => [
            SetAuditUserContext::class,
        ],
        TokenAuthenticated::class => [
            SetAuditUserContextFromToken::class,
        ],
       /* BroadcastFulfilmentCustomerNotification::class => [
            MeasurementSharedListener::class
        ],*/
        ShopAuthenticatedEvent::class => [
            ShopifyAuthenticatedShopWebhookRegister::class
        ],
        SessionInitialized::class => [
            RememberMcpClientName::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
