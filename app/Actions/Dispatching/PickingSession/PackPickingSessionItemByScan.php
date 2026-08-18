<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;
use App\Actions\Dispatching\PickingSession\Json\FetchPickingSessionItemRow;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Packs the item matching a scanned code in one of the delivery notes of a picking session, and
 * answers with the refreshed table row so the packing screen can be updated in place without an
 * Inertia round trip.
 *
 * @phpstan-type ScanOutcome array{status: string, message: string, scanned: string, item: array|null, delivery_note: array|null, row: array|null, delivery_note_state: string, picking_session_state: string, remaining_to_pack: int}
 */
class PackPickingSessionItemByScan extends OrgAction
{
    use WithScannedDeliveryNoteItemMatching;

    protected User $user;

    /**
     * The packing screen of a picking session is open to whoever works the session, so the only
     * extra gate is the organisation opting into scanning. Without it the endpoint would stay live
     * for organisations that never enabled the feature.
     */
    public function authorize(ActionRequest $request): bool
    {
        return (bool)data_get($this->organisation->settings, 'orders.allow_scan_to_pack', false);
    }

    /**
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession, User $user, array $modelData): array
    {
        $scanned           = trim((string)data_get($modelData, 'barcode'));
        $tab               = data_get($modelData, 'tab');
        $requestedItemId   = data_get($modelData, 'delivery_note_item_id');
        $requestedQuantity = data_get($modelData, 'quantity');
        $requestedQuantity = $requestedQuantity === null ? null : (float)$requestedQuantity;

        if ($pickingSession->state != PickingSessionStateEnum::PICKING_FINISHED) {
            return $this->outcome(
                $pickingSession,
                'wrong_state',
                __('This picking session is not in packing state'),
                $scanned
            );
        }

        $sessionItems = $this->loadDeliveryNoteItems($pickingSession);
        $matchedItems = $this->matchItems($sessionItems, $scanned);

        // The 'pack the rest' button targets the exact item that was just scanned, so a session
        // carrying the same org stock in several delivery notes cannot have the follow up land on
        // the wrong one.
        if ($requestedItemId) {
            $matchedItems = $matchedItems->where('id', (int)$requestedItemId)->values();
        }

        if ($matchedItems->isEmpty()) {
            return $this->outcome(
                $pickingSession,
                'not_found',
                __(':scanned does not belong to this picking session', ['scanned' => $scanned]),
                $scanned,
                knownItems: $sessionItems
            );
        }

        $itemToPack = $this->pickItemToPack($matchedItems, $sessionItems);

        if (!$itemToPack) {
            return $this->outcomeForNothingPacked($pickingSession, $matchedItems, $scanned, $sessionItems);
        }

        $packedBefore = (float)$itemToPack->packings()->sum('quantity');

        // 'Pack all' passes null so the remainder is resolved inside the action's lock. Reading it
        // out here would let a second packer's scan land in between and turn this into an error
        // instead of simply packing whatever is genuinely left.
        $quantityWanted = $this->scannedQuantityInStockUnits($itemToPack, $scanned, $requestedQuantity);

        $quantityToPack = $quantityWanted === null
            ? null
            : min($quantityWanted, UpdateDeliveryNoteItemPacking::quantityLeftToPack($itemToPack));

        UpdateDeliveryNoteItemPacking::make()->action($itemToPack, $user, $quantityToPack);

        $itemToPack->refresh();
        $pickingSession->refresh();

        $quantityPacked = round((float)$itemToPack->packings()->sum('quantity') - $packedBefore, 3);
        $remainingAfter = UpdateDeliveryNoteItemPacking::quantityLeftToPack($itemToPack);

        $message = $remainingAfter > 0
            ? __('Packed :quantity x :code for :reference, :remaining still to pack', [
                'quantity'  => $this->formatScanQuantity($itemToPack, $quantityPacked),
                'code'      => $itemToPack->orgStock?->code ?? $scanned,
                'reference' => $itemToPack->deliveryNote->reference,
                'remaining' => $this->formatScanQuantity($itemToPack, $remainingAfter),
            ])
            : __('Packed :quantity x :code for :reference', [
                'quantity'  => $this->formatScanQuantity($itemToPack, $quantityPacked),
                'code'      => $itemToPack->orgStock?->code ?? $scanned,
                'reference' => $itemToPack->deliveryNote->reference,
            ]);

        return $this->outcome($pickingSession, 'packed', $message, $scanned, $itemToPack, $tab);
    }

    /**
     * A packer fills one box at a time, so a code present in several delivery notes goes to the
     * note that is already half packed before any untouched one; delivery note id keeps the choice
     * stable once nothing is started yet.
     *
     * @param  Collection<int, DeliveryNoteItem>  $matchedItems
     * @param  Collection<int, DeliveryNoteItem>  $sessionItems
     */
    protected function pickItemToPack(Collection $matchedItems, Collection $sessionItems): ?DeliveryNoteItem
    {
        $startedDeliveryNoteIds = $sessionItems
            ->filter(fn (DeliveryNoteItem $item) => $item->packings->isNotEmpty())
            ->pluck('delivery_note_id')
            ->unique()
            ->all();

        return $matchedItems
            ->filter(fn (DeliveryNoteItem $item) => $this->canBePackedNow($item, $sessionItems))
            ->sortBy('delivery_note_id')
            ->sortBy(fn (DeliveryNoteItem $item) => in_array($item->delivery_note_id, $startedDeliveryNoteIds) ? 0 : 1)
            ->first();
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>  $sessionItems
     */
    protected function canBePackedNow(DeliveryNoteItem $deliveryNoteItem, Collection $sessionItems): bool
    {
        return $this->isDeliveryNoteBeingPacked($deliveryNoteItem->deliveryNote)
            && !$this->hasWaitingItems($deliveryNoteItem->deliveryNote, $sessionItems)
            && (float)$deliveryNoteItem->quantity_picked != 0
            && UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem) > 0;
    }

    protected function isDeliveryNoteBeingPacked(DeliveryNote $deliveryNote): bool
    {
        return in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::HANDLING,
            DeliveryNoteStateEnum::PICKED,
            DeliveryNoteStateEnum::PACKING,
        ]);
    }

    protected function isDeliveryNotePacked(DeliveryNote $deliveryNote): bool
    {
        return in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::PACKED,
            DeliveryNoteStateEnum::FINALISED,
            DeliveryNoteStateEnum::DISPATCHED,
        ]);
    }

    /**
     * Packing every scannable item of a note that still waits for stock would send the whole note to
     * packed, which is exactly what the 'Set as packed' button refuses to do while items wait.
     *
     * @param  Collection<int, DeliveryNoteItem>  $sessionItems
     */
    protected function hasWaitingItems(DeliveryNote $deliveryNote, Collection $sessionItems): bool
    {
        return $sessionItems
            ->where('delivery_note_id', $deliveryNote->id)
            ->contains(fn (DeliveryNoteItem $item) => $item->has_waiting_warehouse || $item->has_waiting_crm);
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>  $matchedItems
     * @param  Collection<int, DeliveryNoteItem>  $sessionItems
     *
     * @return array
     */
    protected function outcomeForNothingPacked(
        PickingSession $pickingSession,
        Collection $matchedItems,
        string $scanned,
        Collection $sessionItems
    ): array {
        $packedItem = $matchedItems->first(
            fn (DeliveryNoteItem $item) => $item->packings->isNotEmpty()
                || $this->isDeliveryNotePacked($item->deliveryNote)
        );

        if ($packedItem) {
            return $this->outcome(
                $pickingSession,
                'already_packed',
                __(':code is already packed for :reference', [
                    'code'      => $packedItem->orgStock?->code ?? $scanned,
                    'reference' => $packedItem->deliveryNote->reference,
                ]),
                $scanned,
                $packedItem,
                knownItems: $sessionItems
            );
        }

        $waitingItem = $matchedItems->first(
            fn (DeliveryNoteItem $item) => $this->hasWaitingItems($item->deliveryNote, $sessionItems)
        );

        if ($waitingItem) {
            return $this->outcome(
                $pickingSession,
                'nothing_to_pack',
                __(':reference is waiting for stock, pack it from the delivery note', [
                    'reference' => $waitingItem->deliveryNote->reference,
                ]),
                $scanned,
                $waitingItem,
                knownItems: $sessionItems
            );
        }

        $blockedItem = $matchedItems->first(
            fn (DeliveryNoteItem $item) => !$this->isDeliveryNoteBeingPacked($item->deliveryNote)
        );

        if ($blockedItem) {
            return $this->outcome(
                $pickingSession,
                'nothing_to_pack',
                __(':reference can not be packed, it is :state', [
                    'reference' => $blockedItem->deliveryNote->reference,
                    'state'     => DeliveryNoteStateEnum::labels()[$blockedItem->deliveryNote->state->value],
                ]),
                $scanned,
                $blockedItem,
                knownItems: $sessionItems
            );
        }

        return $this->outcome(
            $pickingSession,
            'nothing_to_pack',
            __('Nothing picked to pack for :code', ['code' => $matchedItems->first()->orgStock?->code ?? $scanned]),
            $scanned,
            $matchedItems->first(),
            knownItems: $sessionItems
        );
    }

    /**
     * Every item of the session is read in one go, together with the delivery note it belongs to, so
     * deciding where a scan lands costs no extra query.
     *
     * @return Collection<int, DeliveryNoteItem>
     */
    protected function loadDeliveryNoteItems(PickingSession $pickingSession): Collection
    {
        return $pickingSession->deliveryNotesItems()
            ->with(['orgStock', 'packings', 'deliveryNote', 'shop'])
            ->get();
    }

    /**
     * $knownItems lets an outcome that wrote nothing reuse the collection already loaded by handle().
     * A missed scan is common on a packing bench and should not cost a second full load; the 'packed'
     * outcome passes null because its counts have to be read back after the write.
     *
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function outcome(
        PickingSession $pickingSession,
        string $status,
        string $message,
        string $scanned,
        ?DeliveryNoteItem $deliveryNoteItem = null,
        ?string $tab = null,
        ?Collection $knownItems = null
    ): array {
        $deliveryNote = $deliveryNoteItem?->deliveryNote;
        $row          = null;
        $warning      = null;

        $quantityToPack = $deliveryNoteItem
            ? UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem)
            : 0;

        if ($deliveryNoteItem && $status === 'packed') {
            $rowId = $tab == PickingSessionTabsEnum::GROUPED->value
                ? $deliveryNote->id
                : $deliveryNoteItem->id;

            $row = FetchPickingSessionItemRow::run($pickingSession, $tab, $rowId)?->toArray(request());

            $warning = $this->scanKindWarning($deliveryNoteItem, $this->matchedKind($deliveryNoteItem, $scanned));
        }

        return [
            'status'                => $status,
            'message'               => $message,
            'warning'               => $warning,
            'scanned'               => $scanned,
            'item'                  => $deliveryNoteItem ? [
                'id'                          => $deliveryNoteItem->id,
                'code'                        => $deliveryNoteItem->orgStock?->code,
                'name'                        => $deliveryNoteItem->orgStock?->name,
                'packed_in'                   => (int)($deliveryNoteItem->orgStock?->packed_in ?? 1),
                'quantity_picked'             => (float)$deliveryNoteItem->quantity_picked,
                'quantity_packed'             => (float)$deliveryNoteItem->quantity_packed,
                'quantity_to_pack'            => $quantityToPack,
                'quantity_to_pack_label'      => $this->formatScanQuantity($deliveryNoteItem, $quantityToPack),
                'quantity_to_pack_fractional' => $this->scanQuantityFraction($deliveryNoteItem, $quantityToPack),
            ] : null,
            'delivery_note'         => $deliveryNote ? [
                'id'        => $deliveryNote->id,
                'reference' => $deliveryNote->reference,
                'state'     => $deliveryNote->state->value,
            ] : null,
            'row'                   => $row,
            'delivery_note_state'   => $deliveryNote?->state->value ?? '',
            'picking_session_state' => $pickingSession->state->value,
            'remaining_to_pack'     => $this->countRemainingToPack($pickingSession, $knownItems),
        ];
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function countRemainingToPack(PickingSession $pickingSession, ?Collection $knownItems = null): int
    {
        $items = $knownItems ?? $this->loadDeliveryNoteItems($pickingSession);

        return $items
            ->filter(
                fn (DeliveryNoteItem $item) => $this->isDeliveryNoteBeingPacked($item->deliveryNote)
                    && empty((float)$item->quantity_not_picked)
                    && !UpdateDeliveryNoteItemPacking::isFullyPacked($item)
            )
            ->count();
    }

    public function rules(): array
    {
        return [
            'barcode'               => ['required', 'string', 'max:255'],
            'tab'                   => ['sometimes', 'nullable', 'string'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): array
    {
        $this->user = $request->user();

        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $request->user(), $this->validatedData);
    }
}
