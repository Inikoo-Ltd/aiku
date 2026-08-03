<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceOrgNetAmount
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:invoice_org_net_amount {--from= : Scope invoices dated on/after this (Y-m-d); omit to fix all}';

    public function handle(Invoice $invoice): void
    {
        $invoice->update([
            'org_net_amount' => $invoice->net_amount * $invoice->org_exchange,
        ]);
    }

    public function asCommand(Command $command): void
    {
        $from = $command->option('from');

        $query = Invoice::query()
            ->whereNotNull('org_exchange')
            ->whereRaw('abs(org_net_amount - (net_amount * org_exchange)) > 0.01');

        if ($from) {
            $command->info("Scope: invoices dated on/after {$from}.");
            $query->where('date', '>=', $from.' 00:00:00');
        } else {
            $command->info('Scope: ALL invoices.');
        }

        $total = $query->count();
        if ($total === 0) {
            $command->info('No mismatched invoices found.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunkById(500, function ($invoices) use ($bar) {
            foreach ($invoices as $invoice) {
                $this->handle($invoice);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info("Repaired {$total} invoices: org_net_amount = net_amount * org_exchange.");
    }
}
