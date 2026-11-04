<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * INI-203. The bridges and time series of every line of an invoice, as one job instead of four per
 * line queued while the packer waits. Each line runs its bridges before its time series, which read
 * them for an earlier-dated line.
 *
 * Workers read from a replica, so a line not readable yet fails the job, which is retried, as the
 * per-line time series job used to be. A line that throws does not stop the others, but the job
 * still fails afterwards so the queue retries it; bridges are upserts and the time series jobs
 * are unique, so running a line again is harmless.
 */
class ProcessStoredInvoiceTransactions
{
    use AsAction;

    public string $jobQueue = 'sales_slave';

    /**
     * @param  array<int, int>  $invoiceTransactionIds
     */
    public function handle(array $invoiceTransactionIds): void
    {
        $invoiceTransactions = InvoiceTransaction::whereIn('id', $invoiceTransactionIds)->orderBy('id')->get();

        if ($invoiceTransactions->count() !== count(array_unique($invoiceTransactionIds))) {
            throw new RuntimeException('Invoice transactions not readable yet: '.implode(',', array_diff($invoiceTransactionIds, $invoiceTransactions->modelKeys())));
        }

        $failure = null;
        foreach ($invoiceTransactions as $invoiceTransaction) {
            try {
                SyncInvoiceTransactionTradeUnitBridges::run($invoiceTransaction->id);
                SyncInvoiceTransactionOrgStockBridges::run($invoiceTransaction->id);
                SyncInvoiceTransactionStockBridges::run($invoiceTransaction->id);
                ProcessInvoiceTransactionTimeSeries::run(invoiceTransaction: $invoiceTransaction, delay: 300);
            } catch (Throwable $exception) {
                if ($failure) {
                    report($exception);
                } else {
                    $failure = $exception;
                }
            }
        }

        if ($failure) {
            throw $failure;
        }
    }
}
