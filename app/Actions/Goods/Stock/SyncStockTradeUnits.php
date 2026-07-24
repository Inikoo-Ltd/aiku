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
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePackedIn;
use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Goods\Stock;
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
            // Update childrens
            SyncOrgStockTradeUnits::run($orgStock, $tradeUnitsData);
            OrgStockHydratePackedIn::run($orgStock);
            // Update product packed_in data
            foreach ($orgStock->products as $product) {
                SyncProductOrgStocksFromTradeUnits::run($product);
            }
            SyncDeliveryNoteItemsRequiredPickQuantity::dispatch($orgStock);
        }

        return $stock;
    }
}
