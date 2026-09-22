<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:00:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction;
use App\Actions\Dispatching\DeliveryNoteItem\DeleteDeliveryNoteItem;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\LogBasketEvent;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteTransaction extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(Transaction $transaction): Transaction
    {
        $transaction = DB::transaction(function () use ($transaction) {
            foreach ($transaction->deliveryNoteItems as $deliveryNoteItem) {
                DeleteDeliveryNoteItem::run($deliveryNoteItem);
            }

            if ($transaction->invoiceTransaction) {
                DeleteInvoiceTransaction::run($transaction->invoiceTransaction);
            }

            $transaction->delete();

            return $transaction;
        });

        $order = $transaction->order;
        $order->refresh();

        $this->stopAutomaticCharges($order, $transaction);

        if ($this->strict) {
            OrderHydrateCategoriesData::run($order);
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
        }

        LogBasketEvent::run($order->fresh(), 'remove', $transaction, -(float) $transaction->quantity_ordered);


        return $transaction;
    }

    /**
     * The hanging charge is put back by CalculateOrderHangingCharges on every recalculation, so
     * removing the line only lasted until the next basket change (HELP-3229). Taking it off is
     * the decision that the order is not to carry it, and the charges engine records that.
     */
    private function stopAutomaticCharges(Order $order, Transaction $transaction): void
    {
        if ($transaction->model_type != 'Charge' || $order->charges_engine == OrderChargesEngineEnum::MANUAL) {
            return;
        }

        if (Charge::where('id', $transaction->model_id)->value('type') != ChargeTypeEnum::HANGING->value) {
            return;
        }

        $order->update(['charges_engine' => OrderChargesEngineEnum::MANUAL]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Transaction $transaction): Transaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($transaction->shop, []);

        return $this->handle($transaction);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        if ($transaction->invoiceTransaction) {
            abort(403, 'Cannot delete transaction with invoice transaction');
        }

        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction);
    }
}
