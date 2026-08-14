<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Mon, 10 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Picks the delivery note item matching a code coming from a barcode scanner, out of the same
 * location the picking table would have preselected, and answers with the refreshed table row so
 * the picking screen can be updated in place without an Inertia round trip.
 *
 * @phpstan-type ScanOutcome array{status: string, message: string, scanned: string, item: array|null, row: array|null, delivery_note_state: string, remaining_to_pick: int, counts: array{all: int, todo: int, done: int}}
 */
class PickDeliveryNoteItemByScan extends OrgAction
{
    use WithDeliveryNoteItemUI;
    use WithScannedDeliveryNoteItemMatching;
    use WithScannedDeliveryNoteItemPicking;
    use WithDeliveryNoteItemPickingCounts;

    protected User $user;

    private DeliveryNote $deliveryNote;

    /**
     * Gated by the same two rules that decide whether the scan panel is rendered at all: the
     * organisation has opted into scanning, and this user is the one handling the delivery note.
     * Without the first check the endpoint would stay live for organisations that never enabled it.
     */
    public function authorize(ActionRequest $request): bool
    {
        if (!data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false)) {
            return false;
        }

        return $this->canHandleDeliveryNote($this->deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, User $user, array $modelData): array
    {
        $scanned            = trim((string)data_get($modelData, 'barcode'));
        $tab                = data_get($modelData, 'tab');
        $requestedItemId    = data_get($modelData, 'delivery_note_item_id');
        $requestedLocation  = data_get($modelData, 'location_org_stock_id');
        $requestedQuantity  = data_get($modelData, 'quantity');
        $requestedQuantity  = $requestedQuantity === null ? null : (float)$requestedQuantity;

        if ($deliveryNote->state != DeliveryNoteStateEnum::HANDLING) {
            return $this->outcome(
                $deliveryNote,
                'wrong_state',
                __('This delivery note is not being picked'),
                $scanned
            );
        }

        $deliveryNoteItems = $deliveryNote->deliveryNoteItems()->with(['orgStock', 'shop', 'deliveryNote'])->get();
        $matchedItems      = $this->matchItems($deliveryNoteItems, $scanned);

        // The 'pick the rest' button targets the exact item that was just scanned, so a delivery note
        // carrying the same org stock in two lines cannot have the follow up land on the wrong one.
        if ($requestedItemId) {
            $matchedItems = $matchedItems->where('id', (int)$requestedItemId)->values();
        }

        if ($matchedItems->isEmpty()) {
            return $this->outcome(
                $deliveryNote,
                'not_found',
                __(':scanned does not belong to this delivery note', ['scanned' => $scanned]),
                $scanned,
                knownItems: $deliveryNoteItems
            );
        }

        $itemToPick = $matchedItems->first(fn (DeliveryNoteItem $item) => static::quantityLeftToPick($item) > 0);

        if (!$itemToPick) {
            $item = $matchedItems->first();

            return $this->outcome(
                $deliveryNote,
                'already_picked',
                __(':code has nothing left to pick', ['code' => $item->orgStock?->code ?? $scanned]),
                $scanned,
                $item,
                knownItems: $deliveryNoteItems
            );
        }

        $location       = $this->pickingLocation($itemToPick, $requestedLocation);
        $quantityWanted = $this->scannedQuantityInStockUnits($itemToPick, $scanned, $requestedQuantity);
        $quantityToPick = $location ? $this->storeScannedPicking($itemToPick, $user, $location, $quantityWanted) : 0;

        if ($quantityToPick <= 0) {
            return $this->outcome(
                $deliveryNote,
                'no_stock',
                __('No picking location holds stock for :code', ['code' => $itemToPick->orgStock?->code ?? $scanned]),
                $scanned,
                $itemToPick,
                location: $location,
                knownItems: $deliveryNoteItems
            );
        }

        $itemToPick->refresh();
        $deliveryNote->refresh();

        $remainingAfter = static::quantityLeftToPick($itemToPick);

        $message = $remainingAfter > 0
            ? __('Picked :quantity x :code from :location, :remaining still to pick', [
                'quantity'  => $this->formatScanQuantity($itemToPick, $quantityToPick),
                'code'      => $itemToPick->orgStock?->code ?? $scanned,
                'location'  => $location->location_code,
                'remaining' => $this->formatScanQuantity($itemToPick, $remainingAfter),
            ])
            : __('Picked :quantity x :code from :location', [
                'quantity' => $this->formatScanQuantity($itemToPick, $quantityToPick),
                'code'     => $itemToPick->orgStock?->code ?? $scanned,
                'location' => $location->location_code,
            ]);

        return $this->outcome($deliveryNote, 'picked', $message, $scanned, $itemToPick, $tab, location: $location);
    }

    /**
     * $knownItems lets an outcome that wrote nothing reuse the collection already loaded by handle().
     * A missed scan is common on a picking round and should not cost a second full load; the 'picked'
     * outcome passes null because its counts have to be read back after the write.
     *
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function outcome(
        DeliveryNote $deliveryNote,
        string $status,
        string $message,
        string $scanned,
        ?DeliveryNoteItem $deliveryNoteItem = null,
        ?string $tab = null,
        ?Collection $knownItems = null,
        ?object $location = null
    ): array {
        $row     = null;
        $warning = null;

        if ($deliveryNoteItem && $status === 'picked') {
            $row = FetchDeliveryNoteItemRow::run($deliveryNoteItem, $tab);
            $row = $row?->toArray(request());

            $warning = $this->scanKindWarning($deliveryNoteItem, $this->matchedKind($deliveryNoteItem, $scanned));
        }

        return [
            'status'              => $status,
            'message'             => $message,
            'warning'             => $warning,
            'scanned'             => $scanned,
            'item'                => $deliveryNoteItem ? [
                'id'                    => $deliveryNoteItem->id,
                'code'                  => $deliveryNoteItem->orgStock?->code,
                'name'                  => $deliveryNoteItem->orgStock?->name,
                'packed_in'             => (int)($deliveryNoteItem->orgStock?->packed_in ?? 1),
                'quantity_required'     => (float)$deliveryNoteItem->quantity_required,
                'quantity_picked'       => (float)$deliveryNoteItem->quantity_picked,
                'quantity_to_pick'      => static::quantityLeftToPick($deliveryNoteItem),
                'quantity_to_pick_label' => $this->formatScanQuantity($deliveryNoteItem, static::quantityLeftToPick($deliveryNoteItem)),
                'quantity_to_pick_fractional' => $this->scanQuantityFraction($deliveryNoteItem, static::quantityLeftToPick($deliveryNoteItem)),
                'location_code'         => $location?->location_code,
                'location_org_stock_id' => $location?->id,
            ] : null,
            'row'                 => $row,
            'delivery_note_state' => $deliveryNote->state->value,
            'remaining_to_pick'   => $this->countRemainingToPick($deliveryNote, $knownItems),
            'counts'              => static::pickingCounts($deliveryNote),
        ];
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function countRemainingToPick(DeliveryNote $deliveryNote, ?Collection $knownItems = null): int
    {
        $items = $knownItems ?? $deliveryNote->deliveryNoteItems()->get();

        return $items
            ->filter(fn (DeliveryNoteItem $item) => static::quantityLeftToPick($item) > 0)
            ->count();
    }

    public function rules(): array
    {
        return [
            'barcode'               => ['required', 'string', 'max:255'],
            'tab'                   => ['sometimes', 'nullable', 'string'],
            'quantity'              => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id' => ['sometimes', 'nullable', 'integer'],
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): array
    {
        $this->user         = $request->user();
        $this->deliveryNote = $deliveryNote;

        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user(), $this->validatedData);
    }
}
