<?php

namespace App\Actions\Inventory\PickingTrolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Inventory\PickingTrolley;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePickingTrolley extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    private PickingTrolley $pickingTrolley;

    public function handle(PickingTrolley $pickingTrolley, array $modelData): PickingTrolley
    {
        return $this->update($pickingTrolley, $modelData);
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
                        ['column' => 'warehouse_id', 'value' => $this->pickingTrolley->warehouse_id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->pickingTrolley->id],
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(PickingTrolley $pickingTrolley, array $modelData, bool $strict = true): PickingTrolley
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->pickingTrolley = $pickingTrolley;
        $this->initialisation($pickingTrolley->organisation, $modelData);

        return $this->handle($pickingTrolley, $this->validatedData);
    }

    public function asController(PickingTrolley $pickingTrolley, ActionRequest $request): PickingTrolley
    {
        $this->pickingTrolley = $pickingTrolley;
        $this->initialisationFromWarehouse($pickingTrolley->warehouse, $request);

        return $this->handle($pickingTrolley, $this->validatedData);
    }
}
