<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Actions\Dispatching\PickingSession\PackPickingSessionItemByScan;
use App\Actions\Dispatching\PickingSession\UI\ShowPickingSession;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PackMobilePickingSessionItemByScan
{
    use AsController;
    use WithMobilePickingSessions;
    use WithMobilePackingMessages;

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPickingSession($pickingSession, $user);
        $this->ensurePickingSessionState($pickingSession, [PickingSessionStateEnum::PICKING_FINISHED]);
        abort_unless($this->pickingSettings($pickingSession->organisation)['allow_scan_to_pack'], 403, __('Scan to pack is not enabled for this organisation.'));

        if (!ShowPickingSession::offersScanToPack($pickingSession)) {
            $this->refuseState(__('Nothing in this picking session is packed by scanning.'));
        }

        $data = Validator::make($request->all(), [
            'barcode'               => ['required', 'string', 'max:255'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $packedBefore = $pickingSession->deliveryNotesItems()->get()
            ->mapWithKeys(fn (DeliveryNoteItem $deliveryNoteItem) => [$deliveryNoteItem->id => $this->packedQuantity($deliveryNoteItem)]);

        $outcome = PackPickingSessionItemByScan::make()->handle(
            $pickingSession,
            $user,
            Arr::only($data, ['barcode', 'quantity', 'delivery_note_item_id'])
        );

        $status  = $outcome['status'];
        $message = $outcome['message'];

        if ($status === 'packed' && $outcome['item']) {
            $deliveryNoteItem   = DeliveryNoteItem::with(['orgStock', 'deliveryNote'])->findOrFail($outcome['item']['id']);
            [$status, $message] = $this->sessionPackedMessage(
                $deliveryNoteItem,
                $this->packedQuantity($deliveryNoteItem) - (float) ($packedBefore[$deliveryNoteItem->id] ?? 0),
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
    private function sessionPackedMessage(DeliveryNoteItem $deliveryNoteItem, float $packed, string $scanned): array
    {
        $code = $deliveryNoteItem->orgStock?->code ?? $scanned;

        if ($packed <= 0.000001) {
            return ['nothing_changed', __('Nothing was packed for :code.', ['code' => $code])];
        }

        $remaining  = UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem);
        $parameters = [
            'quantity'  => $this->formatScanQuantity($deliveryNoteItem, $packed),
            'code'      => $code,
            'reference' => $deliveryNoteItem->deliveryNote->reference,
        ];

        $message = $remaining > 0
            ? __('Packed :quantity x :code for :reference, :remaining still to pack', [...$parameters, 'remaining' => $this->formatScanQuantity($deliveryNoteItem, $remaining)])
            : __('Packed :quantity x :code for :reference', $parameters);

        $notPackedReason = $this->packedButNotPromotedReason($deliveryNoteItem->deliveryNote);

        return ['packed', $notPackedReason ? $message.'. '.$notPackedReason : $message];
    }
}
