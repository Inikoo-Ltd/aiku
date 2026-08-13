<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Mon, 10 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;
use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemPicking;
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
 * Picks the item matching a scanned code in one of the delivery notes of a picking session, and
 * answers with the refreshed table row so the picking screen can be updated in place without an
 * Inertia round trip.
 *
 * @phpstan-type ScanOutcome array{status: string, message: string, scanned: string, item: array|null, delivery_note: array|null, row: array|null, delivery_note_state: string, picking_session_state: string, remaining_to_pick: int}
 */
class PickPickingSessionItemByScan extends OrgAction
{
    use WithScannedDeliveryNoteItemMatching;
    use WithScannedDeliveryNoteItemPicking;

    protected User $user;

    /**
     * The picking screen of a session is open to whoever works the session, so the only extra gate is
     * the organisation opting into scanning. Without it the endpoint would stay live for
     * organisations that never enabled the feature.
     */
    public function authorize(ActionRequest $request): bool
    {
        return (bool)data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false);
    }

    /**
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession, User $user, array $modelData): array
    {
        $scanned           = trim((string)data_get($modelData, 'barcode'));
        $tab               = data_get($modelData, 'tab');
        $requestedItemId   = data_get($modelData, 'delivery_note_item_id');
        $requestedLocation = data_get($modelData, 'location_org_stock_id');
        $requestedQuantity = data_get($modelData, 'quantity');
        $requestedQuantity = $requestedQuantity === null ? null : (float)$requestedQuantity;

        if ($pickingSession->state != PickingSessionStateEnum::HANDLING) {
            return $this->outcome(
                $pickingSession,
                'wrong_state',
                __('This picking session is not being picked'),
                $scanned
            );
        }

        $sessionItems = $this->loadDeliveryNoteItems($pickingSession);
        $matchedItems = $this->matchItems($sessionItems, $scanned);

        // The 'pick the rest' button targets the exact item that was just scanned, so a session
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

        $itemToPick = $this->pickItemToPick($matchedItems);

        if (!$itemToPick) {
            return $this->outcomeForNothingPicked($pickingSession, $matchedItems, $scanned, $sessionItems);
        }

        $location       = $this->pickingLocation($itemToPick, $requestedLocation);
        $quantityToPick = $location ? $this->storeScannedPicking($itemToPick, $user, $location, $requestedQuantity) : 0;

        if ($quantityToPick <= 0) {
            return $this->outcome(
                $pickingSession,
                'no_stock',
                __('No picking location holds stock for :code', ['code' => $itemToPick->orgStock?->code ?? $scanned]),
                $scanned,
                $itemToPick,
                location: $location,
                knownItems: $sessionItems
            );
        }

        $itemToPick->refresh();
        $pickingSession->refresh();

        $remainingAfter = static::quantityLeftToPick($itemToPick);

        $message = $remainingAfter > 0
            ? __('Picked :quantity x :code from :location for :reference, :remaining still to pick', [
                'quantity'  => $quantityToPick + 0,
                'code'      => $itemToPick->orgStock?->code ?? $scanned,
                'location'  => $location->location_code,
                'reference' => $itemToPick->deliveryNote->reference,
                'remaining' => $remainingAfter + 0,
            ])
            : __('Picked :quantity x :code from :location for :reference', [
                'quantity'  => $quantityToPick + 0,
                'code'      => $itemToPick->orgStock?->code ?? $scanned,
                'location'  => $location->location_code,
                'reference' => $itemToPick->deliveryNote->reference,
            ]);

        return $this->outcome($pickingSession, 'picked', $message, $scanned, $itemToPick, $tab, location: $location);
    }

    /**
     * A picker empties one shelf at a time, so a code present in several delivery notes goes to the
     * note that is already half picked before any untouched one; delivery note id keeps the choice
     * stable once nothing is started yet.
     *
     * @param  Collection<int, DeliveryNoteItem>  $matchedItems
     */
    protected function pickItemToPick(Collection $matchedItems): ?DeliveryNoteItem
    {
        return $matchedItems
            ->filter(fn (DeliveryNoteItem $item) => $this->canBePickedNow($item))
            ->sortBy('delivery_note_id')
            ->sortBy(fn (DeliveryNoteItem $item) => (float)$item->quantity_picked > 0 ? 0 : 1)
            ->first();
    }

    protected function canBePickedNow(DeliveryNoteItem $deliveryNoteItem): bool
    {
        return $this->isDeliveryNoteBeingPicked($deliveryNoteItem->deliveryNote)
            && static::quantityLeftToPick($deliveryNoteItem) > 0;
    }

    protected function isDeliveryNoteBeingPicked(DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->state == DeliveryNoteStateEnum::HANDLING;
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>  $matchedItems
     * @param  Collection<int, DeliveryNoteItem>  $sessionItems
     */
    protected function outcomeForNothingPicked(
        PickingSession $pickingSession,
        Collection $matchedItems,
        string $scanned,
        Collection $sessionItems
    ): array {
        $blockedItem = $matchedItems->first(
            fn (DeliveryNoteItem $item) => !$this->isDeliveryNoteBeingPicked($item->deliveryNote)
        );

        if ($blockedItem) {
            return $this->outcome(
                $pickingSession,
                'wrong_state',
                __(':reference can not be picked, it is :state', [
                    'reference' => $blockedItem->deliveryNote->reference,
                    'state'     => DeliveryNoteStateEnum::labels()[$blockedItem->deliveryNote->state->value],
                ]),
                $scanned,
                $blockedItem,
                knownItems: $sessionItems
            );
        }

        $item = $matchedItems->first();

        return $this->outcome(
            $pickingSession,
            'already_picked',
            __(':code has nothing left to pick in this session', ['code' => $item->orgStock?->code ?? $scanned]),
            $scanned,
            $item,
            knownItems: $sessionItems
        );
    }

    /**
     * Every item of the session is read in one go, together with the delivery note and shop it
     * belongs to, so deciding where a scan lands costs no extra query.
     *
     * @return Collection<int, DeliveryNoteItem>
     */
    protected function loadDeliveryNoteItems(PickingSession $pickingSession): Collection
    {
        return $pickingSession->deliveryNotesItems()
            ->with(['orgStock', 'shop', 'deliveryNote'])
            ->get();
    }

    /**
     * $knownItems lets an outcome that wrote nothing reuse the collection already loaded by handle().
     * A missed scan is common on a picking round and should not cost a second full load; the 'picked'
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
        ?Collection $knownItems = null,
        ?object $location = null
    ): array {
        $deliveryNote = $deliveryNoteItem?->deliveryNote;
        $row          = null;
        $warning      = null;

        if ($deliveryNoteItem && $status === 'picked') {
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
                'id'                    => $deliveryNoteItem->id,
                'code'                  => $deliveryNoteItem->orgStock?->code,
                'name'                  => $deliveryNoteItem->orgStock?->name,
                'quantity_required'     => (float)$deliveryNoteItem->quantity_required,
                'quantity_picked'       => (float)$deliveryNoteItem->quantity_picked,
                'quantity_to_pick'      => static::quantityLeftToPick($deliveryNoteItem),
                'location_code'         => $location?->location_code,
                'location_org_stock_id' => $location?->id,
            ] : null,
            'delivery_note'         => $deliveryNote ? [
                'id'        => $deliveryNote->id,
                'reference' => $deliveryNote->reference,
                'state'     => $deliveryNote->state->value,
            ] : null,
            'row'                   => $row,
            'delivery_note_state'   => $deliveryNote?->state->value ?? '',
            'picking_session_state' => $pickingSession->state->value,
            'remaining_to_pick'     => $this->countRemainingToPick($pickingSession, $knownItems),
        ];
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function countRemainingToPick(PickingSession $pickingSession, ?Collection $knownItems = null): int
    {
        $items = $knownItems ?? $this->loadDeliveryNoteItems($pickingSession);

        return $items
            ->filter(fn (DeliveryNoteItem $item) => $this->canBePickedNow($item))
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
    public function asController(PickingSession $pickingSession, ActionRequest $request): array
    {
        $this->user = $request->user();

        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $request->user(), $this->validatedData);
    }
}
