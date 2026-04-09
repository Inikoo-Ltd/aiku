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
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
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
        if ($this->strict) {
            OrderHydrateCategoriesData::run($order);
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
        }


        return $transaction;
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
