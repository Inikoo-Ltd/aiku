<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingIssue;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePickingIssue extends OrgAction
{
    use AsAction;
    use WithAttributes;
    protected User $user;
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote|DeliveryNoteItem $parent, array $modelData): void
    {
        $issueRef = 'ISSUE-' . ($parent instanceof DeliveryNote ? 'DN' : 'DNI') . '-' . $parent->id;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'reference', $issueRef);

        if ($parent instanceof DeliveryNote) {
            data_set($modelData, 'warehouse_id', $parent->warehouse_id);
        } elseif ($parent instanceof DeliveryNoteItem) {
            data_set($modelData, 'warehouse_id', $parent->deliveryNote->warehouse_id);
            data_set($modelData, 'org_stock_id', $parent->org_stock_id);
        }

        $parent->pickingIssues()->create($modelData);
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
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],
            'resolver_user_id'        => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],

        ];

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction && !$request->has('issuer_user_id')) {
            $this->set('issuer_user_id', $this->user->id);
        }
    }

    /**
     * @throws \Throwable
     */
    public function inDeliveryNote(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->user             = $request->user();
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        $this->handle($deliveryNote, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function inDeliveryNoteItem(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->user             = $request->user();
        $this->initialisationFromWarehouse($deliveryNoteItem->deliveryNote->warehouse, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
