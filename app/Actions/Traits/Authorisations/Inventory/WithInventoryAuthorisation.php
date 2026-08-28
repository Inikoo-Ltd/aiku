<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Aug 2024 15:18:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithInventoryAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if (str_starts_with($request->route()->getName(), 'grp.overview')) {
            return $request->user()->authTo("group-overview");
        }

        $warehousesIDs = $this->organisation->warehouses()->pluck('id')->toArray();

        $this->canEdit = $request->user()->authTo(
            WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation)
        );

        $viewPermissions = [
            "inventory.{$this->organisation->id}.view",
            "accounting.{$this->organisation->id}.view"
        ];
        foreach ($warehousesIDs as $warehouseId) {
            $viewPermissions[] = "supervisor-stocks.$warehouseId.view";
            $viewPermissions[] = "stocks.$warehouseId.view";
            $viewPermissions[] = "fulfilment.view.$warehouseId.view";
        }

        return $request->user()->authTo(
            $viewPermissions
        );
    }
}
