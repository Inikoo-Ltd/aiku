<?php

namespace App\Actions\Inventory\PickingTrolley;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingTrolleys;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePickingTrolleys;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePickingTrolleys;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Inventory\PickingTrolley;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StorePickingTrolley extends OrgAction
{
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): PickingTrolley
    {
        $modelData['group_id'] = $warehouse->group_id;
        $modelData['organisation_id'] = $warehouse->organisation_id;
        $modelData['warehouse_id'] = $warehouse->id;

        $pickingTrolley = PickingTrolley::create($modelData);

        WarehouseHydratePickingTrolleys::dispatch($pickingTrolley->warehouse);
        OrganisationHydratePickingTrolleys::dispatch($pickingTrolley->organisation);
        GroupHydratePickingTrolleys::dispatch($pickingTrolley->group);

        return $pickingTrolley;
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'picking_trolleys',
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

    public function inWarehouse(Warehouse $warehouse, ActionRequest $request): PickingTrolley
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(PickingTrolley $pickingTrolley): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picking_trolleys.show', [
            $pickingTrolley->organisation->slug,
            $pickingTrolley->warehouse->slug,
            $pickingTrolley->slug,
        ]);
    }
}
