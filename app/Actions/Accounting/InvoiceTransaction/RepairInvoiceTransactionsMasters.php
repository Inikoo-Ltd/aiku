<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 00:29:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionsMasters
{
    use AsAction;

    public string $commandSignature = 'accounting:repair-invoice-transactions-masters';
    public string $commandDescription = 'Repair invoice transactions master IDs from asset_id';

    public function handle(Command $command): void
    {
        $query = InvoiceTransaction::whereNotNull('asset_id');

        $total = $query->count();

        if ($total === 0) {
            $command->info('No invoice transactions to repair.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query->chunkById(500, function ($invoiceTransactions) use ($bar) {
            foreach ($invoiceTransactions as $invoiceTransaction) {
                /** @var InvoiceTransaction $invoiceTransaction */
                $product = $invoiceTransaction->asset?->product;

                if ($product && $masterProduct = $product->masterProduct) {
                    $invoiceTransaction->update([
                        'master_shop_id'           => $masterProduct->master_shop_id,
                        'master_department_id'     => $masterProduct->master_department_id,
                        'master_sub_department_id' => $masterProduct->master_sub_department_id,
                        'master_family_id'         => $masterProduct->master_family_id,
                    ]);
                }

                $bar?->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('Invoice transactions repair completed.');
    }

    public function asCommand(Command $command): void
    {

        $this->handle($command);
    }
}
