<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

trait WithMobileReturnItemPuts
{
    use WithMobileReturns;

    /**
     * @throws \Throwable
     */
    protected function putFromRequest(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request, SowingTypeEnum $type): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandleReturn($user, $warehouse, $returnDeliveryNote);
        $this->ensureItemOfReturn($returnDeliveryNote, $returnDeliveryNoteItem);

        $data = Validator::make($request->all(), array_filter([
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            'location_org_stock_id' => $type === SowingTypeEnum::SOW ? ['required', 'integer', 'min:1', 'max:'.self::MAX_ID] : null,
        ]))->validate();

        [$quantity, $locationOrgStock, $packedIn] = DB::transaction(function () use ($warehouse, $returnDeliveryNote, $returnDeliveryNoteItem, $data, $type, $user) {
            $returnDeliveryNote = ReturnDeliveryNote::lockForUpdate()->findOrFail($returnDeliveryNote->id);
            $this->ensureReturnState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNING);

            $item      = ReturnDeliveryNoteItem::lockForUpdate()->findOrFail($returnDeliveryNoteItem->id);
            $remaining = $this->remainingOf($item);
            $packedIn  = (int) $item->orgStock?->packed_in;

            if ($remaining <= 0.00005) {
                throw ValidationException::withMessages(['quantity' => __('Nothing is left to handle on this item.')]);
            }

            $quantity = isset($data['quantity']) ? (float) $data['quantity'] : $remaining;
            if ($quantity - $remaining > 0.00005) {
                throw ValidationException::withMessages(['quantity' => __('Only :remaining is left to handle on this item.', ['remaining' => $this->skoLabel(round($remaining, 4), $packedIn)])]);
            }
            $quantity = min($quantity, $remaining);

            $modelData = [
                'quantity'      => $quantity,
                'type'          => $type,
                'sower_user_id' => $user->id,
            ];

            $locationOrgStock = null;
            if ($type === SowingTypeEnum::SOW) {
                $locationOrgStock                   = $this->locationLinkOfSko((int) $data['location_org_stock_id'], $item->org_stock_id, $warehouse);
                $modelData['location_org_stock_id'] = $locationOrgStock->id;
            }

            StoreSowing::make()->action($item, $user, $modelData);
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($item);

            return [$quantity, $locationOrgStock, $packedIn];
        });

        return $this->respondWithReturn($warehouse, $returnDeliveryNote, $user, 'done', $this->putMessage($type, $this->skoLabel(round($quantity, 4), $packedIn), $locationOrgStock));
    }

    private function putMessage(SowingTypeEnum $type, string $quantity, ?LocationOrgStock $locationOrgStock): string
    {
        return match ($type) {
            SowingTypeEnum::SOW     => __('Put :quantity back in :location.', ['quantity' => $quantity, 'location' => $locationOrgStock?->location?->code]),
            SowingTypeEnum::DAMAGED => __(':quantity marked as damaged; it does not go back to stock.', ['quantity' => $quantity]),
            SowingTypeEnum::NOT_SOW => __(':quantity marked as not returned.', ['quantity' => $quantity]),
        };
    }
}
