<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-38m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\AddItemNotOnPurchaseOrderToStockDelivery;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AddMobileStockDeliveryItem
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);

        $data = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'min:1', 'max:2147483647'],
            'sko_quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ])->validate();

        $field = AddItemNotOnPurchaseOrderToStockDelivery::isPartnerDelivery($stockDelivery) ? 'org_stock_id' : 'org_supplier_product_id';
        $item  = AddItemNotOnPurchaseOrderToStockDelivery::make()->action($stockDelivery, $user, [
            $field         => (int) $data['item_id'],
            'sko_quantity' => $data['sko_quantity'],
        ]);

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', __(':code added as an extra item.', ['code' => $item->orgStock?->code ?? '']));
    }
}
