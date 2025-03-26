<?php

namespace App\Listeners;

use App\Actions\Dropshipping\Shopify\Webhook\StoreWebhooksToShopify;
use App\Models\Dropshipping\ShopifyUser;
use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;

class ShopifyAppInstalledListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AppInstalledEvent $event): void
    {
        $shopifyUser = ShopifyUser::find($event->shopId->toNative());

        StoreWebhooksToShopify::run($shopifyUser);
    }
}
