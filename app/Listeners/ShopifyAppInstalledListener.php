<?php

namespace App\Listeners;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\Webhook\StoreWebhooksToShopify;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;

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

        StoreWebhooksToShopify::run($shopifyUser);

        $shopApi = $shopifyUser->api()->getRestClient()->request('GET', '/admin/api/2024-04/shop.json');
        $store = Arr::get($shopApi, 'body.shop');

        $shopifyUser = $this->update($shopifyUser, [
            'data' => [
                'store' => $store
            ]
        ]);

        UpdateCustomerSalesChannel::run($shopifyUser->customerSalesChannel, [
            'name' => Arr::get($shopifyUser->data, 'store.name'),
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);
    }
}
