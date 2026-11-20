<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\Api\Group\Inventory\WithMobileStockWriteRequests;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsPlaced;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

trait WithMobileStockDeliveryPlacing
{
    use WithMobileStockDeliveries;
    use WithMobileStockWriteRequests;

    /**
     * @throws Throwable
     */
    protected function placeFromRequest(Warehouse $warehouse, StockDelivery $stockDelivery, StockDeliveryItem $stockDeliveryItem, ActionRequest $request, bool $withQuantity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);
        $this->ensureItemOfStockDelivery($stockDelivery, $stockDeliveryItem);

        $data = Validator::make($request->all(), array_filter([
            'quantity'                => $withQuantity ? ['required', 'numeric', 'gt:0', 'max:1000000'] : null,
            'location_org_stock_id'   => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:location_id', 'prohibits:location_id'],
            'location_id'             => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:location_org_stock_id'],
            'set_as_picking_location' => ['sometimes', 'boolean'],
            'client_request_id'       => ['required', 'uuid'],
        ]))->validate();

        $cacheKey = self::mobileWriteKey($user->id, $data['client_request_id']);
        if (!Cache::add($cacheKey, $stockDeliveryItem->id, now()->addDay())) {
            return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'nothing_changed', __('Already placed.'));
        }

        try {
            $message = $this->place($warehouse, $stockDeliveryItem, $data, $withQuantity ? (float) $data['quantity'] : null);
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', $message);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function place(Warehouse $warehouse, StockDeliveryItem $stockDeliveryItem, array $data, ?float $quantity): string
    {
        $this->ensureItemNotCancelled($stockDeliveryItem);
        $this->ensureStockDeliveryInGoodsIn($stockDeliveryItem->stockDelivery);

        if (!empty($data['location_org_stock_id'])) {
            $location  = $this->locationLinkOfSko((int) $data['location_org_stock_id'], $stockDeliveryItem->org_stock_id, $warehouse)->location;
            $modelData = ['location_org_stock_id' => (int) $data['location_org_stock_id']];
        } else {
            $location  = $this->ensureLocationTakesStock(Location::find($data['location_id']), $warehouse, 'location_id');
            $modelData = ['location_id' => $location->id];
        }

        $setAsPickingLocation = !empty($data['set_as_picking_location'])
            && !$this->hasPickingLocation($this->skoLocationsInWarehouse($warehouse, [$stockDeliveryItem->org_stock_id])->get($stockDeliveryItem->org_stock_id, collect()));
        if ($setAsPickingLocation) {
            $modelData['set_as_picking_location'] = true;
        }

        $packedIn  = (int) $stockDeliveryItem->orgStock?->packed_in;
        $remaining = max(0, (float) $stockDeliveryItem->unit_quantity_checked - (float) $stockDeliveryItem->unit_quantity_placed) / $stockDeliveryItem->unitsPerSko();

        if ($quantity === null) {
            if ($remaining < 0.00005) {
                throw ValidationException::withMessages(['quantity' => __('Nothing is left to place on this item.')]);
            }

            SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, $modelData);
            $quantity = $remaining;
        } else {
            UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => $quantity, ...$modelData]);
        }

        $placed = __('Placed :quantity in :location.', ['quantity' => $this->skoLabel(round($quantity, 4), $packedIn), 'location' => $location->code]);

        return $setAsPickingLocation ? $placed.' '.__(':location is now its picking location.', ['location' => $location->code]) : $placed;
    }
}
