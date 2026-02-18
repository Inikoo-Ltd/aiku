<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 15:59:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickedBay;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickedBays;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePickedBays;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePickedBays;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StorePickedBay extends OrgAction
{
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): PickedBay
    {
        $modelData['group_id'] = $warehouse->group_id;
        $modelData['organisation_id'] = $warehouse->organisation_id;
        $modelData['warehouse_id'] = $warehouse->id;

        $pickedBay = PickedBay::create($modelData);

        WarehouseHydratePickedBays::dispatch($pickedBay->warehouse);
        OrganisationHydratePickedBays::dispatch($pickedBay->organisation);
        GroupHydratePickedBays::dispatch($pickedBay->group);

        return $pickedBay;
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'picked_bays',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->warehouse->id],
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules['code'] = ['required', 'max:64', 'string'];
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function inWarehouse(Warehouse $warehouse, ActionRequest $request): PickedBay
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(PickedBay $pickedBay): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picked_bays.show', [
            $pickedBay->organisation->slug,
            $pickedBay->warehouse->slug,
            $pickedBay->slug,
        ]);
    }
}
