<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UnsetOrgStockShipmentPackaging extends OrgAction
{
    public function handle(OrgStock $orgStock): OrgStock
    {
        $orgStock->update(['is_shipment_packaging' => false]);

        return $orgStock;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisation($organisation, $request);
        abort_unless($orgStock->organisation_id === $organisation->id, 404);

        return $this->handle($orgStock);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
