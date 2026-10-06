<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsCommand;

class CheckExternalShopWixConnection extends OrgAction
{
    use WithWixExternalShopApi;

    use AsCommand;

    public string $commandSignature = 'shop-external:check-wix-connection {shop}';

    public function handle(Shop $shop): void
    {
        $wixUser = WixUser::where('external_shop_id', $shop->id)->first();

        $error = null;

        if (!$wixUser) {
            $error = __('The shop is not connected to a Wix site');
        } else {
            $instance = $this->getWixAppInstance($wixUser);

            if (Arr::has($instance, 'message') || !Arr::get($instance, 'instance')) {
                $error = Arr::get($instance, 'message') ?: __('Could not reach the Wix site.');
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
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))
            ->firstOrFail();
        $this->handle($shop);
    }
}
