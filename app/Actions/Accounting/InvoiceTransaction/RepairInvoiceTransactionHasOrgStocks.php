<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionHasOrgStocks
{
    use AsAction;
    use RepairInvoiceTransactionBridges;

    public string $commandSignature = 'accounting:repair-invoice-transaction-has-org-stocks';
    public string $commandDescription = 'Populate invoice_transaction_has_org_stocks from existing invoice_transactions';

    protected function getJobClass(): string
    {
        return SyncInvoiceTransactionOrgStockBridges::class;
    }
}
