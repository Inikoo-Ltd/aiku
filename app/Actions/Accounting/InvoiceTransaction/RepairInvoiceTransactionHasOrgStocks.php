<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionHasOrgStocks
{
    use AsAction;

    private const int CHUNK_SIZE = 2000;

    public string $commandSignature = 'accounting:repair-invoice-transaction-has-org-stocks';
    public string $commandDescription = 'Populate invoice_transaction_has_org_stocks from existing invoice_transactions';

    public function handle(Command $command): void
    {
        $query = InvoiceTransaction::query()
            ->select('id')
            ->where('model_type', 'Product')
            ->whereNotNull('model_id')
            ->whereNull('deleted_at');

        $total = (clone $query)->count('id');

        if ($total === 0) {
            $command->info('No invoice transactions to repair.');

            return;
        }

        $command->line("Found {$total} invoice transactions to process.");

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query->chunkById(self::CHUNK_SIZE, function ($invoiceTransactions) use ($bar) {
            foreach ($invoiceTransactions as $invoiceTransaction) {
                SyncInvoiceTransactionOrgStockBridges::run($invoiceTransaction->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('Repair completed.');
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
