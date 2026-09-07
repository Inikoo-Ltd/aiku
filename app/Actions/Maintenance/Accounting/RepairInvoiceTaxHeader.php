<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 13:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Traits\WithLineTaxCategories;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Invoices whose stored net/tax do not add up to their own lines (HELP-3081). Lists them by
 * default; --fix rewrites the header from the lines and recalculates the order from its
 * transactions. An issued invoice already on a filed VAT return is not to be rewritten: scope
 * with --from to the open period and leave the rest to accounts. External (Faire) invoices
 * carry the marketplace's tax and are never touched. Every --fix run leaves a before/after csv
 * per invoice and order under storage/app/repairs, on top of the invoice audit trail.
 */
class RepairInvoiceTaxHeader
{
    use AsAction;
    use WithLineTaxCategories;

    public string $commandSignature = 'repair:invoice_tax_header {--from= : Only invoices dated on/after this (Y-m-d)} {--shop= : Shop slug} {--min-tax-delta=0 : Skip invoices whose tax differs from the lines by less than this (penny rounding drifts are a different problem)} {--fix : Rewrite the headers, otherwise only report}';

    /**
     * @return array{net: float, tax: float, total: float}
     */
    public function expectedTotals(Invoice $invoice): array
    {
        $breakdown = $this->getInvoiceTaxBreakdown($invoice);
        $net       = round(array_sum(array_column($breakdown, 'net_amount')), 2);
        $tax       = round(array_sum(array_column($breakdown, 'tax_amount')), 2);

        return ['net' => $net, 'tax' => $tax, 'total' => round($net + $tax, 2)];
    }

    public function handle(Invoice $invoice): void
    {
        CalculateInvoiceTotals::make()->action($invoice);

        if ($invoice->order) {
            CalculateOrderTotalAmounts::run($invoice->order);
        }
    }

    /**
     * One grouped query over the lines finds the mismatches; only those invoices are loaded.
     *
     * @return array<int, int>
     */
    public function mismatchedInvoiceIds(?string $from, ?string $shopSlug): array
    {
        $lines = DB::table('invoice_transactions as it')
            ->join('tax_categories as tc', 'tc.id', 'it.tax_category_id')
            ->join('invoices as i', 'i.id', 'it.invoice_id')
            ->join('shops as s', 's.id', 'i.shop_id')
            ->whereNull('it.deleted_at')
            ->whereNull('i.deleted_at')
            ->where('i.type', InvoiceTypeEnum::INVOICE->value)
            ->where('i.is_tax_only', false)
            ->where('i.amount_off', 0)
            ->whereNot('s.type', ShopTypeEnum::EXTERNAL->value)
            ->when($from, fn ($query) => $query->where('i.date', '>=', $from.' 00:00:00'))
            ->when($shopSlug, fn ($query) => $query->where('s.slug', $shopSlug))
            ->groupBy('it.invoice_id', 'it.tax_category_id', 'tc.rate')
            ->select('it.invoice_id', DB::raw('round(sum(it.net_amount), 2) as net'), 'tc.rate');

        return DB::query()->fromSub($lines, 'l')
            ->join('invoices as i', 'i.id', 'l.invoice_id')
            ->groupBy('l.invoice_id', 'i.net_amount', 'i.tax_amount')
            ->havingRaw('abs(sum(l.net) - i.net_amount) > 0.01 or abs(sum(round(l.net * l.rate, 2)) - i.tax_amount) > 0.01')
            ->pluck('l.invoice_id')
            ->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $from = $command->option('from');
        if (!$from && $command->option('fix') && !$command->confirm('No --from given: this would rewrite invoices of every period, including filed ones. Continue?')) {
            return 1;
        }

        $ids = $this->mismatchedInvoiceIds($from, $command->option('shop'));

        $rows   = [];
        $record = [];
        Invoice::whereIn('id', $ids)->with('shop')->orderBy('id')->chunkById(200, function ($invoices) use (&$rows, &$record, $command) {
            foreach ($invoices as $invoice) {
                $expected = $this->expectedTotals($invoice);
                if (abs((float)$invoice->tax_amount - $expected['tax']) < (float)$command->option('min-tax-delta')) {
                    continue;
                }
                $rows[] = [
                    $invoice->shop->code,
                    $invoice->reference,
                    $invoice->date?->toDateString(),
                    $invoice->net_amount,
                    $invoice->tax_amount,
                    $expected['tax'],
                    number_format((float)$invoice->tax_amount - $expected['tax'], 2),
                    $invoice->total_amount,
                    $expected['total'],
                    $invoice->payment_amount,
                ];

                if ($command->option('fix')) {
                    $order  = $invoice->order;
                    $before = [$invoice->net_amount, $invoice->tax_amount, $invoice->total_amount, $order?->net_amount, $order?->tax_amount, $order?->total_amount];
                    $this->handle($invoice);
                    $invoice->refresh();
                    $order?->refresh();
                    $record[] = [
                        $invoice->shop->code,
                        $invoice->reference,
                        $order?->reference,
                        ...$before,
                        $invoice->net_amount,
                        $invoice->tax_amount,
                        $invoice->total_amount,
                        $order?->net_amount,
                        $order?->tax_amount,
                        $order?->total_amount,
                    ];
                }
            }
        });

        $command->table(['Shop', 'Invoice', 'Date', 'Net', 'Tax', 'Tax (lines)', 'Δ tax', 'Total', 'Total (lines)', 'Paid'], $rows);
        $command->info(count($rows).' invoices with a header that does not add up to its lines.');

        if (!$command->option('fix')) {
            $command->line('Dry run. Pass --fix to rewrite these headers from their lines.');

            return 0;
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['shop', 'invoice', 'order', 'invoice_net_before', 'invoice_tax_before', 'invoice_total_before', 'order_net_before', 'order_tax_before', 'order_total_before', 'invoice_net_after', 'invoice_tax_after', 'invoice_total_after', 'order_net_after', 'order_tax_after', 'order_total_after']);
        foreach ($record as $line) {
            fputcsv($csv, $line);
        }
        rewind($csv);
        $path = 'repairs/invoice_tax_header_'.now()->format('Ymd_His').'.csv';
        Storage::disk('local')->put($path, stream_get_contents($csv));
        fclose($csv);

        $command->info(count($rows).' invoices repaired. Before/after record: storage/app/'.$path);

        return 0;
    }
}
