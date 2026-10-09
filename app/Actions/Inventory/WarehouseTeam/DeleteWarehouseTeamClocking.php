<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 13:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam;

use App\Actions\HumanResources\Clocking\DeleteClocking;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWarehouseTeamAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteWarehouseTeamClocking extends OrgAction
{
    use WithWarehouseTeamAuthorisation;

    public function handle(Warehouse $warehouse, Clocking $clocking): Clocking
    {
        self::ensureTeamClocking($warehouse, $clocking);

        return DeleteClocking::make()->handle($clocking);
    }

    public function asController(Warehouse $warehouse, Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $clocking);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
