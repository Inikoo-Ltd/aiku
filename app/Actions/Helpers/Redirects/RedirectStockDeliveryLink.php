<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectStockDeliveryLink extends OrgAction
{
    public function handle(StockDelivery $stockDelivery): RedirectResponse
    {
        return Redirect::to(route('grp.org.procurement.stock_deliveries.show', [$stockDelivery->organisation->slug, $stockDelivery->slug]));
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stockDelivery);
    }
}
