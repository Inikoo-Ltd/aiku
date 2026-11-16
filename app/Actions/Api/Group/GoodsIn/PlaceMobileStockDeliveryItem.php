<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PlaceMobileStockDeliveryItem
{
    use AsController;
    use WithMobileStockDeliveryPlacing;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, StockDeliveryItem $stockDeliveryItem, ActionRequest $request): JsonResponse
    {
        return $this->placeFromRequest($warehouse, $stockDelivery, $stockDeliveryItem, $request, withQuantity: true);
    }
}
