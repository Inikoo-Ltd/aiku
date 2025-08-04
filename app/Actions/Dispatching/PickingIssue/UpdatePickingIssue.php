<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingIssue;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\PickingIssue;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePickingIssue extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    /**
     * @throws \Throwable
     */
    public function handle(PickingIssue $pickingIssue, array $modelData): void
    {
        $this->update($pickingIssue, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'location_id' => ['sometimes', 
                Rule::Exists('locations', 'id')->where('warehouse_id', $this->warehouse->id)],
            'picking_id' => ['sometimes', 'nullable'],
            'delivery_note_issue' => ['sometimes', 'string'],
            'delivery_note_item_issue' => ['sometimes', 'string'],
            'issuer_user_id'        => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],
            'resolver_user_id'        => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],
            'is_solved' => ['sometimes', 'boolean']
        ];

        return $rules;
    }
    /**
     * @throws \Throwable
     */
    public function asController(PickingIssue $pickingIssue, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($pickingIssue->warehouse, $request);

        $this->handle($pickingIssue, $this->validatedData);
    }
}
