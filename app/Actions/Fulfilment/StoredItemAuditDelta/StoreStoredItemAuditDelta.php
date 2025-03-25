<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 18:06:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItemAuditDelta;

use App\Actions\Fulfilment\StoredItemAudit\Hydrators\StoredItemAuditHydrateDeltas;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaTypeEnum;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreStoredItemAuditDelta extends OrgAction
{
    use WithActionUpdate;

    public function handle(StoredItemAudit $storedItemAudit, array $modelData)
    {
        data_set($modelData, 'group_id', $storedItemAudit->group_id);
        data_set($modelData, 'organisation_id', $storedItemAudit->organisation_id);
        data_set($modelData, 'audited_at', now());

        $existingAuditDelta = $storedItemAudit->deltas()->where(
            [
                'pallet_id' => Arr::get($modelData, 'pallet_id'),
                'stored_item_id' => Arr::get($modelData, 'stored_item_id'),
            ]
        )->first();

        if ($existingAuditDelta) {
            return $storedItemAudit;
        }

        $isNewStoredItem = false;
        $storedItem      = StoredItem::where('id', $modelData['stored_item_id'])->first();


        $originalQuantity = DB::table('pallet_stored_items')
            ->where('stored_item_id', $storedItem->id)
            ->where('pallet_id', $modelData['pallet_id'])
            ->get()->pluck('quantity')->first();

        if ($originalQuantity == null) {
            $originalQuantity = 0;
            $auditType        = StoredItemAuditDeltaTypeEnum::SET_UP;
        } elseif ($originalQuantity > $modelData['audited_quantity']) {
            $auditType = StoredItemAuditDeltaTypeEnum::SUBTRACTION;
        } elseif ($originalQuantity < $modelData['audited_quantity']) {
            $auditType = StoredItemAuditDeltaTypeEnum::ADDITION;
        } else {
            $auditType = StoredItemAuditDeltaTypeEnum::NO_CHANGE;
        }


        if (in_array($storedItem->state, [StoredItemStateEnum::SUBMITTED, StoredItemStateEnum::IN_PROCESS])) {
            $isNewStoredItem = true;
        }


        data_set($modelData, 'audit_type', $auditType);
        data_set($modelData, 'state', StoredItemAuditDeltaStateEnum::IN_PROCESS);
        data_set($modelData, 'original_quantity', $originalQuantity);

        data_set($modelData, 'is_new_stored_item', $isNewStoredItem);
        data_set($modelData, 'is_stored_item_new_in_pallet', true);


        $storedItemAuditDelta = $storedItemAudit->deltas()->create($modelData);

        StoredItemAuditHydrateDeltas::run($storedItemAudit);


        return $storedItemAuditDelta;
    }

    public function rules(): array
    {
        return [
            'pallet_id'        => ['required', 'integer', 'exists:pallets,id'],
            'stored_item_id'   => ['sometimes','required', 'integer', 'exists:stored_items,id'],
            'stored_item_slug' => ['sometimes'],
            'audited_quantity' => ['required', 'integer', 'min:0'],
            'user_id'          => ['sometimes', 'required', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)],

        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction) {
            $this->set('user_id', $request->user()->id);
        }

        if ($this->has('stored_item_slug')) {

            $storedItem = StoredItem::where('slug', $this->get('stored_item_slug'))->first();
            $this->set('stored_item_id', $storedItem->id);
        }
    }

    public function asController(StoredItemAudit $storedItemAudit, ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($storedItemAudit->fulfilment, $request);

        $this->handle($storedItemAudit, $this->validatedData);
    }

    public function action(StoredItemAudit $storedItemAudit, $modelData): StoredItemAuditDelta
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($storedItemAudit->fulfilment, $modelData);

        return $this->handle($storedItemAudit, $this->validatedData);
    }


}
