<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobileStoredItem
{
    use AsController;
    use WithMobileStoredItems;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pallets(Warehouse $warehouse, StoredItem $storedItem): array
    {
        return $this->palletStoredItemsInWarehouse($warehouse)
            ->leftJoin('locations', 'locations.id', '=', 'pallets.location_id')
            ->where('pallet_stored_items.stored_item_id', $storedItem->id)
            ->select([
                'pallets.id',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.state',
                'locations.id as location_id',
                'locations.code as location_code',
                'pallet_stored_items.quantity',
            ])
            ->orderByRaw('locations.code ASC NULLS LAST')
            ->orderBy('pallets.reference')
            ->get()
            ->map(fn (object $row) => [
                'pallet'   => [
                    'id'                 => $row->id,
                    'reference'          => $row->reference,
                    'customer_reference' => $row->customer_reference,
                    'state'              => $row->state,
                    'state_label'        => PalletStateEnum::labels()[$row->state] ?? $row->state,
                ],
                'location' => $row->location_id ? ['id' => $row->location_id, 'code' => $row->location_code] : null,
                'quantity' => (int) $row->quantity,
            ])
            ->values()
            ->all();
    }

    public function asController(Warehouse $warehouse, StoredItem $storedItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewFulfilment($user, $warehouse);

        /** @var StoredItem|null $scoped */
        $scoped = $this->storedItemsInWarehouse($warehouse)->with('fulfilmentCustomer.customer')->where('stored_items.id', $storedItem->id)->first();
        abort_unless($scoped, 404);

        return response()->json([
            'data' => [
                ...$this->storedItemRow($scoped),
                'pallets' => $this->pallets($warehouse, $scoped),
            ],
        ]);
    }
}
