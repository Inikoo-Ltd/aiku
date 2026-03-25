<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionHasTradeUnits
{
    use AsAction;
    use RepairInvoiceTransactionBridges;

    public string $commandSignature = 'accounting:repair-invoice-transaction-has-trade-units';

    public string $commandDescription = 'Populate invoice_transaction_has_trade_units from existing invoice_transactions';

    protected function getJobClass(): string
    {
        return SyncInvoiceTransactionTradeUnitBridges::class;
    }
}
