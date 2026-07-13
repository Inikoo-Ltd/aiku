<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\OrgAction;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\ActionRequest;

class DeleteOrgStockFromLocation extends OrgAction
{
    use WithLocationOrgStockActionAuthorisation;

    public function handle(Location $location, OrgStock $orgStock): void
    {
        dd('xxx');
    }

    public function asController(Location $location, OrgStock $orgStock, ActionRequest $request): void
    {
        $this->initialisation($location->organisation, $request);

        $this->handle($location, $orgStock);
    }
}
