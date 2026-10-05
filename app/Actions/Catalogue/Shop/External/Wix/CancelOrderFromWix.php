<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;

class CancelOrderFromWix extends OrgAction
{
    public function handle(Order $order): void
    {
        if (in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            return;
        }

        foreach ($order->invoices as $invoice) {
            DeleteInvoice::run($invoice, [
                'deleted_note' => 'Cancelled by Wix'
            ]);
        }

        foreach ($order->deliveryNotes as $deliveryNote) {
            if ($deliveryNote->state != DeliveryNoteStateEnum::CANCELLED) {
                CancelDeliveryNote::run($deliveryNote, null, false);
            }
        }

        CancelOrder::run($order);
    }
}
