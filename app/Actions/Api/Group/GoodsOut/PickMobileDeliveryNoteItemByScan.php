<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\PickDeliveryNoteItemByScan;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PickMobileDeliveryNoteItemByScan
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        abort_unless($this->pickingSettings($deliveryNote->organisation)['allow_scan_to_pick'], 403, __('Scan to pick is not enabled for this organisation.'));
        abort_if($this->isDropshipping($deliveryNote), 403, __('Dropshipping delivery notes are not picked by scanning.'));
        $this->ensureCanHandle($deliveryNote);

        Validator::make($request->all(), [
            'barcode'               => ['required', 'string', 'max:255'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $pickedBefore = $deliveryNote->deliveryNoteItems()->pluck('quantity_picked', 'id');

        $outcome = PickDeliveryNoteItemByScan::make()->asController($deliveryNote, $request);

        $status  = $outcome['status'];
        $message = $outcome['message'];

        if ($status === 'picked' && $outcome['item']) {
            $deliveryNoteItem = DeliveryNoteItem::with('orgStock')->findOrFail($outcome['item']['id']);
            $picked           = (float) $deliveryNoteItem->quantity_picked - (float) ($pickedBefore[$deliveryNoteItem->id] ?? 0);
            [$status, $message] = $this->pickedMessage($deliveryNoteItem, $picked, $outcome['item']['location_code'] ?? null, $outcome['scanned']);
        }

        return $this->respondWith($deliveryNote, $user, $status, $message, ['item' => $outcome['item']]);
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

        $remaining = PickDeliveryNoteItemByScan::quantityLeftToPick($deliveryNoteItem);

        return ['picked', $remaining > 0
            ? __('Picked :quantity x :code from :location, :remaining still to pick', [
                'quantity'  => $this->formatScanQuantity($deliveryNoteItem, $picked),
                'code'      => $code,
                'location'  => $locationCode,
                'remaining' => $this->formatScanQuantity($deliveryNoteItem, $remaining),
            ])
            : __('Picked :quantity x :code from :location', [
                'quantity' => $this->formatScanQuantity($deliveryNoteItem, $picked),
                'code'     => $code,
                'location' => $locationCode,
            ])];
    }
}
