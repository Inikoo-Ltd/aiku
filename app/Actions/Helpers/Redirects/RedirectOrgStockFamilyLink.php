<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOrgStockFamilyLink extends OrgAction
{
    public function handle(OrgStockFamily $orgStockFamily): RedirectResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse = $orgStockFamily->organisation->warehouses()->first();

        return Redirect::to(route('grp.org.warehouses.show.inventory.org_stock_families.show', [
            $orgStockFamily->organisation->slug,
            $warehouse->slug,
            $orgStockFamily->slug,
        ]));
    }

    public function asController(OrgStockFamily $orgStockFamily, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($orgStockFamily->organisation, $request);

        return $this->handle($orgStockFamily);
    }
}
