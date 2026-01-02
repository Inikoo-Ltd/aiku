<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\CollectionTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $timeSeriesId, string $from, string $to): string
    {
        return "hydrate-collection-time-series-records:{$timeSeriesId}:{$from}:{$to}";
    }

    public function handle(int $timeSeriesId, Carbon $from, Carbon $to): int
    {
        return 0;
        $timeSeries = CollectionTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return 0;
        }

        $collection = $timeSeries->collection;
        $frequency = $timeSeries->frequency;

        $periods = $this->generatePeriods($from, $to, $frequency);
        $recordsCreated = 0;

        foreach ($periods as $period) {
            $data = $this->aggregateDataForPeriod(
                $collection,
                $period['from'],
                $period['to']
            );

            $record = $timeSeries->records()->updateOrCreate(
                [
                    'from' => $period['from'],
                    'to' => $period['to'],
                ],
                $data
            );

            if ($record->wasRecentlyCreated) {
                $recordsCreated++;
            }
        }

        if ($recordsCreated > 0) {
            CollectionHydrateTimeSeriesNumberRecords::dispatch($timeSeries->id);
        }

        return $recordsCreated;
    }

    protected function aggregateDataForPeriod($collection, Carbon $from, Carbon $to): array
    {
        $productIds = DB::table('collection_has_models')
            ->where('collection_id', $collection->id)
            ->where('model_type', 'Product')
            ->pluck('model_id');

        $assetIds = DB::table('products')
            ->whereIn('id', $productIds)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        if ($assetIds->isEmpty()) {
            return [
                'sales' => 0,
                'sales_org_currency' => 0,
                'sales_grp_currency' => 0,
                'invoices' => 0,
                'refunds' => 0,
                'orders' => 0,
                'delivery_notes' => 0,
                'customers_invoiced' => 0,
            ];
        }

        $invoiceIdsQuery = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('date', [$from, $to])
            ->select('invoice_id')
            ->distinct();

        $salesData = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->select(
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency')
            )
            ->first();

        $invoicesCount = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->count();

        $refundsCount = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->count();

        $ordersCount = DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('date', [$from, $to])
            ->distinct()
            ->count('order_id');

        $deliveryNotesCount = DB::table('delivery_note_items')
            ->join('transactions', 'delivery_note_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.asset_id', $assetIds)
            ->whereBetween('delivery_note_items.date', [$from, $to])
            ->distinct()
            ->count('delivery_note_id');

        $customersInvoicedCount = DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('date', [$from, $to])
            ->distinct()
            ->count('customer_id');

        return [
            'sales' => $salesData->sales ?? 0,
            'sales_org_currency' => $salesData->sales_org_currency ?? 0,
            'sales_grp_currency' => $salesData->sales_grp_currency ?? 0,
            'invoices' => $invoicesCount,
            'refunds' => $refundsCount,
            'orders' => $ordersCount,
            'delivery_notes' => $deliveryNotesCount,
            'customers_invoiced' => $customersInvoicedCount,
        ];
    }
}
