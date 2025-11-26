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

class SyncProductOrgStocksFromTradeUnits
{
    use asObject;

    public function handle(Product $product): Product
    {
        $orgStocks  = [];

        foreach ($product->tradeUnits  as $tradeUnit) {

            foreach ($tradeUnit->stocks as $stock) {
                $orgStock = $stock->orgStocks->where('organisation_id', $product->organisation_id)->first();


                if ($orgStock) {
                    $packedIn        = null;
                    $tradeUnitsCount = $orgStock->tradeUnits->count();
                    if ($tradeUnitsCount == 1) {
                        $packedIn = $orgStock->tradeUnits->first()->pivot->quantity;
                        // If packedIn is not an integer, set it to null
                        if ($packedIn !== null) {
                            $pf = (float)$packedIn;
                            if (floor($pf) != $pf) {
                                $packedIn = null;
                            }
                        }

                        if ($packedIn !== null) {
                            $packedIn = (int)$packedIn;
                        }
                    }
                    list($smallestDividend, $correspondingDivisor) = findSmallestFactors($stock->pivot->quantity);

                    $orgStocks[$orgStock->id] = [
                        'quantity'                  => $tradeUnit->pivot->quantity / $stock->pivot->quantity,
                        'trade_units_per_org_stock' => $packedIn,
                        'divisor'                   => $correspondingDivisor,
                        'dividend'                  => $smallestDividend
                    ];
                }
            }
        }

        $product->orgStocks()->sync($orgStocks);


        return $product;
    }

}
