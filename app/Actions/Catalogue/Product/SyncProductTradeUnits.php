<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingWeightFromTradeUnits;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateCustomerExclusiveProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateProducts;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class SyncProductTradeUnits
{
    use asObject;

    public function handle(Product $product, array $tradeUnitsRaw): Product
    {
        $tradeUnits = [];
        foreach ($tradeUnitsRaw as $tradeUnitID => $tradeUnitData) {
            $tradeUnit                  = TradeUnit::find($tradeUnitID);
            $tradeUnits[$tradeUnit->id] = [
                'quantity' => Arr::get($tradeUnitData, 'quantity')
            ];

        }

        $product->tradeUnits()->sync($tradeUnits);

        ProductHydrateGrossWeightFromTradeUnits::dispatch($product);
        ProductHydrateBarcodeFromTradeUnit::dispatch($product);
        ProductHydrateMarketingWeightFromTradeUnits::dispatch($product);
        ProductHydrateMarketingDimensionFromTradeUnits::dispatch($product);

        foreach ($product->tradeUnits as $tradeUnitData) {
            $tradeUnit = TradeUnit::find($tradeUnitData->id);
            if ($tradeUnit) {
                if ($product->exclusive_for_customer_id) {
                    TradeUnitsHydrateCustomerExclusiveProducts::dispatch($tradeUnit);
                } else {
                    TradeUnitsHydrateProducts::dispatch($tradeUnit);
                }
            }
        }

        $product->refresh();

        SyncProductOrgStocksFromTradeUnits::run($product);

        return $product;
    }

}
