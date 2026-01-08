<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

class GetShopifyStore extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_store {shopify_user}';

    public function handle(ShopifyUser $shopifyUser): array
    {
        return $shopifyUser->getShopifyShop();
    }

    public function asCommand(Command $command)
    {
        $shopifyUser = ShopifyUser::find($command->argument('shopify_user'));

        $this->handle($shopifyUser);
    }
}
