<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Apr 2026 23:46:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting\InvoiceTransaction;

use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsCommand;

class RepairInvoiceTransactionMarketplaceId
{
    use AsCommand;

    public string $commandSignature = 'repair:invoice_transaction_marketplace_id';

    public function asCommand(Command $command): void
    {
        $count = Transaction::whereNotNull('marketplace_id')->count();

        $progressBar = $command->getOutput()->createProgressBar($count);
        $progressBar->start();

        Transaction::whereNotNull('marketplace_id')->chunk(1000, function ($transactions) use ($progressBar) {
            /** @var Transaction $transaction */
            foreach ($transactions as $transaction) {
                if ($transaction->invoiceTransaction) {
                    $transaction->invoiceTransaction->updateQuietly([
                        'marketplace_id' => $transaction->marketplace_id,
                    ]);
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
    }

}
