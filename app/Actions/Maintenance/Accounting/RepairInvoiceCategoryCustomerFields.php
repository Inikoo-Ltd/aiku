<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\CategoriseInvoice;
use App\Actions\Accounting\InvoiceCategory\RedoInvoiceCategoryTimeSeries;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RepairInvoiceCategoryCustomerFields
{
    use WithActionUpdate;

    /**
     * @return array<int, int> the invoice category ids whose totals became stale
     */
    protected function handle(Invoice $invoice): array
    {
        $customer = $invoice->customer;

        $modelData = [
            'is_vip'             => $customer->is_vip,
            'as_organisation_id' => $customer->as_organisation_id,
            'as_employee_id'     => $customer->as_employee_id,
        ];

        $invoice->updateQuietly($modelData);
        $invoice->order?->updateQuietly($modelData);

        $oldInvoiceCategoryId = $invoice->invoice_category_id;
        $invoice              = CategoriseInvoice::run($invoice);

        if ($invoice->invoice_category_id == $oldInvoiceCategoryId) {
            return [];
        }

        return array_filter([$oldInvoiceCategoryId, $invoice->invoice_category_id]);
    }

    public string $commandSignature = 'repair:invoice_category_customer_fields {--S|shop= : Shop slug} {--from= : Only invoices dated on or after this date (Y-m-d)}';

    public function asCommand(Command $command): int
    {
        $query = Invoice::whereNull('source_id')
            ->whereHas('customer', function (Builder $query) {
                $query->where('is_vip', true)
                    ->orWhereNotNull('as_organisation_id')
                    ->orWhereNotNull('as_employee_id');
            });

        if ($command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();
            if (!$shop) {
                $command->error('Shop '.$command->option('shop').' not found');

                return 1;
            }
            $query->where('shop_id', $shop->id);
        }

        if ($command->option('from')) {
            $query->where('date', '>=', $command->option('from'));
        }

        $count = $query->count();
        $command->info("Invoices to check: $count");

        $staleCategoryRanges = [];

        $query->with(['customer', 'order', 'invoiceCategory'])
            ->chunkById(1000, function (Collection $invoices) use (&$staleCategoryRanges, $command) {
                foreach ($invoices as $invoice) {
                    $oldInvoiceCategory = $invoice->invoiceCategory;
                    $staleCategoryIds   = $this->handle($invoice);

                    if (!$staleCategoryIds) {
                        continue;
                    }

                    $command->info("Invoice: $invoice->id $invoice->reference Category Changed:   ".$oldInvoiceCategory?->slug."     -> ".$invoice->invoiceCategory?->slug);

                    $date = $invoice->date?->toDateString();
                    if (!$date) {
                        continue;
                    }

                    foreach ($staleCategoryIds as $invoiceCategoryId) {
                        $staleCategoryRanges[$invoiceCategoryId]['from'] = min($staleCategoryRanges[$invoiceCategoryId]['from'] ?? $date, $date);
                        $staleCategoryRanges[$invoiceCategoryId]['to']   = max($staleCategoryRanges[$invoiceCategoryId]['to'] ?? $date, $date);
                    }
                }
            });

        foreach ($staleCategoryRanges as $invoiceCategoryId => $range) {
            $command->info("Redoing time series for invoice category $invoiceCategoryId from ".$range['from'].' to '.$range['to']);
            RedoInvoiceCategoryTimeSeries::run($invoiceCategoryId, $range['from'], $range['to']);
        }

        return 0;
    }
}
