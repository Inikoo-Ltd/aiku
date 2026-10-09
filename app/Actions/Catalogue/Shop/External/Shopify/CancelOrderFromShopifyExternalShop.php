<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;

class CancelOrderFromShopifyExternalShop extends OrgAction
{
    public function handle(Order $order): bool
    {
        if (in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            return false;
        }

        if ($order->deliveryNotes()->whereIn('state', [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::FINALISED])->exists()) {
            return false;
        }

        foreach ($order->invoices as $invoice) {
            DeleteInvoice::run($invoice, [
                'deleted_note' => 'Cancelled in Shopify'
            ]);
        }

        foreach ($order->deliveryNotes as $deliveryNote) {
            if ($deliveryNote->state != DeliveryNoteStateEnum::CANCELLED) {
                CancelDeliveryNote::run($deliveryNote, null, false);
            }
        }

        CancelOrder::run($order);

        return true;
    }
}
