<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\PackDeliveryNoteItemByScan;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PackMobileDeliveryNoteItemByScan
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobilePackingMessages;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        abort_unless($this->pickingSettings($deliveryNote->organisation)['allow_scan_to_pack'], 403, __('Scan to pack is not enabled for this organisation.'));
        abort_if($this->isDropshipping($deliveryNote), 403, __('Dropshipping delivery notes are not packed by scanning.'));
        $this->ensureCanHandle($deliveryNote);

        Validator::make($request->all(), [
            'barcode'               => ['required', 'string', 'max:255'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $packedBefore = $deliveryNote->deliveryNoteItems()->get()->mapWithKeys(fn (DeliveryNoteItem $deliveryNoteItem) => [$deliveryNoteItem->id => $this->packedQuantity($deliveryNoteItem)]);

        $outcome = PackDeliveryNoteItemByScan::make()->asController($deliveryNote, $request);

        $status  = $outcome['status'];
        $message = $outcome['message'];

        if ($status === 'packed' && $outcome['item']) {
            $deliveryNoteItem   = DeliveryNoteItem::with('orgStock')->findOrFail($outcome['item']['id']);
            [$status, $message] = $this->packedMessage($deliveryNoteItem, $this->packedQuantity($deliveryNoteItem) - (float) ($packedBefore[$deliveryNoteItem->id] ?? 0), $outcome['scanned']);
        }

        return $this->respondWith($deliveryNote, $user, $status, $message, ['item' => $outcome['item']]);
    }
}
