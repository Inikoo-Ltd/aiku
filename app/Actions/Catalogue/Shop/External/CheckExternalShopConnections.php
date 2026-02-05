<?php

/*
 * author Louis Perez
 * created on 05-02-2026-09h-22m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Shop\External;

use App\Actions\Catalogue\Shop\External\Faire\CheckExternalShopFaireConnection;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use Lorisleiva\Actions\Concerns\AsCommand;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class CheckExternalShopConnections extends RetinaAction
{
    use AsCommand;

    public string $commandSignature = 'shop-external:check-all-shop-connections';

    public function handle(Shop $shop): void
    {
        if($shop->engine == ShopEngineEnum::FAIRE){
            CheckExternalShopFaireConnection::run($shop);
        }

        if($shop->engine == ShopEngineEnum::SHOPIFY){
            // TODO. The file below is not done yet anyway
            // CheckExternalShopShopifyConnection::run($shop);
        }
    }
    
    public function asCommand(Command $command): void
    {
        $command->info('Checking all shop connections');
        
        $shopQuery = Shop::where('type', ShopTypeEnum::EXTERNAL)
                            ->where('state', ShopStateEnum::OPEN);
        
        $total = (clone $shopQuery)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->start();

        Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('state', ShopStateEnum::OPEN)
            ->each(function ($shop) use ($bar) { 
                $this->handle($shop);
                $bar->advance();
            });

            
        $bar->finish();
        $command->newLine();
    }
}
