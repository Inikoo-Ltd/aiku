<?php

/*
 * author Louis Perez
 * created on 05-03-2026-16h-43m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;

class CountProductsWithMismatchTradeUnits
{
    use WithActionUpdate;

    protected function handle(MasterShop $masterShop, Command $command): void
    {
        $totalCount = 0;
        $totalWrong = 0;
        MasterAsset::where('master_shop_id', $masterShop->id)->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true)
            ->orderBy('id')
            ->chunkById(1000, function ($masterProducts) use (&$totalCount, &$totalWrong) {
                foreach ($masterProducts as $masterProduct) {
                    $totalCount++;
                    $masterAssetTradeUnits = $masterProduct->tradeUnits->pluck('pivot.quantity', 'id');

                    $products     = $masterProduct->products;
                    $hasDifferent = false;
                    foreach ($products as $product) {
                        $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

                        $hasDifferent = $masterAssetTradeUnits->diffAssoc($productTradeUnits)->count()
                            || $productTradeUnits->diffAssoc($masterAssetTradeUnits)->count();


                        if ($hasDifferent) {
                            break;
                        }
                    }

                    if ($hasDifferent) {
                        $totalWrong++;
                    }
                }
            });

        echo "Result:   $totalWrong / $totalCount ".percentage($totalWrong, $totalCount)."\n";
    }

    public string $commandSignature = 'count:products_with_mismatch_trade_units {masterShop}';

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))
            ->where('status', true)
            ->firstOrFail();
        $this->handle($masterShop, $command);
    }

}
