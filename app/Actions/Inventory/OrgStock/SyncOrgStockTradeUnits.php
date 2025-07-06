<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 20:55:09 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncOrgStockTradeUnits
{
    use AsAction;

    public function handle(OrgStock $orgStock, array $tradeUnitsData): OrgStock
    {
        $orgStock->tradeUnits()->sync($tradeUnitsData);

        foreach ($orgStock->tradeUnits as $tradeUnit) {
            TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
        }

        return $orgStock;
    }
}
