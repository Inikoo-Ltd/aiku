<?php

namespace App\Actions\Inventory\PickedBay;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePickedBay extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    private PickedBay $pickedBay;

    public function handle(PickedBay $pickedBay, array $modelData): PickedBay
    {
        return $this->update($pickedBay, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'sometimes',
                'required',
                'max:64',
                $this->strict ? 'alpha_dash' : 'string',
                new IUnique(
                    table: 'picking_trolleys',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->pickedBay->warehouse_id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->pickedBay->id],
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Warehouse $warehouse, PickedBay $pickedBay, array $modelData, bool $strict = true): PickedBay
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->pickedBay = $pickedBay;
        $this->initialisation($warehouse->organisation, $modelData);

        return $this->handle($pickedBay, $this->validatedData);
    }

    public function asController(Warehouse $warehouse, PickedBay $pickedBay, ActionRequest $request): PickedBay
    {
        $this->pickedBay = $pickedBay;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($pickedBay, $this->validatedData);
    }
}
