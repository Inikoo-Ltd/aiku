<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWarehouseArea extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;

    public function handle(WarehouseArea $warehouseArea): WarehouseArea
    {
        $warehouseArea->locations()->delete();
        $warehouseArea->stats()->delete();
        $warehouseArea->delete();

        return $warehouseArea;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("inventory.edit");
    }

    public function asController(WarehouseArea $warehouseArea, ActionRequest $request): WarehouseArea
    {
        $this->initialisationFromWarehouse($warehouseArea->warehouse, $request);

        return $this->handle($warehouseArea);
    }


    public function htmlResponse(WarehouseArea $warehouseArea): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.infrastructure.dashboard', $warehouseArea->warehouse->slug);
    }

}
