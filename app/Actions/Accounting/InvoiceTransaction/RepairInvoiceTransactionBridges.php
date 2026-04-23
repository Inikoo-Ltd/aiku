<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 14:32:28 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Console\Command;

trait RepairInvoiceTransactionBridges
{
    private const int CHUNK_SIZE = 2000;

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
                $this->getJobClass()::run($invoiceTransaction->id);
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

    abstract protected function getJobClass(): string;
}
