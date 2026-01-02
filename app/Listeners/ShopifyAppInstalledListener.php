<?php

namespace App\Listeners;

use App\Actions\Catalogue\Shop\External\Shopify\GetShopifyStore;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\FulfilmentService\StoreFulfilmentService;
use App\Actions\Dropshipping\Shopify\Webhook\CreateShopifyWebhooks;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
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

        } else if($shopifyUser->external_shop_id) {
            $store = GetShopifyStore::run($shopifyUser);

            $country = Country::where('code', Arr::get($store, 'data.shop.billingAddress.countryCodeV2'))->first();
            UpdateShop::make()->action($shopifyUser->externalShop, [
                'name' => Arr::get($store, 'data.shop.name'),
                'country_id' => $country->id
            ]);
        }

    }
}
