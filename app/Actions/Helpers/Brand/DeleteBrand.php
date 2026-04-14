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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteBrand extends OrgAction
{
    public function handle(Brand $brand): bool
    {
        $brand->tradeUnits()->detach();
        $brand->products()->detach();
        $brand->delete();
        return true;
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.trade_units.brands.index');
    }

    public function asController(Brand $brand, ActionRequest $request): bool
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($brand);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($brand);
    }

}
