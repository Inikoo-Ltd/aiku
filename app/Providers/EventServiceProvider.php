<?php

namespace App\Providers;

use App\Events\BroadcastFulfilmentCustomerNotification;
use App\Listeners\MeasurementSharedListener;
use App\Listeners\ShopifyAuthenticatedShopWebhookRegister;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
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
        BroadcastFulfilmentCustomerNotification::class => [
            MeasurementSharedListener::class
        ],
        ShopAuthenticatedEvent::class => [
            ShopifyAuthenticatedShopWebhookRegister::class
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
