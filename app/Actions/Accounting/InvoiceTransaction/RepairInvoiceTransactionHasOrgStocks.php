<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasOrgStock;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionHasOrgStocks
{
    use AsAction;

    public string $commandSignature = 'accounting:repair-invoice-transaction-has-org-stocks';
    public string $commandDescription = 'Populate invoice_transaction_has_org_stocks from existing invoice_transactions';

    public function handle(Command $command): void
    {
        $query = InvoiceTransaction::where('model_type', 'Product')
            ->whereNotNull('model_id')
            ->whereNull('deleted_at');

        $total = $query->count();

        if ($total === 0) {
            $command->info('No invoice transactions to repair.');

            return;
        }

        $command->line("Found {$total} invoice transactions to process.");

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $created = 0;
        $skipped = 0;

        $query->chunkById(500, function ($invoiceTransactions) use ($bar, &$created, &$skipped) {
            foreach ($invoiceTransactions as $invoiceTransaction) {
                /** @var InvoiceTransaction $invoiceTransaction */
                $product = Product::find($invoiceTransaction->model_id);

                if (!$product) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $orgStocks = $product->orgStocks;

                if ($orgStocks->isEmpty()) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                foreach ($orgStocks as $orgStock) {
                    InvoiceTransactionHasOrgStock::updateOrCreate(
                        [
                            'invoice_transaction_id' => $invoiceTransaction->id,
                            'org_stock_id'           => $orgStock->id,
                        ],
                        [
                            'group_id'            => $invoiceTransaction->group_id,
                            'organisation_id'     => $invoiceTransaction->organisation_id,
                            'org_stock_family_id' => $orgStock->org_stock_family_id,
                            'customer_id'         => $invoiceTransaction->customer_id,
                            'order_id'            => $invoiceTransaction->order_id,
                            'net_amount'          => $invoiceTransaction->net_amount,
                            'org_net_amount'      => $invoiceTransaction->org_net_amount,
                            'grp_net_amount'      => $invoiceTransaction->grp_net_amount,
                            'type'                => $invoiceTransaction->model_type,
                            'in_process'          => $invoiceTransaction->in_process ?? false,
                            'is_refund'           => $invoiceTransaction->is_refund ?? false,
                            'date'                => $invoiceTransaction->date,
                        ]
                    );

                    $created++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info("Repair completed. Created/updated: {$created} records, skipped: {$skipped} transactions.");
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
