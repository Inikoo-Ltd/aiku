<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Jul 2025 22:28:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Traits;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingWeightFromTradeUnits;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateCustomerExclusiveProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateProducts;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;

trait WithProductOrgStocks
{
    /**
     * Process org stocks data and calculate trade units per org stock
     *
     * @param  array  $orgStocksRaw
     *
     * @return array
     */
    protected function processOrgStocks(array $orgStocksRaw): array
    {
        $orgStocks = [];

        foreach ($orgStocksRaw as $orgStockId => $item) {
            $orgStock = OrgStock::find($orgStockId);

            if ($orgStock) {
                $tradeUnitsPerOrgStock = null;


                if ($orgStock->state == OrgStockStateEnum::ABNORMALITY) {
                    if ($orgStock->tradeUnits->count() == 1) {
                        $tradeUnitsPerOrgStock = $orgStock->tradeUnits->first()->pivot->quantity;
                    }
                } else {
                    $stock = $orgStock->stock;
                    if ($stock->tradeUnits->count() == 1) {
                        $tradeUnitsPerOrgStock = $stock->tradeUnits->first()->pivot->quantity;
                    }
                }

                if ($tradeUnitsPerOrgStock != null && floor($tradeUnitsPerOrgStock) != $tradeUnitsPerOrgStock) {
                    $tradeUnitsPerOrgStock = null;
                }

                $orgStocks[$orgStockId]                              = $item;
                $orgStocks[$orgStockId]['trade_units_per_org_stock'] = (int)$tradeUnitsPerOrgStock;
            }
        }

        return $orgStocks;
    }


    protected function syncOrgStocks(Product $product, array $orgStocksRaw): Product
    {
        $orgStocks = $this->processOrgStocks($orgStocksRaw);
        $product->orgStocks()->sync($orgStocks);
        $product->refresh();


        $product = $this->syncTradeUnits($product);
        $product->refresh();

        return $product;
    }

    protected function syncTradeUnits(Product $product): Product
    {
        $tradeUnits = [];
        foreach ($product->orgStocks as $orgStock) {
            foreach ($orgStock->tradeUnits as $tradeUnit) {
                $tradeUnits[$tradeUnit->id] = [
                    'quantity' => $orgStock->pivot->quantity * $tradeUnit->pivot->quantity,
                ];
            }
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

        return $product;
    }

}
