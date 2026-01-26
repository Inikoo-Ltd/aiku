<?php

namespace App\Actions\Inventory\PickedBay;

use App\Actions\OrgAction;
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

        return PickedBay::create($modelData);
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
        return Redirect::route('grp.org.warehouses.show.inventory.picked_bays.show', [
            $pickedBay->organisation->slug,
            $pickedBay->warehouse->slug,
            $pickedBay->slug,
        ]);
    }
}
