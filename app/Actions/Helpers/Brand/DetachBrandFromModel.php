<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Brand\Hydrators\BrandHydrateModels;
use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\ActionRequest;

class DetachBrandFromModel extends OrgAction
{
    public function handle(TradeUnit $model, Brand $brand): void
    {
        $model->Brands()->detach([$brand->id]);
        $brand->refresh();
        BrandHydrateModels::dispatch($brand);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $brand);
    }
}
