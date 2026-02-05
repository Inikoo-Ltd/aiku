<?php

/*
 * author Louis Perez
 * created on 05-02-2026-09h-33m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsCommand;

class CheckExternalShopFaireConnection extends OrgAction
{
    use AsCommand;

    public string $commandSignature = 'shop-external:check-faire-connection {shop}';

    public function handle(Shop $shop): void
    {
        $faireBrand =  $shop->getFaireBrand();
        $dataToBeUpdated = ['external_shop_platform_status' => true];
        if (!Arr::has($faireBrand, 'name')) {
            data_set($dataToBeUpdated, 'external_shop_platform_status', false);

            // Checks if it has failed before or not. Write timestamp & err msg if it hasn't failed before. Skip if it does
            if (!$shop->external_shop_connection_failed_at) {
                data_set($dataToBeUpdated, 'external_shop_connection_failed_at', now());
                data_set($dataToBeUpdated, 'external_shop_connection_error', data_get($faireBrand, 'error.message'));
            }
        } else {
            // Remove connection failed at if it's okay. But do not override the error message. Maybe we'll need it some time else.
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
