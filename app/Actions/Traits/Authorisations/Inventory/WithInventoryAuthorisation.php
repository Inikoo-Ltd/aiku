<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Aug 2024 15:18:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithInventoryAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if (str_starts_with($request->route()->getName(), 'grp.overview')) {
            return $request->user()->authTo("group-overview");
        }

        $warehousesIDs = $this->organisation->warehouses()->pluck('id')->toArray();

        $editPermissions = [
            "inventory.{$this->organisation->id}.edit",
        ];
        foreach ($warehousesIDs as $warehouseId) {
            $editPermissions[] = "supervisor-stocks.$warehouseId.view";
        }

        $this->canEdit = $request->user()->authTo(
            $editPermissions
        );

        $viewPermissions = [
            "inventory.{$this->organisation->id}.view",
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
