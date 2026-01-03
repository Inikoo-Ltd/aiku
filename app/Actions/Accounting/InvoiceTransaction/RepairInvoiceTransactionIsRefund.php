<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 17:30:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionIsRefund
{
    use AsAction;

    public string $commandSignature = 'accounting:repair-invoice-transaction-is-refund';
    public string $commandDescription = 'Set is_refund to true for transactions where parent invoice is type refund';

    public function handle(?Command $command = null): void
    {
        $query = InvoiceTransaction::whereHas('invoice', function ($query) {
            $query->where('type', InvoiceTypeEnum::REFUND);
        });

        $total = $query->count();

        if ($total === 0) {
            $command?->info('No invoice transactions to repair.');

            return;
        }

        $bar = $command?->getOutput()->createProgressBar($total);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar?->start();

        $query->chunkById(500, function ($invoiceTransactions) use ($bar) {
            foreach ($invoiceTransactions as $invoiceTransaction) {
                /** @var InvoiceTransaction $invoiceTransaction */
                $invoiceTransaction->update([
                    'is_refund' => true,
                ]);

                $bar?->advance();
            }
        });

        $bar?->finish();
        $command?->newLine();
        $command?->info('Invoice transactions repair completed.');
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
