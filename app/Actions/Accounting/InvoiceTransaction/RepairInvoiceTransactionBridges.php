<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 14:32:28 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

trait RepairInvoiceTransactionBridges
{
    private const int CHUNK_SIZE = 10000;

    public function handle(Command $command): void
    {
        $query = DB::table('invoice_transactions')
            ->select('id', 'model_id', 'model_type', 'deleted_at')->orderBy('id', 'desc');

        $total = (clone $query)->count('id');

        if ($total === 0) {
            $command->info('No invoice transactions to repair.');
            return;
        }

        $command->line("Found $total invoice transactions to process.");

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query->chunkById(self::CHUNK_SIZE, function ($invoiceTransactions) use ($bar) {
            foreach ($invoiceTransactions as $invoiceTransaction) {

                if ($invoiceTransaction->deleted_at == null && $invoiceTransaction->model_type == 'Product'
                && $invoiceTransaction->model_id != null
                ) {
                    /** @var class-string $jobClass */
                    $jobClass = $this->getJobClass();
                    $jobClass::dispatch($invoiceTransaction->id)->onQueue('sales_slave_historic');
                }

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

    /**
     * @return class-string
     */
    abstract protected function getJobClass(): string;
}
