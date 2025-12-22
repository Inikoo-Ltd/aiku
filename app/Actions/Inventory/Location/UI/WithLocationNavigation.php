<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 10:21:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Database\Eloquent\Builder;

trait WithLocationNavigation
{
    use WithNavigation;

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var Location $model */
        if ($this->parent instanceof Warehouse) {
            $query->where('locations.warehouse_id', $model->warehouse_id);
        } else {
            $query->where('locations.warehouse_area_id', $model->warehouse_area_id);
        }
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var Location $model */
        return match ($routeName) {
            'grp.org.warehouses.show.inventory.locations.show',
            'grp.org.warehouses.show.inventory.locations.edit'
            => [
                'location' => $model->slug,
            ],
            'grp.org.warehouses.show.inventory.warehouse-areas.show.locations.show',
            'grp.org.warehouses.show.inventory.warehouse-areas.show.locations.edit' => [
                'warehouseArea' => $model->warehouseArea->slug,
                'location'      => $model->slug,
            ],
            'grp.org.warehouses.show.infrastructure.locations.show',
            'grp.org.warehouses.show.infrastructure.locations.edit' => [
                'organisation' => $model->organisation->slug,
                'warehouse'    => $model->warehouse->slug,
                'location'     => $model->slug,
            ],
            'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show',
            'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.edit' => [
                'organisation'  => $model->organisation->slug,
                'warehouse'     => $model->warehouse->slug,
                'warehouseArea' => $model->warehouseArea->slug,
                'location'      => $model->slug,
            ],
            default => [],
        };
    }

}
