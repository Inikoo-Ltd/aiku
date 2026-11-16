<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * An order line that reaches an order already in the warehouse has to be put on its delivery
 * note, or the order ships and is invoiced with a line nobody was ever asked to pick.
 */
trait WithDeliveryNoteLineCreation
{
    use WithDeliveryNoteQuantitySync;

    public function createDeliveryNoteItem(Order $order, Transaction $transaction): void
    {
        DB::transaction(function () use ($order, $transaction) {
            $deliveryNote = $this->lockWorkableDeliveryNote($order);
            if (!$deliveryNote) {
                return;
            }

            $transaction->update([
                'state'           => TransactionStateEnum::IN_WAREHOUSE,
                'status'          => TransactionStatusEnum::PROCESSING,
                'in_warehouse_at' => now()
            ]);

            $product = Product::find($transaction->model_id);


            foreach ($product->orgStocks as $orgStock) {
                $quantity             = DeliveryNoteItem::requiredQuantity((float) $orgStock->pivot->quantity, (float) $transaction->quantity_ordered + (float) $transaction->quantity_bonus);
                $deliveryNoteItemData = [
                    'org_stock_id'               => $orgStock->id,
                    'transaction_id'             => $transaction->id,
                    'quantity_required'          => $quantity,
                    'original_quantity_required' => $quantity,
                ];

                StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteItemData);
            }

            /*
             * A line the warehouse has never seen cannot be picked from a note that is already
             * packed, so the note goes back far enough for the new line to be worked.
             */
            $this->walkDeliveryNoteBackToPicking($deliveryNote->refresh(), true, null);
        });
    }

    /**
     * Walking a note back deletes packings and moves the order's state, and this runs unattended
     * over every Faire order, so the note is locked for the duration and the whole walk commits or
     * none of it does. Without it a throw further down the poll leaves a half unpacked note.
     */
    protected function lockWorkableDeliveryNote(Order $order): ?DeliveryNote
    {
        return $order->deliveryNotes()->whereNotIn('state', [
            DeliveryNoteStateEnum::CANCELLED,
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED,
        ])->where('type', DeliveryNoteTypeEnum::ORDER)->lockForUpdate()->first();
    }
}
