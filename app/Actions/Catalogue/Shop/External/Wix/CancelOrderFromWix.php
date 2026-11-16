<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\OrgAction;
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


        CancelOrder::run($order);
    }
}
