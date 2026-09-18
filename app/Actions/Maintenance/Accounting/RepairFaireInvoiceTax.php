<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Catalogue\Shop\External\Faire\UpdateFaireOrder;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Faire invoices whose tax was recalculated from Aiku's own rates at invoicing instead of the
 * tax Faire charged, so zero rated goods (UK tea) were billed at the standard rate (HELP-3192).
 * Only external shop invoices are read. Faire's figure is fetched per order, stored on the order
 * so later recalculations keep it, and the invoice and order are re-totalled. Lists by default;
 * --fix writes and leaves a before/after csv under storage/app/repairs.
 */
class RepairFaireInvoiceTax
{
    use AsAction;

    public string $commandSignature = 'repair:faire_invoice_tax {--from= : Only invoices dated on/after this (Y-m-d)} {--shop= : Shop slug} {--fix : Rewrite the tax, otherwise only report}';

    public function handle(Invoice $invoice, float $faireTaxAmount): void
    {
        $order = $invoice->order;
        $order->update(['data->marketplace_tax_amount' => $faireTaxAmount]);

        CalculateInvoiceTotals::make()->action($invoice->refresh());
        UpdateInvoicePaymentState::run($invoice->refresh());
        CalculateOrderTotalAmounts::run($order->refresh());
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $rows   = [];
        $record = [];

        Invoice::query()
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->whereNotNull('order_id')
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::EXTERNAL))
            ->when($command->option('shop'), fn ($query, $slug) => $query->whereHas('shop', fn ($shopQuery) => $shopQuery->where('slug', $slug)))
            ->when($command->option('from'), fn ($query, $from) => $query->where('date', '>=', $from.' 00:00:00'))
            ->with(['shop.organisation', 'order'])
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$rows, &$record, $command) {
                foreach ($invoices as $invoice) {
                    $order = $invoice->order;
                    if (!$order?->external_id) {
                        continue;
                    }

                    try {
                        $faireOrderData = $invoice->shop->getFaireOrder($order->external_id);
                    } catch (Throwable $exception) {
                        $command->warn($invoice->reference.': '.$exception->getMessage());
                        continue;
                    }

                    if (!is_array($faireOrderData) || !isset($faireOrderData['payout_costs'])) {
                        $command->warn($invoice->reference.': no Faire payout data');
                        continue;
                    }

                    $faireTaxAmount = UpdateFaireOrder::make()->getFaireTaxAmount($faireOrderData, $invoice->shop);
                    if (abs((float)$invoice->tax_amount - $faireTaxAmount) < 0.01) {
                        continue;
                    }

                    $rows[] = [
                        $invoice->shop->organisation->slug,
                        $invoice->shop->slug,
                        $invoice->reference,
                        $invoice->date?->toDateString(),
                        $invoice->net_amount,
                        $invoice->tax_amount,
                        $faireTaxAmount,
                        number_format((float)$invoice->tax_amount - $faireTaxAmount, 2),
                        $invoice->pay_status?->value ?? '',
                    ];

                    if ($command->option('fix')) {
                        $before = [$invoice->tax_amount, $invoice->total_amount, $order->tax_amount, $order->total_amount];
                        $this->handle($invoice, $faireTaxAmount);
                        $invoice->refresh();
                        $order->refresh();
                        $record[] = [$invoice->shop->slug, $invoice->reference, $order->reference, ...$before, $invoice->tax_amount, $invoice->total_amount, $order->tax_amount, $order->total_amount];
                    }
                }
            });

        $headers = ['Organisation', 'Shop', 'Invoice', 'Date', 'Net', 'Tax', 'Faire tax', 'Δ tax', 'Pay status'];
        $command->table($headers, $rows);
        $command->info(count($rows).' Faire invoices whose tax differs from what Faire charged.');

        $listPath = 'repairs/faire_invoice_tax_affected_'.now()->format('Ymd_His').'.csv';
        $this->storeCsv($listPath, $headers, $rows);
        $command->info('Affected invoices list: storage/app/'.$listPath);

        if (!$command->option('fix')) {
            $command->line('Dry run. Pass --fix to set these to the tax Faire charged.');

            return 0;
        }

        $path = 'repairs/faire_invoice_tax_'.now()->format('Ymd_His').'.csv';
        $this->storeCsv($path, ['shop', 'invoice', 'order', 'invoice_tax_before', 'invoice_total_before', 'order_tax_before', 'order_total_before', 'invoice_tax_after', 'invoice_total_after', 'order_tax_after', 'order_total_after'], $record);

        $command->info(count($rows).' invoices repaired. Before/after record: storage/app/'.$path);

        return 0;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function storeCsv(string $path, array $headers, array $rows): void
    {
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        Storage::disk('local')->put($path, stream_get_contents($csv));
        fclose($csv);
    }
}
