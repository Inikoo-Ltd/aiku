<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:56:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Accounting;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;

class ReportWrongInvoiceTransactionDates
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $numberTransactionsWrongDate = DB::table('invoice_transactions')
            ->leftJoin('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            //   ->whereNotNull('invoices.date')->whereNotNull('invoice_transactions.date')
            ->whereRaw('invoice_transactions.date::date != invoices.date::date')->count();

        $numberTransactions = DB::table('invoice_transactions')->count();


        $percentage                  = round(100 * $numberTransactionsWrongDate / $numberTransactions, 3);
        $numberTransactionsWrongDate = number_format($numberTransactionsWrongDate);
        $numberTransactions          = number_format($numberTransactions);

        print "Number of transactions with wrong date: ".$numberTransactionsWrongDate." of ".$numberTransactions." (".$percentage."%)  \n";
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:report_wrong_invoice_transaction_dates';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
