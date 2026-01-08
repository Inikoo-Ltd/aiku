<?php

/*
 * author Louis Perez
 * created on 08-01-2026-15h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\CloneCatalogueStructure;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairDepartmentAndSubDepartmentInShop
{
    use WithActionUpdate;

    public function handle(Shop $shop, Command $command): void
    {
        $command->line('Running repair from master [' . $shop->masterShop->code . '] to Shop:' . $shop->code);
        CloneCatalogueStructure::dispatch($shop->masterShop, $shop, false, true, true);
    }


    public string $commandSignature = 'repair:department-sub-department-in-shop';

    public function asCommand(Command $command): void
    {
        $shops = Shop::where('state', ShopStateEnum::OPEN)->get();
        foreach($shops as $shop) {
            if($shop->masterShop) $this->handle($shop, $command);
        }
    }

}
