<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetShopifyCustomers extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_customers {shop}';

    public function handle(Shop $shop): void
    {
        $shopifyUser = ShopifyUser::where('external_shop_id', $shop->id)->first();

        if (!$shopifyUser) {
            return;
        }

        $after = null;

        do {
            $variables = ['first' => 250];
            if ($after) {
                $variables['after'] = $after;
            }

            $response = $shopifyUser->getShopifyCustomers($variables);

            if (!empty($response['error'])) {
                return;
            }

            foreach (Arr::get($response, 'data.customers.edges', []) as $edge) {
                $shopifyCustomer = Arr::get($edge, 'node', []);
                StoreCustomerFromShopify::make()->handle($shop, $shopifyCustomer);
            }

            $pageInfo = Arr::get($response, 'data.customers.pageInfo', []);
            $after = Arr::get($pageInfo, 'endCursor');
        } while (Arr::get($pageInfo, 'hasNextPage', false) && $after);
    }

    public function asCommand(Command $command): int
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shop'))
            ->get();

        foreach ($shops as $shop) {
            $this->handle($shop);
        }

        return 0;
    }
}
