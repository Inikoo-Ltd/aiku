<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\PickDeliveryNoteItemByScan;
use App\Actions\Dispatching\Picking\UpsertPicking;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PickMobileDeliveryNoteItem
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureLineAction($deliveryNote, $user);
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $data = Validator::make($request->all(), [
            'location_org_stock_id' => ['required', 'integer'],
            'quantity'              => ['required', 'numeric', 'min:0'],
        ])->validate();

        $lineLocations    = app(GetMobileDeliveryNote::class)->pickingLocations($deliveryNoteItem);
        $locationOrgStock = $this->pickableLocation($deliveryNote, $deliveryNoteItem, (int) $data['location_org_stock_id'], $lineLocations);
        $pickingId        = $deliveryNoteItem->pickings()->where('location_id', $locationOrgStock->location_id)->orderBy('id')->value('id');
        $quantity         = (float) $data['quantity'];

        $before         = $this->lineSnapshot($deliveryNoteItem);
        $leftBeforePick = PickDeliveryNoteItemByScan::quantityLeftToPick($deliveryNoteItem->refresh());

        if ($pickingId || $quantity > 0) {
            UpsertPicking::make()->action($deliveryNoteItem, $user, array_filter([
                'location_org_stock_id' => $locationOrgStock->id,
                'quantity'              => $quantity,
                'picking_id'            => $pickingId,
                'picker_user_id'        => $user->id,
            ], fn ($value) => $value !== null));
        }

        return $this->respond(
            $deliveryNote,
            $user,
            $before,
            $this->lineSnapshot($deliveryNoteItem),
            __(':quantity picked from :location', [
                'quantity' => $this->formatScanQuantity($deliveryNoteItem, $this->pickedAt($deliveryNoteItem, $locationOrgStock)),
                'location' => $locationOrgStock->location?->code,
            ]),
            $leftBeforePick <= 0 && $quantity > 0 ? __('Nothing left to pick on this line.') : __('Nothing changed on this line.')
        );
    }

    private function pickedAt(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock): float
    {
        return (float) $deliveryNoteItem->pickings()
            ->whereIn('type', [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK])
            ->where('location_id', $locationOrgStock->location_id)
            ->sum('quantity');
    }
}
