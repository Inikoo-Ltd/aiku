<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 16:49:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\RecurringBill;

class InvoiceRecurringBillTransactions extends OrgAction
{
    public function handle(Invoice $invoice, RecurringBill $recurringBill): Invoice
    {
        $transactions = $recurringBill->transactions;

        foreach ($transactions as $transaction) {
            $data = [
                'tax_category_id' => $transaction->recurringBill->tax_category_id,
                'quantity'        => $transaction->quantity * $transaction->temporal_quantity,
                'gross_amount'    => $transaction->gross_amount,
                'net_amount'      => $transaction->net_amount,
                'data'            => $transaction->data
            ];

            StoreInvoiceTransaction::make()->action($invoice, $transaction->historicAsset, $data);
        }

        return $invoice;
    }

}
