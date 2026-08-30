<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetShopifyStore extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_store {shopify_user}';

    public function handle(ShopifyUser $shopifyUser): array
    {
        $response = $shopifyUser->getShopifyShop();

        $domain = Arr::get($response, 'data.shop.primaryDomain.url');
        $shop = $shopifyUser->externalShop;

        if ($domain && $shop) {
            $shop->update([
                'data' => array_merge($shop->data ?? [], [
                    'external_domain' => preg_replace('#^https?://#', '', $domain)
                ])
            ]);
        }

        return $response;
    }

    public function asCommand(Command $command): void
    {
        $shopifyUser = ShopifyUser::find($command->argument('shopify_user'));

        $this->handle($shopifyUser);
    }
}
