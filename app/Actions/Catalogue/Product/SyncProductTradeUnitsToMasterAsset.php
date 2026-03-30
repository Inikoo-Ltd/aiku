<?php

/*
 * author Louis Perez
 * created on 27-03-2026-16h-50m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class SyncProductTradeUnitsToMasterAsset extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Product $product): void
    {
        $tradeUnits = $product->tradeUnits
            ->map(function ($tradeUnit) {
                $tradeUnit->quantity = $tradeUnit->pivot->quantity;

                return $tradeUnit;
            })->toArray();

        UpdateMasterAsset::run($product->masterProduct, [
            'trade_units' => $tradeUnits
        ]);

        MasterAssetHydrateMismatch::run($product->masterProduct);
    }

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisation(group(), $request);

        $this->handle($product);
    }

}
