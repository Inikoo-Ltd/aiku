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
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        data_set($modelData, 'picker_user_id', $user->id);

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

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->has('quantity')) {
            $this->set('quantity', $this->deliveryNoteItem->quantity_required - $this->deliveryNoteItem->quantity_picked);
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): ?Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;

        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $user, $this->validatedData);
    }


}
