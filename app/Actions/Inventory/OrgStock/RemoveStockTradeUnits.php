<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 12 May 2023 15:16:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\SupplyChain\Stock;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveStockTradeUnits
{
    use AsAction;

    public function handle(Stock $stock, array $tradeUnitsData): Stock
    {
        $stock->tradeUnits()->detach($tradeUnitsData);

        HydrateOrgStock::run($stock);

        return $stock;
    }
}
