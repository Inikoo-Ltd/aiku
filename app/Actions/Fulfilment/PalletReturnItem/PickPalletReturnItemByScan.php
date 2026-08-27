<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\PickWholePalletInPalletReturn;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Picks the item of a pallet return matching a code coming from a barcode scanner. A whole-pallet
 * return matches the pallet reference printed on the pallet label and picks the whole pallet in
 * one scan; a stored-item return matches the stored item reference and counts one unit per scan,
 * the way dropshipping picking used to work. Customer references match too: they are what the
 * customer stuck on the goods before sending them in, so they are what a scanner may actually see.
 *
 * @phpstan-type ScanOutcome array{status: string, message: string, scanned: string, item: array|null, pallet_return_state: string, remaining_to_pick: int}
 */
class PickPalletReturnItemByScan extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn, User $user, array $modelData): array
    {
        $scanned  = trim((string)data_get($modelData, 'barcode'));
        $quantity = (float)data_get($modelData, 'quantity', 1);

        if ($palletReturn->state != PalletReturnStateEnum::PICKING) {
            return $this->outcome(
                $palletReturn,
                'wrong_state',
                __('This return is not being picked'),
                $scanned
            );
        }

        $items        = $this->loadItems($palletReturn);
        $matchedItems = $this->matchItems($items, $scanned);

        if ($matchedItems->isEmpty()) {
            return $this->outcome(
                $palletReturn,
                'not_found',
                __(':scanned does not belong to this return', ['scanned' => $scanned]),
                $scanned,
                knownItems: $items
            );
        }

        $itemToPick = $matchedItems->first(fn (PalletReturnItem $item) => $this->quantityLeftToPick($item) > 0);

        if (!$itemToPick) {
            return $this->outcome(
                $palletReturn,
                'already_picked',
                __(':reference is already picked', ['reference' => $this->itemReference($matchedItems->first())]),
                $scanned,
                $matchedItems->first(),
                knownItems: $items
            );
        }

        if ($itemToPick->type == 'Pallet') {
            PickWholePalletInPalletReturn::make()->action($itemToPick, [], user: $user);
            $message = __('Pallet :reference picked whole', ['reference' => $this->itemReference($itemToPick)]);
        } else {
            $quantityToPick = min($quantity, $this->quantityLeftToPick($itemToPick));
            PickPalletReturnItemInPalletReturnWithStoredItem::make()->action($itemToPick, [
                'quantity_picked' => (float)$itemToPick->quantity_picked + $quantityToPick,
            ]);
            $message = __('Picked :quantity x :reference', [
                'quantity'  => $quantityToPick + 0,
                'reference' => $this->itemReference($itemToPick),
            ]);
        }

        $itemToPick->refresh();
        $palletReturn->refresh();

        $remainingAfter = $this->quantityLeftToPick($itemToPick);
        if ($remainingAfter > 0) {
            $message .= ', '.__(':remaining still to pick', ['remaining' => $remainingAfter + 0]);
        }

        return $this->outcome($palletReturn, 'picked', $message, $scanned, $itemToPick);
    }

    /**
     * @return Collection<int, PalletReturnItem>
     */
    protected function loadItems(PalletReturn $palletReturn): Collection
    {
        return PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->with(['pallet', 'storedItem'])
            ->get();
    }

    /**
     * A whole-pallet return answers to the pallet, a stored-item return to the stored item; both
     * to the warehouse reference (what the label printer put on it) and to the customer's own
     * reference (what came stuck on the goods).
     *
     * @param  Collection<int, PalletReturnItem>  $items
     *
     * @return Collection<int, PalletReturnItem>
     */
    protected function matchItems(Collection $items, string $scanned): Collection
    {
        if ($scanned === '') {
            return collect();
        }

        $matches = fn (?string $reference) => $reference !== null && strcasecmp(trim($reference), $scanned) === 0;

        return $items->filter(function (PalletReturnItem $item) use ($matches) {
            if ($item->type == 'Pallet') {
                return $matches($item->pallet?->reference) || $matches($item->pallet?->customer_reference);
            }

            return $matches($item->storedItem?->reference);
        })->values();
    }

    protected function quantityLeftToPick(PalletReturnItem $item): float
    {
        if ($item->type == 'Pallet') {
            return $item->state == PalletReturnItemStateEnum::PICKED ? 0 : 1;
        }

        return max(
            0,
            (float)$item->quantity_ordered - (float)($item->quantity_picked ?? 0) - (float)($item->quantity_not_picked ?? 0)
        );
    }

    protected function itemReference(PalletReturnItem $item): string
    {
        return ($item->type == 'Pallet' ? $item->pallet?->reference : $item->storedItem?->reference) ?? '';
    }

    /**
     * @param  Collection<int, PalletReturnItem>|null  $knownItems
     */
    protected function outcome(
        PalletReturn $palletReturn,
        string $status,
        string $message,
        string $scanned,
        ?PalletReturnItem $palletReturnItem = null,
        ?Collection $knownItems = null
    ): array {
        $items = $knownItems ?? $this->loadItems($palletReturn);

        return [
            'status'              => $status,
            'message'             => $message,
            'scanned'             => $scanned,
            'item'                => $palletReturnItem ? [
                'id'               => $palletReturnItem->id,
                'type'             => $palletReturnItem->type,
                'reference'        => $this->itemReference($palletReturnItem),
                'quantity_ordered' => (float)$palletReturnItem->quantity_ordered,
                'quantity_picked'  => (float)($palletReturnItem->quantity_picked ?? 0),
                'quantity_to_pick' => $this->quantityLeftToPick($palletReturnItem),
                'location_code'    => $palletReturnItem->pallet?->location?->code,
            ] : null,
            'pallet_return_state' => $palletReturn->state->value,
            'remaining_to_pick'   => $items->filter(fn (PalletReturnItem $item) => $this->quantityLeftToPick($item) > 0)->count(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if (!data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false)) {
            return false;
        }

        return $request->user()->authTo([
            "fulfilment.{$this->warehouse->id}.edit",
            "supervisor-incoming.".$this->warehouse->id,
            "supervisor-fulfilment.".$this->warehouse->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'barcode'  => ['required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(PalletReturn $palletReturn, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        return $this->handle($palletReturn, $request->user(), $this->validatedData);
    }
}
