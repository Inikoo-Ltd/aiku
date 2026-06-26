<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\UpdateStoredItemAuditDelta;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncStoredItemToPalletAudit extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected FulfilmentCustomer $fulfilmentCustomer;
    protected Fulfilment $fulfilment;

    public function handle(Pallet $pallet, StoredItemAudit $storedItemAudit, array $modelData): void
    {
        foreach (Arr::get($modelData, 'stored_item_ids', []) as $storedItemId => $auditData) {
            $isNewInPallet   = true;
            $isNewStoredItem = false;
            $storedItemExist = $pallet->storedItems()->where(
                'stored_item_id',
                $storedItemId
            )->exists();
            $originalQty     = 0;
            if ($storedItemExist) {
                $originalQty   = $pallet->storedItems()->where('stored_item_id', $storedItemId)->count();
                $isNewInPallet = false;
            }

            //$isNewStoredItem  <-- check the stored item if state==in_processs this valuee will be true


            if ($storedItemExist) {
                if ($originalQty > $auditData['quantity']) {
                    $type = StoredItemAuditDeltaTypeEnum::SUBTRACTION;
                } elseif ($originalQty < $auditData['quantity']) {
                    $type = StoredItemAuditDeltaTypeEnum::ADDITION;
                } else {
                    $type = StoredItemAuditDeltaTypeEnum::NO_CHANGE;
                }
            } else {
                $type = StoredItemAuditDeltaTypeEnum::SET_UP;
            }

            if (in_array($type, [StoredItemAuditDeltaTypeEnum::SET_UP, StoredItemAuditDeltaTypeEnum::ADDITION], true)) {
                $storedItem = StoredItem::find($storedItemId);
                if ($storedItem && !$storedItem->state->canBeStored()) {
                    throw ValidationException::withMessages(['stored_item_ids' => __('The SKU ":reference" is :state and cannot be stored.', ['reference' => $storedItem->reference, 'state' => $storedItem->state->labelGenerated()])]);
                }
            }

            $storedItemAuditDelta = $storedItemAudit->deltas()->where('pallet_id', $pallet->id)->where('stored_item_id', $storedItemId)->first();
            if ($storedItemAuditDelta) {
                UpdateStoredItemAuditDelta::make()->action($storedItemAuditDelta, [
                    'original_quantity' => $originalQty,
                    'audited_quantity'  => $auditData['quantity'],
                    'audit_type'        => $type,
                    'state'             => StoredItemAuditDeltaStateEnum::IN_PROCESS
                ]);
            } else {
                StoreStoredItemAuditDelta::make()->action($storedItemAudit, [
                    'pallet_id'                    => $pallet->id,
                    'stored_item_id'               => $storedItemId,
                    'original_quantity'            => $originalQty,
                    'audited_quantity'             => $auditData['quantity'],
                    'audit_type'                   => $type,
                    'state'                        => StoredItemAuditDeltaStateEnum::IN_PROCESS,
                    'is_new_stored_item'           => $isNewStoredItem,
                    'is_stored_item_new_in_pallet' => $isNewInPallet
                ]);
            }
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'stored_item_ids'            => ['sometimes', 'array'],
            'stored_item_ids.*.quantity' => ['required', 'integer', 'min:1'],
            'user_id'                      => ['required', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)],

        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'stored_item_ids.*.quantity.required' => __('The quantity is required'),
            'stored_item_ids.*.quantity.integer'  => __('The quantity must be an integer'),
            'stored_item_ids.*.quantity.min'      => __('The quantity must be at least 1'),
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction) {
            $this->set('user_id', $request->user()->id);
        }
    }

    public function asController(Pallet $pallet, StoredItemAudit $storedItemAudit, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $pallet->fulfilmentCustomer;
        $this->fulfilment         = $pallet->fulfilment;

        $this->initialisation($pallet->organisation, $request);

        $this->handle($pallet, $storedItemAudit, $this->validatedData);
    }

    //Uncomment this if needed for testing
    //    public function action(Pallet $pallet, StoredItemAudit $storedItemAudit, $modelData): void
    //    {
    //        $this->asAction           = true;
    //        $this->fulfilmentCustomer = $pallet->fulfilmentCustomer;
    //        $this->fulfilment         = $pallet->fulfilment;
    //
    //        $this->initialisation($pallet->organisation, $modelData);
    //
    //        $this->handle($pallet, $storedItemAudit, $this->validatedData);
    //    }


}
