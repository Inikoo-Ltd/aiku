<?php

/*
 * Author Louis Perez
 * Created on 24-07-2026-15h-49m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDeliveryNoteItemsRequiredPickQuantity
{
    use AsAction;

    public function handle(OrgStock $orgStock)
    {
        foreach (
            $orgStock->deliveryNoteItem()->with(['deliveryNote', 'transaction'])->whereIn('state', [
                DeliveryNoteItemStateEnum::QUEUED,
                DeliveryNoteItemStateEnum::UNASSIGNED,
                DeliveryNoteItemStateEnum::HANDLING,
            ])->get() as $deliveryNoteItem
        ) {

            $transaction          = $deliveryNoteItem->transaction;
            $productQty           = DB::table('product_has_org_stocks')->where('product_id', $transaction->model_id)->where('org_stock_id', $orgStock->id)->first()?->quantity;
            $quantity             = $productQty * $transaction->quantity_ordered;

            DeleteDeliveryNoteItem::run($deliveryNoteItem);

            StoreDeliveryNoteItem::make()->action($deliveryNoteItem->deliveryNote, [
                "org_stock_id" => $orgStock->id,
                "transaction_id" => $deliveryNoteItem->transaction_id,
                "quantity_required" => $quantity,
                "original_quantity_required" => $quantity,
            ]);

        }
    }
}
