<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Jul 2025 22:28:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Traits;

use App\Actions\Catalogue\Product\AttachTradeUnitToProduct;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;

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
            $orgStock              = OrgStock::find($orgStockId);
            $tradeUnitsPerOrgStock = null;

            if ($orgStock->type == OrgStockStateEnum::ABNORMALITY) {
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
            $orgStocks[$orgStockId]['trade_units_per_org_stock'] = (int) $tradeUnitsPerOrgStock;
        }

        return $orgStocks;
    }


    protected function syncOrgStocks(Product $product, array $orgStocksRaw): Product
    {
        $orgStocks = $this->processOrgStocks($orgStocksRaw);
        $product->orgStocks()->sync($orgStocks);
        return $product;
    }

    protected function associateTradeUnits(Product $product): Product
    {
        $tradeUnits = [];
        foreach ($product->orgStocks as $orgStock) {
            foreach ($orgStock->tradeUnits as $tradeUnit) {
                $tradeUnits[$tradeUnit->id] = [
                    'quantity' => $orgStock->pivot->quantity * $tradeUnit->pivot->quantity,
                ];
            }
        }

        foreach ($tradeUnits as $tradeUnitId => $tradeUnitData) {
            $tradeUnit = TradeUnit::find($tradeUnitId);
            AttachTradeUnitToProduct::run($product, $tradeUnit, [
                'quantity' => $tradeUnitData['quantity'],
                'notes'    => Arr::get($tradeUnitData, 'notes'),
            ]);
        }
        $product->refresh();

        return $product;
    }

}
