<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Aug 2025 21:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingTradeUnitsToMasterAsset
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterProduct, Command $command): void
    {
        $seederShop = $this->getSeederShop($masterProduct->masterShop);


        $product = $masterProduct->products()->where('shop_id', $seederShop->id)->first();
        $command->info($masterProduct->code.' '.$seederShop->slug.' '.$masterProduct->products()->count());
        if ($product) {
            $tradeUnits = [];
            foreach ($product->tradeUnits as $tradeUnit) {
                $tradeUnits[] = $tradeUnit;
            }
            dd($masterProduct->code, $seederShop->slug, $tradeUnits);
        }
    }

    public function getSeederShop(MasterShop $masterShop): Shop
    {
        $shopId = match ($masterShop->slug) {
            'aw' => 1,
            'ds' => 13,
            'ac' => 9,
            'aroma' => 40,
            'ful' => 15
        };

        return Shop::find($shopId);
    }


    public function getCommandSignature(): string
    {
        return 'repair:add_trade_units_to_master';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        MasterAsset::where('type', 'product')->where('slug', 'jbb-01')->orderBy('id')
            ->chunk(1000, function ($models) use ($command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                }
            });


        return 0;
    }


}
