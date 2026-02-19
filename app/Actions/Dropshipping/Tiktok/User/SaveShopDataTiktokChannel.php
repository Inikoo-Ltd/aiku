<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveShopDataTiktokChannel
{
    use asAction;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser): void
    {
        $shopData = [];
        $tiktokShop = $tiktokUser->getAuthorizedShop();
        $tiktokWarehouses = $tiktokUser->getWarehouses();
        $tiktokUser->updateWebhook('ORDER_STATUS_CHANGE', route('webhooks.tiktok.orders.create'));

        $defaultWarehouse = null;
        foreach (Arr::get($tiktokWarehouses, 'data.warehouses', []) as $warehouse) {
            if ($warehouse['is_default'] === true) {
                $defaultWarehouse = $warehouse;
            }
        }

        data_set($shopData, 'data.authorized_shop', Arr::get($tiktokShop, 'data.shops'));
        data_set($shopData, 'data.warehouse', Arr::get($tiktokWarehouses, 'data.warehouses'));
        data_set($shopData, 'tiktok_shop_id', Arr::get($tiktokShop, 'data.shops.0.id'));
        data_set($shopData, 'tiktok_warehouse_id', Arr::get($defaultWarehouse, 'id'));
        data_set($shopData, 'tiktok_shop_chiper', Arr::get($tiktokShop, 'data.shops.0.chiper'));

        $this->update($tiktokUser, $shopData);
    }

    public function getCommandSignature(): string
    {
        return 'tiktok:data_update {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $this->handle($customerSalesChannel->user);

        $command->info("\nShop data updated successfully.");
    }
}
