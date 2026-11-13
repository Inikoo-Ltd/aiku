<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\PickPickingSessionItemByScan;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PickMobilePickingSessionItemByScan
{
    use AsController;
    use WithMobilePickingSessions;

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPickingSession($pickingSession, $user);
        $this->ensurePickingSessionState($pickingSession, [PickingSessionStateEnum::HANDLING]);
        abort_unless($this->pickingSettings($pickingSession->organisation)['allow_scan_to_pick'], 403, __('Scan to pick is not enabled for this organisation.'));

        $data = Validator::make($request->all(), [
            'barcode'               => ['required', 'string', 'max:255'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $pickedBefore = $pickingSession->deliveryNotesItems()->pluck('quantity_picked', 'id');

        $outcome = PickPickingSessionItemByScan::make()->handle(
            $pickingSession,
            $user,
            Arr::only($data, ['barcode', 'quantity', 'delivery_note_item_id', 'location_org_stock_id'])
        );

        $status  = $outcome['status'];
        $message = $outcome['message'];

        if ($status === 'picked' && $outcome['item']) {
            $deliveryNoteItem   = DeliveryNoteItem::with(['orgStock', 'deliveryNote'])->findOrFail($outcome['item']['id']);
            [$status, $message] = $this->pickedMessage(
                $deliveryNoteItem,
                (float) $deliveryNoteItem->quantity_picked - (float) ($pickedBefore[$deliveryNoteItem->id] ?? 0),
                $outcome['item']['location_code'] ?? null,
                $outcome['scanned']
            );
        }

        return $this->respondWithPickingSession($pickingSession, $user, $status, $message, [
            'item'          => $outcome['item'],
            'delivery_note' => $outcome['delivery_note'],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function pickedMessage(DeliveryNoteItem $deliveryNoteItem, float $picked, ?string $locationCode, string $scanned): array
    {
        $code = $deliveryNoteItem->orgStock?->code ?? $scanned;

        if ($picked <= 0.000001) {
            return ['nothing_changed', __('Nothing was picked for :code.', ['code' => $code])];
        }

        $remaining  = PickPickingSessionItemByScan::quantityLeftToPick($deliveryNoteItem);
        $parameters = [
            'quantity'  => $this->formatScanQuantity($deliveryNoteItem, $picked),
            'code'      => $code,
            'location'  => $locationCode,
            'reference' => $deliveryNoteItem->deliveryNote->reference,
        ];

        return ['picked', $remaining > 0
            ? __('Picked :quantity x :code from :location for :reference, :remaining still to pick', [...$parameters, 'remaining' => $this->formatScanQuantity($deliveryNoteItem, $remaining)])
            : __('Picked :quantity x :code from :location for :reference', $parameters)];
    }
}
