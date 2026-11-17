<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemMovement\StoredItemMovementTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItemMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait WithMobilePalletReturnLines
{
    use WithMobileFulfilmentOutbound;

    protected function typeGroup(PalletReturnTypeEnum $type): string
    {
        return $type === PalletReturnTypeEnum::PALLET ? 'pallets' : 'items';
    }

    protected function typeGroupLabel(PalletReturnTypeEnum $type): string
    {
        return $type === PalletReturnTypeEnum::PALLET ? __('Pallets') : __('Stored items');
    }

    protected function lineType(PalletReturn $palletReturn): string
    {
        return $palletReturn->type === PalletReturnTypeEnum::PALLET ? 'Pallet' : 'StoredItem';
    }

    /**
     * @return Collection<int, PalletReturnItem>
     */
    protected function returnLines(PalletReturn $palletReturn): Collection
    {
        $isPallets = $palletReturn->type === PalletReturnTypeEnum::PALLET;

        return PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->where('type', $this->lineType($palletReturn))
            ->with($isPallets ? ['pallet.location', 'pallet.palletStoredItems.storedItem', 'pickingLocation'] : ['pallet.location', 'pickingLocation', 'storedItem', 'palletStoredItem'])
            ->get()
            ->sortBy(fn (PalletReturnItem $line) => $this->walkKey($this->lineLocation($line), $line->pallet, $line->id))
            ->values();
    }

    protected function walkKey(?Location $location, ?Pallet $pallet, int $id): string
    {
        return sprintf('%s|%s|%012d', $location?->code ?? '~', $pallet?->reference ?? '~', $id);
    }

    protected function lineLocation(PalletReturnItem $line): ?Location
    {
        return $line->pallet?->location ?? $line->pickingLocation;
    }

    protected function quantityOnPallet(PalletReturnItem $line): float
    {
        return max(0, (float) $line->palletStoredItem?->quantity);
    }

    /**
     * @return array<int, string>
     */
    protected function lineActionKeys(PalletReturnItem $line): array
    {
        if ($line->type === 'Pallet') {
            if ($line->has_waiting_crm) {
                return ['undo'];
            }

            return match ($line->state) {
                PalletReturnItemStateEnum::PICKING => ['pick', 'not_picked'],
                PalletReturnItemStateEnum::PICKED  => ['undo'],
                default                            => [],
            };
        }

        if ($line->has_waiting_crm) {
            return [];
        }

        $quantityToPick = $line->getQuantityLeftToPick();

        return array_values(array_filter([
            $quantityToPick > 0 && $this->quantityOnPallet($line) > 0 ? 'pick' : null,
            $quantityToPick > 0 ? 'not_picked' : null,
            (float) $line->quantity_picked > 0 || (float) $line->quantity_not_picked > 0 ? 'undo' : null,
        ]));
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function lineActions(PalletReturnItem $line, bool $linesEditable): array
    {
        if (!$linesEditable) {
            return [];
        }

        return array_map(fn (string $key) => $this->actionOption($key, match ($key) {
            'pick'       => __('Pick'),
            'not_picked' => __('Not picked'),
            'undo'       => __('Undo'),
        }), $this->lineActionKeys($line));
    }

    /**
     * @return array{id: int, reference: string|null, customer_reference: string|null}
     */
    protected function palletReference(Pallet $pallet): array
    {
        return ['id' => $pallet->id, 'reference' => $pallet->reference, 'customer_reference' => $pallet->customer_reference];
    }

    /**
     * @return array<string, mixed>
     */
    protected function palletSummary(Pallet $pallet): array
    {
        return [
            ...$this->palletReference($pallet),
            'type'        => $pallet->type->value,
            'type_label'  => $this->palletTypeLabel($pallet->type),
            'state'       => $pallet->state->value,
            'state_label' => PalletStateEnum::labels()[$pallet->state->value],
        ];
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     * @return array<int, array<string, mixed>>
     */
    protected function palletLineRows(Collection $lines, bool $linesEditable): array
    {
        $pickedLineIds = $lines
            ->filter(fn (PalletReturnItem $line) => in_array($line->state, [PalletReturnItemStateEnum::PICKED, PalletReturnItemStateEnum::DISPATCHED], true))
            ->pluck('id');

        $pickedQuantities = $pickedLineIds->isEmpty() ? collect() : StoredItemMovement::whereIn('pallet_return_item_id', $pickedLineIds)
            ->where('type', StoredItemMovementTypeEnum::PICKED)
            ->groupBy('pallet_return_item_id', 'stored_item_id')
            ->selectRaw('pallet_return_item_id, stored_item_id, -sum(quantity) as quantity')
            ->get()
            ->groupBy('pallet_return_item_id')
            ->map(fn (Collection $movements) => $movements->pluck('quantity', 'stored_item_id'));

        return $lines->map(function (PalletReturnItem $line) use ($linesEditable, $pickedQuantities) {
            $pallet         = $line->pallet;
            $pickedFromLine = $pickedQuantities->get($line->id, collect());

            return [
                'line_id'                  => $line->id,
                'line_state'               => $line->state->value,
                'line_state_label'         => PalletReturnItemStateEnum::labels()[$line->state->value],
                'waiting_customer_service' => (bool) $line->has_waiting_crm,
                'pallet'                   => $pallet ? $this->palletSummary($pallet) : null,
                'location'                 => $this->locationReference($this->lineLocation($line)),
                'stored_items'             => $pallet ? $pallet->palletStoredItems
                    ->filter(fn (PalletStoredItem $palletStoredItem) => $palletStoredItem->storedItem !== null)
                    ->map(fn (PalletStoredItem $palletStoredItem) => [
                        'reference' => $palletStoredItem->storedItem->reference,
                        'name'      => $palletStoredItem->storedItem->name,
                        'quantity'  => (int) ($pickedFromLine->get($palletStoredItem->stored_item_id) ?? $palletStoredItem->quantity),
                    ])
                    ->values()
                    ->all() : [],
                'available_actions'        => $this->lineActions($line, $linesEditable),
            ];
        })->values()->all();
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     * @return array<int, array<string, mixed>>
     */
    protected function storedItemRows(Warehouse $warehouse, PalletReturn $palletReturn, Collection $lines, bool $linesEditable): array
    {
        $otherPallets = $this->otherPalletsByStoredItem($warehouse, $palletReturn, $lines);

        return $lines->groupBy('stored_item_id')->map(function (Collection $itemLines) use ($linesEditable, $otherPallets) {
            /** @var PalletReturnItem $first */
            $first      = $itemLines->first();
            $storedItem = $first->storedItem;

            return [
                'stored_item'         => [
                    'id'        => $storedItem?->id,
                    'reference' => $storedItem?->reference,
                    'name'      => $storedItem?->name,
                    'barcode'   => $storedItem?->barcode,
                ],
                'quantity_ordered'    => (int) $itemLines->sum(fn (PalletReturnItem $line) => (float) $line->quantity_ordered),
                'quantity_picked'     => (int) $itemLines->sum(fn (PalletReturnItem $line) => (float) $line->quantity_picked),
                'quantity_not_picked' => (int) $itemLines->sum(fn (PalletReturnItem $line) => (float) $line->quantity_not_picked),
                'quantity_to_pick'    => (int) $itemLines->sum(fn (PalletReturnItem $line) => $line->getQuantityLeftToPick()),
                'lines'               => $itemLines->map(fn (PalletReturnItem $line) => [
                    'line_id'                  => $line->id,
                    'pallet'                   => $line->pallet ? $this->palletReference($line->pallet) : null,
                    'location'                 => $this->locationReference($this->lineLocation($line)),
                    'quantity_ordered'         => (int) $line->quantity_ordered,
                    'quantity_picked'          => (int) $line->quantity_picked,
                    'quantity_not_picked'      => (int) $line->quantity_not_picked,
                    'quantity_to_pick'         => (int) $line->getQuantityLeftToPick(),
                    'quantity_on_pallet'       => (int) $this->quantityOnPallet($line),
                    'state'                    => $line->state->value,
                    'state_label'              => PalletReturnItemStateEnum::labels()[$line->state->value],
                    'waiting_customer_service' => (bool) $line->has_waiting_crm,
                    'available_actions'        => $this->lineActions($line, $linesEditable),
                ])->values()->all(),
                'other_pallets'       => $otherPallets->get($first->stored_item_id, collect())->values()->all(),
            ];
        })->values()->all();
    }

    protected function otherPalletStoredItemsQuery(Warehouse $warehouse, PalletReturn $palletReturn): Builder
    {
        return PalletStoredItem::query()
            ->where('state', '!=', PalletStoredItemStateEnum::RETURNED)
            ->whereHas('pallet', fn (Builder $pallet) => $pallet->where('warehouse_id', $warehouse->id)->where('state', PalletStateEnum::STORING))
            ->whereDoesntHave('palletReturnItems', fn (Builder $line) => $line->where('pallet_return_id', $palletReturn->id))
            ->whereIn('stored_item_id', PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('type', 'StoredItem')->select('stored_item_id'));
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    protected function otherPalletsByStoredItem(Warehouse $warehouse, PalletReturn $palletReturn, Collection $lines): Collection
    {
        if ($palletReturn->state !== PalletReturnStateEnum::PICKING || $lines->isEmpty()) {
            return collect();
        }

        return $this->otherPalletStoredItemsQuery($warehouse, $palletReturn)
            ->with(['pallet.location', 'reservingPalletReturnItems.palletReturn'])
            ->get()
            ->map(fn (PalletStoredItem $palletStoredItem) => [
                'stored_item_id'        => $palletStoredItem->stored_item_id,
                'walk_key'              => $this->walkKey($palletStoredItem->pallet->location, $palletStoredItem->pallet, $palletStoredItem->id),
                'pallet_stored_item_id' => $palletStoredItem->id,
                'pallet'                => $this->palletReference($palletStoredItem->pallet),
                'location'              => $this->locationReference($palletStoredItem->pallet->location),
                'quantity_available'    => (int) PalletStoredItem::calculateMaxQuantityForReturn(
                    (float) $palletStoredItem->quantity,
                    (float) $palletStoredItem->getStockReservations($palletReturn->id)['reserved_quantity'],
                    0
                ),
            ])
            ->filter(fn (array $row) => $row['quantity_available'] > 0)
            ->sortBy('walk_key')
            ->groupBy('stored_item_id')
            ->map(fn (Collection $rows) => $rows->map(fn (array $row) => collect($row)->except(['stored_item_id', 'walk_key'])->all()));
    }

    protected function lineProgress(PalletReturnItem $line): string
    {
        if ($line->has_waiting_crm) {
            return 'waiting_customer_service';
        }

        if ($line->type === 'Pallet') {
            return match ($line->state) {
                PalletReturnItemStateEnum::PICKED, PalletReturnItemStateEnum::DISPATCHED => 'picked',
                PalletReturnItemStateEnum::NOT_PICKED, PalletReturnItemStateEnum::CANCEL => 'not_picked',
                default                                                                  => 'to_pick',
            };
        }

        return match (true) {
            $line->state === PalletReturnItemStateEnum::CANCEL => 'not_picked',
            $line->getQuantityLeftToPick() > 0                 => 'to_pick',
            (float) $line->quantity_picked > 0                 => 'picked',
            default                                            => 'not_picked',
        };
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     * @return array{lines: int, picked: int, not_picked: int, to_pick: int, waiting_customer_service: int}
     */
    protected function lineCounts(Collection $lines): array
    {
        $counts = ['lines' => $lines->count(), 'picked' => 0, 'not_picked' => 0, 'to_pick' => 0, 'waiting_customer_service' => 0];
        foreach ($lines as $line) {
            $counts[$this->lineProgress($line)]++;
        }

        return $counts;
    }

    /**
     * @param Collection<int, PalletReturnItem> $lines
     */
    protected function finishPickingBlocked(PalletReturn $palletReturn, Collection $lines): ?string
    {
        if ($palletReturn->type === PalletReturnTypeEnum::PALLET) {
            $openLines = $lines->filter(fn (PalletReturnItem $line) => $line->pallet?->state !== PalletStateEnum::DISPATCHED);

            if ($openLines->isEmpty()) {
                return __('This return has no pallets to pick.');
            }

            if ($openLines->contains(fn (PalletReturnItem $line) => (bool) $line->has_waiting_crm)) {
                return __('Customer service must resolve the pallets set as not picked first.');
            }

            $toPick = $openLines->reject(fn (PalletReturnItem $line) => in_array($line->state, [
                PalletReturnItemStateEnum::PICKED,
                PalletReturnItemStateEnum::NOT_PICKED,
                PalletReturnItemStateEnum::CANCEL,
            ], true))->count();

            return $toPick ? trans_choice(':count pallet is still to pick.|:count pallets are still to pick.', $toPick, ['count' => $toPick]) : null;
        }

        if ($lines->isEmpty()) {
            return __('This return has no items to pick.');
        }

        $pending = $lines->filter(fn (PalletReturnItem $line) => $line->state !== PalletReturnItemStateEnum::CANCEL
            && $line->pallet?->state === PalletStateEnum::STORING
            && (float) $line->quantity_picked + (float) $line->quantity_not_picked < (float) $line->quantity_ordered)->count();

        return $pending ? trans_choice(':count line is still to pick or set as not picked.|:count lines are still to pick or set as not picked.', $pending, ['count' => $pending]) : null;
    }
}
