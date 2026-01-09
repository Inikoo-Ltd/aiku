<?php
/*
 * author Louis Perez
 * created on 09-01-2026-13h-30m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\SysAdmin;

use App\Actions\Catalogue\Shop\DeleteShop;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairDeleteShopOnLocalDB
{
    public string $commandSignature = 'repair:delete_invalid_shops';

    public function handle(Shop $shop): void
    {
        DeleteShop::run($shop);
    }

    public function asCommand(Command $command): void
    {
        $invalidShops = Shop::whereNot('engine', 'aiku')->whereNotNull('master_shop_id')->get();
        if ($invalidShops->isEmpty()) {
            $command->info('No invalid shops found.');
            return;
        }

        foreach ($invalidShops as $shop) {
            $command->warn("Deleting shop ID {$shop->id}");
            $this->handle($shop);
        }

        $command->info('Repair completed.');
    }
}
