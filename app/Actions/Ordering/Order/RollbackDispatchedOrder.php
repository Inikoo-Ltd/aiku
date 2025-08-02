<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class RollbackDispatchedOrder extends OrgAction
{
    public function handle(Order $order): void
    {
        if ($order->deliveryNotes) {
            foreach ($order->deliveryNotes as $deliveryNote) {
                if ($deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED) {
                    UpdateDeliveryNote::make()->action($deliveryNote, [
                        'state' => DeliveryNoteStateEnum::FINALISED,
                        'dispatched_at' => null,
                    ]);

                    foreach ($deliveryNote->deliveryNoteItems as $item) {
                        if ($item->state == DeliveryNoteItemStateEnum::DISPATCHED) {
                            UpdateDeliveryNoteItem::make()->action($item, [
                                'state' => DeliveryNoteItemStateEnum::FINALISED,
                                'dispatched_at' => null,
                            ]);
                        }
                    }
                }
            }
        }

        if ($order->state == OrderStateEnum::DISPATCHED) {
            $order = UpdateOrder::make()->action($order, [
                'state' => OrderStateEnum::FINALISED,
                'dispatched_at' => null
            ]);
            foreach ($order->transactions as $transaction) {
                if ($transaction->state == TransactionStateEnum::DISPATCHED) {
                    UpdateTransaction::make()->action($transaction, [
                        'state' => TransactionStateEnum::FINALISED,
                    ]);
                }
            }
        }
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order);
    }
}
