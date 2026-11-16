<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Dispatching\PackingBench;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StorePackingBench extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): PackingBench
    {
        $modelData['group_id']        = $warehouse->group_id;
        $modelData['organisation_id'] = $warehouse->organisation_id;
        $modelData['warehouse_id']    = $warehouse->id;

        return PackingBench::create($modelData);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'packing_benches',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->warehouse->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
        ];
    }

    public function action(Warehouse $warehouse, array $modelData): PackingBench
    {
        $this->asAction  = true;
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $modelData);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function inWarehouse(Warehouse $warehouse, ActionRequest $request): PackingBench
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(PackingBench $packingBench): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.packing_benches.index', [
            $packingBench->organisation->slug,
            $packingBench->warehouse->slug,
        ]);
    }
}
