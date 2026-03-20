<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionHasStocks
{
    use AsAction;
    use RepairInvoiceTransactionBridges;

    public string $commandSignature = 'accounting:repair-invoice-transaction-has-stocks';
    public string $commandDescription = 'Populate invoice_transaction_has_stocks from existing invoice_transactions';

    protected function getJobClass(): string
    {
        return SyncInvoiceTransactionStockBridges::class;
    }
}
