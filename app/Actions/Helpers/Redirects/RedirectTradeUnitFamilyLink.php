<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectTradeUnitFamilyLink extends GrpAction
{
    public function handle(TradeUnitFamily $tradeUnitFamily): RedirectResponse
    {
        return Redirect::to(route('grp.trade_units.families.show', [$tradeUnitFamily->slug]));
    }

    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($tradeUnitFamily);
    }
}
