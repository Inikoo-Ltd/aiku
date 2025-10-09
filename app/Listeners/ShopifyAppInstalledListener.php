<?php

namespace App\Listeners;

use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\FulfilmentService\StoreFulfilmentService;
use App\Actions\Dropshipping\Shopify\Webhook\CreateShopifyWebhooks;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;
use Sentry;

class ShopifyAppInstalledListener
{
    use WithActionUpdate;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @throws \Exception
     */
    public function handle(AppInstalledEvent $event): void
    {
        $shopifyUser = ShopifyUser::find($event->shopId->toNative());

        if (!$shopifyUser) {
            Sentry::captureMessage('Shopify user not found in ShopifyAppInstalledListener');
            return;
        }


        CreateShopifyWebhooks::run($shopifyUser);

        if ($shopifyUser->customerSalesChannel) {

            CheckShopifyChannel::run($shopifyUser->customerSalesChannel);
            StoreFulfilmentService::run($shopifyUser->customerSalesChannel);

        }

    }
}
