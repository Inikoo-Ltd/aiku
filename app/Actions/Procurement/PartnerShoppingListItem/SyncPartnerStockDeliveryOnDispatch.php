<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncPartnerStockDeliveryOnDispatch
{
    use AsAction;

    public function handle(DeliveryNote $deliveryNote): ?StockDelivery
    {
        $stockDelivery = StockDelivery::where('delivery_note_id', $deliveryNote->id)->first();
        if (!$stockDelivery) {
            return null;
        }

        $order   = $deliveryNote->orders()->first();
        $invoice = $order?->invoices()->latest('id')->first();

        $stockDelivery->update([
            'state'         => StockDeliveryStateEnum::DISPATCHED,
            'dispatched_at' => $deliveryNote->dispatched_at ?? now(),
            'invoice_id'    => $invoice?->id,
        ]);

        $dispatchedByStock = $deliveryNote->deliveryNoteItems()
            ->with('orgStock')
            ->get()
            ->groupBy(fn ($item) => $item->orgStock?->stock_id)
            ->map(fn ($items) => (float) $items->sum('quantity_dispatched'));

        foreach ($stockDelivery->items as $item) {
            $item->update([
                'state'         => StockDeliveryItemStateEnum::DISPATCHED,
                'unit_quantity' => $dispatchedByStock->get($item->stock_id, $item->unit_quantity),
            ]);
        }

        return $stockDelivery;
    }
}
