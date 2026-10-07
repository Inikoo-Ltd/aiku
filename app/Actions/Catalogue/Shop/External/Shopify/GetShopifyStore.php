<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetShopifyStore extends OrgAction
{
    use WithShopifyExternalShopApi;

    public string $commandSignature = 'external_shop:shopify_store {shopify_user}';

    public function handle(ShopifyUser $shopifyUser): array
    {
        $shop = $shopifyUser->externalShop;

        if (!$shop || $shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return ['message' => __('The Shopify store is not linked to a Shopify external shop')];
        }

        $store = $this->getShopifyExternalShopStoreData($shopifyUser);

        if (Arr::has($store, 'message')) {
            return $store;
        }

        $settings = $shop->settings ?? [];
        data_set($settings, 'shopify.shop_url', Arr::get($store, 'shop.myshopifyDomain') ?: $shopifyUser->name);
        data_set($settings, 'shopify.store_name', Arr::get($store, 'shop.name'));

        if (!Arr::get($settings, 'shopify.location_id') && $locationId = Arr::get($store, 'location.id')) {
            data_set($settings, 'shopify.location_id', $locationId);
            data_set($settings, 'shopify.location_name', Arr::get($store, 'location.name'));
        }

        $data = $shop->data ?? [];
        if ($domain = Arr::get($store, 'shop.primaryDomain.url')) {
            data_set($data, 'external_domain', preg_replace('#^https?://#', '', rtrim($domain, '/')));
        }

        $shop->update([
            'settings' => $settings,
            'data'     => $data,
        ]);

        $shopData = [];

        if ($name = Arr::get($store, 'shop.name')) {
            $shopData['name'] = $name;
        }

        if ($country = Country::where('code', Arr::get($store, 'shop.billingAddress.countryCodeV2'))->first()) {
            $shopData['country_id'] = $country->id;
        }

        if ($shopData) {
            UpdateShop::make()->action($shop, $shopData);
        }

        return $store;
    }

    public function asCommand(Command $command): int
    {
        $shopifyUser = ShopifyUser::findOrFail($command->argument('shopify_user'));

        $result = $this->handle($shopifyUser);

        if (Arr::has($result, 'message')) {
            $command->error(Arr::get($result, 'message'));

            return 1;
        }

        $command->info('Shopify store '.Arr::get($result, 'shop.name').' read');

        return 0;
    }
}
