<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateWarehouseAutoDisassociateEmptyLocations extends OrgAction
{
    use WithActionUpdate;

    public function handle(Warehouse $warehouse, array $modelData): Warehouse
    {
        data_set($modelData, 'settings.auto_disassociate_empty_locations', (bool) Arr::pull($modelData, 'auto_disassociate_empty_locations'));

        return $this->update($warehouse, $modelData, ['settings']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(
            WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation)
        );
    }

    public function rules(): array
    {
        return [
            'auto_disassociate_empty_locations' => ['required', 'boolean'],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        $this->handle($warehouse, $this->validatedData);
    }

    public function action(Warehouse $warehouse, array $modelData): Warehouse
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, $modelData);

        return $this->handle($warehouse, $this->validatedData);
    }
}
