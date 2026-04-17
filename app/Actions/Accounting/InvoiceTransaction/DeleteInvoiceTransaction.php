<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 22:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasOrgStock;
use App\Models\Accounting\InvoiceTransactionHasStock;
use App\Models\Accounting\InvoiceTransactionHasTradeUnit;

class DeleteInvoiceTransaction extends OrgAction
{
    public function handle(InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {

        InvoiceTransactionHasTradeUnit::where('invoice_transaction_id', $invoiceTransaction->id)->delete();
        InvoiceTransactionHasOrgStock::where('invoice_transaction_id', $invoiceTransaction->id)->delete();
        InvoiceTransactionHasStock::where('invoice_transaction_id', $invoiceTransaction->id)->delete();

        $invoiceTransaction->delete();

        ProcessInvoiceTransactionTimeSeries::run($invoiceTransaction);

        return $invoiceTransaction;
    }

    public function action(InvoiceTransaction $invoiceTransaction, int $hydratorsDelay = 1800): InvoiceTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoiceTransaction->shop, []);

        return $this->handle($invoiceTransaction);
    }

}
