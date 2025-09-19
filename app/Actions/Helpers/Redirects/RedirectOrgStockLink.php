<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOrgStockLink extends OrgAction
{
    public function handle(OrgStock $orgStock): ?RedirectResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse=$orgStock->organisation->warehouses()->first();

        $url = route('grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show', [
            $orgStock->organisation->slug,
            $warehouse->slug,
            $orgStock->slug
        ]);
        return Redirect::to($url);
    }



    public function asController(OrgStock $orgStock, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock);
    }

}
