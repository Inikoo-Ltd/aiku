<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use App\Models\Helpers\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetWixStore extends OrgAction
{
    use WithWixExternalShopApi;

    public string $commandSignature = 'external_shop:wix_store {shop}';

    public function handle(Shop $shop, WixUser $wixUser): array
    {
        $properties = Arr::get($this->getWixSiteProperties($wixUser), 'properties', []);
        $siteUrl    = $wixUser->site_url ?: Arr::get($wixUser->data, 'url');

        $settings = $shop->settings ?? [];
        data_set($settings, 'wix.instance_id', $wixUser->wix_instance_id);
        data_set($settings, 'wix.site_id', $wixUser->wix_site_id);
        data_set($settings, 'wix.site_url', $siteUrl);

        $data = $shop->data ?? [];
        if ($siteUrl) {
            data_set($data, 'external_domain', preg_replace('#^https?://#', '', rtrim($siteUrl, '/')));
        }

        $shop->update([
            'settings' => $settings,
            'data'     => $data,
        ]);

        $shopData = [];

        if ($name = Arr::get($properties, 'siteDisplayName') ?: Arr::get($properties, 'businessName')) {
            $shopData['name'] = $name;
        }

        if ($country = Country::where('code', Arr::get($properties, 'address.country'))->first()) {
            $shopData['country_id'] = $country->id;
        }

        if ($shopData) {
            UpdateShop::make()->action($shop, $shopData);
        }

        return $properties;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $this->handle($shop, $shop->wixUser()->firstOrFail());
    }
}
