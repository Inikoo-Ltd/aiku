<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteLineCreation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A marketplace product can be ordered before anybody has said which stock it is picked from.
 * Its line then has nothing to put on the delivery note, and the order was packed, invoiced and
 * dispatched without it while the stock never moved (HELP-4696). The moment the product gets its
 * stock, every line of it still in the warehouse without a delivery note item gets one, and a
 * note already picked or packed goes back to picking.
 */
class StoreMissingDeliveryNoteItemsOfProduct
{
    use AsAction;
    use WithDeliveryNoteLineCreation;

    public int $hydratorsDelay = 0;

    public const array ORDER_STATES_IN_WAREHOUSE = [
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    public function handle(Product $product): int
    {
        if (!$product->orgStocks()->exists()) {
            return 0;
        }

        $transactions = Transaction::where('model_type', 'Product')
            ->where('model_id', $product->id)
            ->where('state', '!=', TransactionStateEnum::CANCELLED)
            ->whereRaw('quantity_ordered + coalesce(quantity_bonus, 0) > 0')
            ->whereDoesntHave('deliveryNoteItems')
            ->whereHas('order', fn ($query) => $query->whereIn('state', self::ORDER_STATES_IN_WAREHOUSE))
            ->with('order')
            ->get();

        $linesAdded = 0;
        foreach ($transactions as $transaction) {
            $this->createDeliveryNoteItem($transaction->order, $transaction);
            if ($transaction->deliveryNoteItems()->exists()) {
                $linesAdded++;
            }
        }

        return $linesAdded;
    }
}
