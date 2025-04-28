<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementEditAuthorisation;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWarehouse extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseManagementEditAuthorisation;


    public function handle(Warehouse $warehouse): Warehouse
    {
        $warehouse->locations()->delete();
        $warehouse->warehouseAreas()->delete();
        $warehouse->delete();
        return $warehouse;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->warehouse->state != WarehouseStateEnum::IN_PROCESS) {
            $validator->errors()->add('delete', 'Only warehouses in process can be deleted');
        }
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.index');
    }

}
