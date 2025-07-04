<?php
/*
 * author Arya Permana - Kirin
 * created on 04-07-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromAdjustment;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromCharge;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromShipping;
use App\Actions\Dropshipping\Ebay\Orders\FulfillOrderToEbay;
use App\Actions\Dropshipping\Magento\Orders\FulfillOrderToMagento;
use App\Actions\Dropshipping\Shopify\Fulfilment\FulfillOrderToShopify;
use App\Actions\Dropshipping\WooCommerce\Orders\FulfillOrderToWooCommerce;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DispatchOrderFromDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) { 
            GenerateOrderInvoice::make()->action($order);
            $order->refresh();
            $order = UpdateStateToDispatchedOrder::make()->action($order);
            
            return $order;
        });

        return $order;
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
