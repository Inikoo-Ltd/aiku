<?php

/*
 * Author: Kirin
 * Created: Thu, 22 May 2025 13:45 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreNotPickPicking extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;


    public function handle(DeliveryNoteItem $deliveryNoteItem, ?User $user, array $modelData): ?Picking
    {
        if ($user) {
            data_set($modelData, 'picker_user_id', $user->id);
        }

        // If locked, will skip the process
        if ($deliveryNoteItem->locked_at && (Carbon::parse($deliveryNoteItem->locked_at)->diffInSeconds(now()) < 3)) {
            return null;
        }

        $deliveryNoteItem->update(['locked_at' => now()]);

        try {
            data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
            data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
            data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
            data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
            data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id);
            data_set($modelData, 'engine', PickingEngineEnum::AIKU);
            data_set($modelData, 'type', PickingTypeEnum::NOT_PICK);

            /** @var Picking $picking */
            $picking = $deliveryNoteItem->pickings()->create($modelData);
            $picking->refresh();

            CalculateDeliveryNoteItemTotalPicked::make()->action($picking->deliveryNoteItem);

            return $picking;
        } catch (Exception) {
            $deliveryNoteItem->update(['locked_at' => null]);

            return null;
        }
    }

    public function rules(): array
    {
        return [
            'not_picked_reason' => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'   => ['sometimes', 'string'],
            'quantity'          => ['sometimes', 'numeric'],
        ];
    }

    /**
     * What the requirement still leaves open once everything already accounted for is taken out:
     * picked, previously marked as not picked, and the two waiting buckets. This is the same
     * amount the picking screen prints on the button, so pressing it twice cannot claim the
     * quantity twice the way subtracting only the picks did.
     */
    private function outstandingQuantity(DeliveryNoteItem $deliveryNoteItem): float
    {
        $outstanding = (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_not_picked
            - (float)$deliveryNoteItem->quantity_waiting_warehouse
            - (float)$deliveryNoteItem->quantity_waiting_crm;

        return $outstanding > 0 ? $outstanding : 0.0;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->has('quantity')) {
            $this->set('quantity', $this->outstandingQuantity($this->deliveryNoteItem));
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): ?Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        /*
         * An item with nothing required at all is ignored by recording a picking of zero, which is
         * what marks it as handled, so the first press still goes through. Once one exists there is
         * nothing further to record and every later press is a repeat of the same button.
         */
        if ($this->outstandingQuantity($deliveryNoteItem) <= 0
            && $deliveryNoteItem->pickings()->where('type', PickingTypeEnum::NOT_PICK)->exists()) {
            return null;
        }

        return $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, ?User $user, array $modelData): ?Picking
    {
        $this->asAction         = true;
        $this->deliveryNoteItem = $deliveryNoteItem;

        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $user, $this->validatedData);
    }


}
