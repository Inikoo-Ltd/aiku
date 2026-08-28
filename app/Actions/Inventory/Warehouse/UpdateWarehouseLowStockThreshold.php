<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLowStockAudits;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateWarehouseLowStockThreshold extends OrgAction
{
    use WithActionUpdate;

    public function handle(Warehouse $warehouse, array $modelData): Warehouse
    {
        $threshold        = (float) Arr::pull($modelData, 'low_stock_threshold');
        $hasNewThreshold  = $warehouse->getLowStockThreshold() !== $threshold;

        data_set($modelData, 'settings.low_stock_threshold', $threshold);

        $warehouse = $this->update($warehouse, $modelData, ['settings']);

        if ($hasNewThreshold) {
            WarehouseHydrateLowStockAudits::dispatch($warehouse);
        }

        return $warehouse;
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
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function action(Warehouse $warehouse, array $modelData): Warehouse
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, $modelData);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function jsonResponse(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }
}
