<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 19:59:15 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class ModelHydrateSingleTradeUnits
{
    use AsObject;


    public function handle(Product|MasterAsset|OrgStock|Stock $model): Product|MasterAsset|OrgStock|Stock
    {
        $model->update([
            'is_single_trade_unit' => $model->tradeUnits()->count() == 1,
        ]);

        return $model;
    }

}
