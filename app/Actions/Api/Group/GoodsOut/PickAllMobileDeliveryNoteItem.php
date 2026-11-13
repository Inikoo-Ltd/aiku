<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Picking\PickAllItem;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PickAllMobileDeliveryNoteItem
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
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $lineLocations    = app(GetMobileDeliveryNote::class)->pickingLocations($deliveryNoteItem);
        $locationOrgStock = empty($data['location_org_stock_id'])
            ? $this->firstLocationWithStock($lineLocations)
            : $this->pickableLocation($deliveryNote, $deliveryNoteItem, (int) $data['location_org_stock_id'], $lineLocations);

        if (!$locationOrgStock) {
            return $this->respondWith($deliveryNote, $user, 'nothing_changed', __('No location of this line holds stock.'));
        }

        $before = $this->lineSnapshot($deliveryNoteItem);

        PickAllItem::run($deliveryNoteItem->refresh(), [
            'location_org_stock_id' => $locationOrgStock->id,
            'picker_user_id'        => $user->id,
        ]);

        $deliveryNoteItem->refresh();

        return $this->respond(
            $deliveryNote,
            $user,
            $before,
            $this->lineSnapshot($deliveryNoteItem),
            __(':quantity picked from :location', [
                'quantity' => $this->formatScanQuantity($deliveryNoteItem, (float) $deliveryNoteItem->quantity_picked - $before['quantity_picked']),
                'location' => $locationOrgStock->location?->code,
            ]),
            __('Nothing left to pick on this line, or the location has no stock.')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $lineLocations
     */
    private function firstLocationWithStock(array $lineLocations): ?LocationOrgStock
    {
        $location = collect($lineLocations)->first(fn (array $location) => $location['stock'] > 0);

        return $location ? LocationOrgStock::find($location['location_org_stock_id']) : null;
    }
}
