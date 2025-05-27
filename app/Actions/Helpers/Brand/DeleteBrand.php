<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\ActionRequest;

class DeleteBrand extends OrgAction
{
    public function handle(Brand $brand): Brand
    {
        $brand->tradeUnits()->detach();
        $brand->delete();
        return $brand;
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($brand);
    }

}
