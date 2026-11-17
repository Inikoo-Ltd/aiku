<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

trait WithMobileStoredItems
{
    use WithMobileFulfilmentOutbound;

    protected function palletStoredItemsInWarehouse(Warehouse $warehouse): QueryBuilder
    {
        return DB::table('pallet_stored_items')
            ->join('pallets', 'pallets.id', '=', 'pallet_stored_items.pallet_id')
            ->where('pallets.warehouse_id', $warehouse->id)
            ->whereNull('pallets.deleted_at')
            ->where('pallet_stored_items.state', PalletStoredItemStateEnum::ACTIVE->value);
    }

    protected function storedItemsInWarehouse(Warehouse $warehouse): Builder
    {
        $inWarehouse = $this->palletStoredItemsInWarehouse($warehouse)
            ->groupBy('pallet_stored_items.stored_item_id')
            ->select([
                'pallet_stored_items.stored_item_id',
                DB::raw('SUM(pallet_stored_items.quantity) as warehouse_quantity'),
                DB::raw('COUNT(DISTINCT pallet_stored_items.pallet_id) as warehouse_pallets'),
            ]);

        return StoredItem::query()
            ->joinSub($inWarehouse, 'in_warehouse', 'in_warehouse.stored_item_id', '=', 'stored_items.id')
            ->select('stored_items.*', 'in_warehouse.warehouse_quantity', 'in_warehouse.warehouse_pallets');
    }

    /**
     * @return array<string, mixed>
     */
    protected function storedItemRow(StoredItem $storedItem): array
    {
        return [
            'id'             => $storedItem->id,
            'reference'      => $storedItem->reference,
            'name'           => $storedItem->name,
            'barcode'        => $storedItem->barcode,
            'state'          => $storedItem->state->value,
            'state_label'    => StoredItemStateEnum::labels()[$storedItem->state->value],
            'customer'       => $this->customerReference($storedItem->fulfilmentCustomer),
            'quantity'       => (int) $storedItem->getAttribute('warehouse_quantity'),
            'number_pallets' => (int) $storedItem->getAttribute('warehouse_pallets'),
        ];
    }
}
