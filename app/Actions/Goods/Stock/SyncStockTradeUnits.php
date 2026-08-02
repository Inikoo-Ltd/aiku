<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity;
use App\Actions\Goods\Stock\Hydrators\StockHydrateGrossWeightFromTradeUnits;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateStocks;
use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Goods\Stock;
use Illuminate\Support\Facades\Bus;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncStockTradeUnits
{
    use AsAction;

    public function handle(Stock $stock, array $tradeUnitsData): Stock
    {
        $stock->tradeUnits()->sync($tradeUnitsData);
        $stock = ModelHydrateSingleTradeUnits::run($stock);

        foreach ($stock->tradeUnits as $tradeUnit) {
            TradeUnitsHydrateStocks::dispatch($tradeUnit);
        }

        StockHydrateGrossWeightFromTradeUnits::dispatch($stock);

        foreach ($stock->orgStocks as $orgStock) {
            SyncOrgStockTradeUnits::run($orgStock, $tradeUnitsData);

            $jobs = $orgStock->products
                ->map(fn ($product) => SyncProductOrgStocksFromTradeUnits::makeJob($product))
                ->push(SyncDeliveryNoteItemsRequiredPickQuantity::makeJob($orgStock))
                ->all();

            Bus::chain($jobs)->dispatch();
        }

        return $stock;
    }
}
