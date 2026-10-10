<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncPartnerStockDeliveryOnDispatch
{
    use AsAction;

    public function handle(DeliveryNote $deliveryNote): ?StockDelivery
    {
        $stockDelivery = StockDelivery::where('delivery_note_id', $deliveryNote->id)->first()
            ?? StorePartnerStockDeliveryFromDeliveryNote::run($deliveryNote);
        if (!$stockDelivery) {
            return null;
        }

        if (!$stockDelivery->isManagedByPartner()) {
            return $stockDelivery;
        }

        $order   = $deliveryNote->orders()->first();
        $invoice = $order?->invoices()->where('type', InvoiceTypeEnum::INVOICE)->latest('id')->first();

        $stockDelivery->update([
            'state'         => StockDeliveryStateEnum::DISPATCHED,
            'dispatched_at' => $deliveryNote->dispatched_at ?? now(),
            'invoice_id'    => $invoice?->id,
        ]);

        $deliveryNoteItemsByStock = $deliveryNote->deliveryNoteItems()
            ->with('orgStock')
            ->get()
            ->groupBy(fn ($item) => $item->orgStock?->stock_id);

        $dispatchedByStock = $deliveryNoteItemsByStock
            ->map(fn ($items) => (float) $items->sum(fn ($item) => (float) $item->quantity_dispatched * (float) ($item->orgStock?->packed_in ?: 1)));

        foreach ($stockDelivery->items as $item) {
            $item->update([
                'state'         => StockDeliveryItemStateEnum::DISPATCHED,
                'unit_quantity' => $dispatchedByStock->get($item->stock_id, $item->unit_quantity),
            ]);

            if ($item->org_stock_id) {
                $this->copySellerBatches($item, $deliveryNoteItemsByStock->get($item->stock_id, collect()));
            }
        }

        return $stockDelivery;
    }

    /**
     * The batches the seller picked arrive on the buyer's goods in already filled in: same code and
     * best-before, as the buyer's own batch of its SKO, in the buyer's SKOs.
     *
     * @param  Collection<int, DeliveryNoteItem>  $deliveryNoteItems
     */
    private function copySellerBatches(StockDeliveryItem $item, Collection $deliveryNoteItems): void
    {
        if ($item->batches()->exists()) {
            return;
        }

        $quantities = [];
        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $sellerUnitsPerSko = (float) ($deliveryNoteItem->orgStock?->packed_in ?: 1);
            foreach ($deliveryNoteItem->pickedBatches() as $picked) {
                $sellerBatch = BatchCode::find($picked['batch_code_id']);
                $buyerBatch  = StoreBatchCode::make()->inOrganisation($item->organisation, [
                    'code'         => $sellerBatch->code,
                    'expiry_date'  => $sellerBatch->expiry_date?->toDateString(),
                    'org_stock_id' => $item->org_stock_id,
                ]);
                $quantities[$buyerBatch->id] = ($quantities[$buyerBatch->id] ?? 0) + $picked['quantity'] * $sellerUnitsPerSko / $item->unitsPerSko();
            }
        }

        foreach ($quantities as $batchCodeId => $quantity) {
            $item->batches()->create([
                'group_id'        => $item->group_id,
                'organisation_id' => $item->organisation_id,
                'batch_code_id'   => $batchCodeId,
                'quantity'        => round($quantity, 6),
            ]);
        }
    }
}
