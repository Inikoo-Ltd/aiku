<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Mar 2025 13:06:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice;
use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ForceDeleteInvoice
{
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'invoice:force_delete {id : The ID of the invoice to delete}';
    }

    public function getCommandDescription(): string
    {
        return "Force Delete (from database) an invoice and its associated transactions";
    }


    public function handle(Invoice $invoice, ?Command $command = null): int
    {
        EnsureInvoiceNotSentAsEInvoice::run($invoice);

        try {
            DB::transaction(function () use ($invoice, $command) {
                // Delete associated transactions first
                $transactionCount = $invoice->invoiceTransactions()->withTrashed()->count();
                $invoice->invoiceTransactions()->withTrashed()->forceDelete();

                // Delete the invoice
                $invoice->forceDelete();

                if ($command) {
                    $command->info("Deleted $transactionCount invoice transactions.");
                    $command->info("Invoice $invoice->reference (ID: $invoice->id) deleted successfully.");
                }
            });

            return 0;
        } catch (Exception|Throwable $e) {
            $command?->error("Error deleting invoice: {$e->getMessage()}");

            return 1;
        }
    }


    public function asCommand(Command $command): int
    {
        $invoiceId = $command->argument('id');

        $invoice = Invoice::withTrashed()->find($invoiceId);

        if (!$invoice) {
            $command->error("Invoice with ID $invoiceId not found.");

            return 1;
        }

        $command->info("Found invoice: $invoice->reference");

        if (!$command->confirm('Are you sure you want to delete this invoice and all its transactions?')) {
            $command->info('Operation cancelled.');

            return 0;
        }

        return $this->handle($invoice, $command);
    }

}
