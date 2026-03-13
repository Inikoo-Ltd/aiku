<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-10m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMismatch implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $masterAssetID): string
    {
        return $masterAssetID ?? 'empty';
    }

    public function handle($onlyStats = false): void
    {
        if (!$onlyStats) {
            MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)
                ->orderBy('id')
                ->chunkById(1000, function ($masterProducts) {
                    foreach ($masterProducts as $masterProduct) {

                        $masterAssetTradeUnits = $masterProduct->tradeUnits->pluck('pivot.quantity', 'id');

                        $products = $masterProduct->products;
                        foreach ($products as $product) {

                            if ($masterProduct->mismatch_detected == true) {
                                continue;
                            }

                            $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

                            $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
                            $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

                            if ($diffFromMaster->isNotEmpty() || $diffFromProduct->isNotEmpty()) {
                                $masterProduct->updateQuietly(['mismatch_detected' => true]);
                            }
                        }
                    }
                });
        }

        MasterShop::each(function ($masterShop) {
            $countMismatch = MasterAsset::where('master_shop_id', $masterShop->id)
                ->where('mismatch_detected', true)
                ->count();

            $masterShop->stats()->update(['number_mismatched_master_products' => $countMismatch]);
        });

    }

}
