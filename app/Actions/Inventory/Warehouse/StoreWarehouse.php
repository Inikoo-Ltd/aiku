<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:25:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateUniversalSearch;
use App\Actions\Tenancy\Tenant\Hydrators\TenantHydrateWarehouse;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWarehouse
{
    use AsAction;
    use WithAttributes;

    public function handle($modelData): Warehouse
    {
        $warehouse = Warehouse::create($modelData);
        $warehouse->stats()->create();
        TenantHydrateWarehouse::run(app('currentTenant'));

        WarehouseHydrateUniversalSearch::dispatch($warehouse);


        return $warehouse;
    }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'unique:tenant.warehouses', 'between:2,4', 'alpha'],
            'name'         => ['required', 'max:250', 'string'],
        ];
    }

    public function action($objectData): Warehouse
    {
        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }
}
