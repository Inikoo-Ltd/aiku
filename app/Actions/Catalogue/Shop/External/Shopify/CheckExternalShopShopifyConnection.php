<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsCommand;

class CheckExternalShopShopifyConnection extends OrgAction
{
    use WithShopifyExternalShopApi;
    use AsCommand;

    public string $commandSignature = 'shop-external:check-shopify-connection {shop}';

    public function handle(Shop $shop): ?string
    {
        $shopifyUser = $this->getShopifyExternalShopUser($shop);

        $error = $this->getShopifyExternalShopBlockedReason($shopifyUser);

        if (!$error) {
            $store = $this->getShopifyExternalShopStoreData($shopifyUser);

            if (Arr::has($store, 'message') || !Arr::get($store, 'shop')) {
                $error = Arr::get($store, 'message') ?: __('Could not reach the Shopify store.');
            }
        }

        $dataToBeUpdated = ['external_shop_platform_status' => is_null($error)];

        if ($error) {
            if (!$shop->external_shop_connection_failed_at) {
                data_set($dataToBeUpdated, 'external_shop_connection_failed_at', now());
                data_set($dataToBeUpdated, 'external_shop_connection_error', $error);
            }
        } else {
            data_set($dataToBeUpdated, 'external_shop_connection_failed_at', null);
        }

        $shop->update($dataToBeUpdated);

        return $error;
    }

    public function asCommand(Command $command): int
    {
        $shop  = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $error = $this->handle($shop);

        if ($error) {
            $command->error($error);

            return 1;
        }

        $command->info('Connected');

        return 0;
    }
}
