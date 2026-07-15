<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectTradeUnitLink extends GrpAction
{
    public function handle(TradeUnit $tradeUnit): RedirectResponse
    {
        return Redirect::to(route('grp.trade_units.units.show', [$tradeUnit->slug]));
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($tradeUnit);
    }
}
