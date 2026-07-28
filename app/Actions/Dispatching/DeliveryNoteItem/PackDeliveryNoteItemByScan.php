<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Tue, 28 Jul 2026 10:00:00 Bali, Indonesia
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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Packs, in one shot, the delivery note item matching a code coming from a barcode scanner,
 * and answers with the refreshed table row so the packing screen can be updated in place
 * without an Inertia round trip.
 *
 * @phpstan-type ScanOutcome array{status: string, message: string, scanned: string, item: array|null, row: array|null, delivery_note_state: string, remaining_to_pack: int}
 */
class PackDeliveryNoteItemByScan extends OrgAction
{
    use WithDeliveryNoteItemUI;

    protected User $user;

    private DeliveryNote $deliveryNote;

    /**
     * Packing through a scan must be gated by the same rule that decides whether the packing UI is
     * shown at all, so the endpoint cannot be used to pack a delivery note somebody else is handling.
     */
    public function authorize(ActionRequest $request): bool
    {
        return $this->canHandleDeliveryNote($this->deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, User $user, array $modelData): array
    {
        $scanned           = trim((string)data_get($modelData, 'barcode'));
        $tab               = data_get($modelData, 'tab');
        $requestedItemId   = data_get($modelData, 'delivery_note_item_id');
        $requestedQuantity = data_get($modelData, 'quantity');
        $requestedQuantity = $requestedQuantity === null ? null : (float)$requestedQuantity;

        if (!in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED])) {
            return $this->outcome(
                $deliveryNote,
                'wrong_state',
                __('This delivery note is not in packing state'),
                $scanned
            );
        }

        $deliveryNoteItems = $deliveryNote->deliveryNoteItems()->with(['orgStock', 'packings'])->get();
        $matchedItems      = $this->matchItems($deliveryNoteItems, $scanned);

        // The 'pack the rest' button targets the exact item that was just scanned, so a delivery note
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

        $itemToPack = $matchedItems->first(
            fn (DeliveryNoteItem $item) => (float)$item->quantity_picked != 0
                && UpdateDeliveryNoteItemPacking::quantityLeftToPack($item) > 0
        );

        if (!$itemToPack) {
            $packedItem = $matchedItems->first(fn (DeliveryNoteItem $item) => $item->packings->count() > 0);

            if ($packedItem) {
                return $this->outcome(
                    $deliveryNote,
                    'already_packed',
                    __(':code is already fully packed', ['code' => $packedItem->orgStock?->code ?? $scanned]),
                    $scanned,
                    $packedItem,
                    knownItems: $deliveryNoteItems
                );
            }

            return $this->outcome(
                $deliveryNote,
                'nothing_to_pack',
                __('Nothing picked to pack for :code', ['code' => $matchedItems->first()->orgStock?->code ?? $scanned]),
                $scanned,
                $matchedItems->first(),
                knownItems: $deliveryNoteItems
            );
        }

        $packedBefore = (float)$itemToPack->packings()->sum('quantity');

        // 'Pack all' passes null so the remainder is resolved inside the action's lock. Reading it
        // out here would let a second packer's scan land in between and turn this into an error
        // instead of simply packing whatever is genuinely left.
        $quantityToPack = $requestedQuantity === null
            ? null
            : min($requestedQuantity, UpdateDeliveryNoteItemPacking::quantityLeftToPack($itemToPack));

        UpdateDeliveryNoteItemPacking::make()->action($itemToPack, $user, $quantityToPack);

        $itemToPack->refresh();
        $deliveryNote->refresh();

        $quantityToPack = round((float)$itemToPack->packings()->sum('quantity') - $packedBefore, 3);
        $remainingAfter = UpdateDeliveryNoteItemPacking::quantityLeftToPack($itemToPack);

        $message = $remainingAfter > 0
            ? __('Packed :quantity x :code, :remaining still to pack', [
                'quantity'  => $quantityToPack,
                'code'      => $itemToPack->orgStock?->code ?? $scanned,
                'remaining' => $remainingAfter,
            ])
            : __('Packed :quantity x :code', [
                'quantity' => $quantityToPack,
                'code'     => $itemToPack->orgStock?->code ?? $scanned,
            ]);

        return $this->outcome($deliveryNote, 'packed', $message, $scanned, $itemToPack, $tab);
    }

    /**
     * Items are matched on the org stock code first, which is what warehouse labels carry,
     * then on the EAN of any trade unit behind the org stock, which is what supplier
     * packaging carries.
     *
     * @param  Collection<int, DeliveryNoteItem>  $deliveryNoteItems
     *
     * @return Collection<int, DeliveryNoteItem>
     */
    protected function matchItems(Collection $deliveryNoteItems, string $scanned): Collection
    {
        if ($scanned === '') {
            return collect();
        }

        $matchedByCode = $deliveryNoteItems->filter(
            fn (DeliveryNoteItem $item) => strcasecmp(trim((string)$item->orgStock?->code), $scanned) === 0
        );

        if ($matchedByCode->isNotEmpty()) {
            return $matchedByCode->values();
        }

        $orgStockIds = $deliveryNoteItems->pluck('org_stock_id')->filter()->unique()->all();

        if (!$orgStockIds) {
            return collect();
        }

        $matchedOrgStockIds = DB::table('model_has_trade_units')
            ->join('trade_units', 'trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
            ->leftJoin('barcodes', 'barcodes.id', '=', 'trade_units.barcode_id')
            ->leftJoin('model_has_barcodes', function ($join) {
                $join->on('model_has_barcodes.model_id', '=', 'trade_units.id')
                    ->where('model_has_barcodes.model_type', '=', 'TradeUnit');
            })
            ->leftJoin('barcodes as attached_barcodes', 'attached_barcodes.id', '=', 'model_has_barcodes.barcode_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->whereIn('model_has_trade_units.model_id', $orgStockIds)
            ->where(function ($query) use ($scanned) {
                $query->where('barcodes.number', $scanned)
                    ->orWhere('attached_barcodes.number', $scanned);
            })
            ->pluck('model_has_trade_units.model_id')
            ->all();

        if (!$matchedOrgStockIds) {
            return collect();
        }

        return $deliveryNoteItems->whereIn('org_stock_id', $matchedOrgStockIds)->values();
    }

    /**
     * $knownItems lets an outcome that wrote nothing reuse the collection already loaded by handle().
     * A missed scan is common on a packing bench and should not cost a second full load; the 'packed'
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
        ?Collection $knownItems = null
    ): array {
        $row = null;

        if ($deliveryNoteItem && $status === 'packed') {
            $row = FetchDeliveryNoteItemRow::run($deliveryNoteItem, $tab);
            $row = $row?->toArray(request());
        }

        return [
            'status'              => $status,
            'message'             => $message,
            'scanned'             => $scanned,
            'item'                => $deliveryNoteItem ? [
                'id'               => $deliveryNoteItem->id,
                'code'             => $deliveryNoteItem->orgStock?->code,
                'name'             => $deliveryNoteItem->orgStock?->name,
                'quantity_picked'  => (float)$deliveryNoteItem->quantity_picked,
                'quantity_packed'  => (float)$deliveryNoteItem->quantity_packed,
                'quantity_to_pack' => UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem),
            ] : null,
            'row'                 => $row,
            'delivery_note_state' => $deliveryNote->state->value,
            'remaining_to_pack'   => $this->countRemainingToPack($deliveryNote, $knownItems),
        ];
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>|null  $knownItems
     */
    protected function countRemainingToPack(DeliveryNote $deliveryNote, ?Collection $knownItems = null): int
    {
        $items = $knownItems ?? $deliveryNote->deliveryNoteItems()->with('packings')->get();

        return $items
            ->filter(
                fn (DeliveryNoteItem $item) => empty((float)$item->quantity_not_picked)
                    && !UpdateDeliveryNoteItemPacking::isFullyPacked($item)
            )
            ->count();
    }

    public function rules(): array
    {
        return [
            'barcode'               => ['required', 'string', 'max:255'],
            'tab'                    => ['sometimes', 'nullable', 'string'],
            'quantity'               => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'delivery_note_item_id'  => ['sometimes', 'nullable', 'integer'],
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
