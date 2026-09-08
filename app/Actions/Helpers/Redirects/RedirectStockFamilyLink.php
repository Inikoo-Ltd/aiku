<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Goods\StockFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectStockFamilyLink extends OrgAction
{
    public function handle(StockFamily $stockFamily): RedirectResponse
    {
        return Redirect::to(route('grp.goods.stock-families.show', [$stockFamily->slug]));
    }

    public function asController(StockFamily $stockFamily, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stockFamily);
    }
}
