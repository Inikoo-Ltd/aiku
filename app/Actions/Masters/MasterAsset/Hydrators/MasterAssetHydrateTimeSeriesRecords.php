<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Masters\MasterAssetTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $timeSeriesId, string $from, string $to): string
    {
        return "hydrate-master-asset-time-series-records:{$timeSeriesId}:{$from}:{$to}";
    }

    public function handle(int $timeSeriesId, Carbon $from, Carbon $to): int
    {
        return 0;
        $timeSeries = MasterAssetTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return 0;
        }

        $masterAsset = $timeSeries->masterAsset;
        $frequency = $timeSeries->frequency;

        $periods = $this->generatePeriods($from, $to, $frequency);
        $recordsCreated = 0;

        foreach ($periods as $period) {
            $data = $this->aggregateDataForPeriod(
                $masterAsset,
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
            MasterAssetHydrateTimeSeriesNumberRecords::dispatch($timeSeries->id);
        }

        return $recordsCreated;
    }

    protected function aggregateDataForPeriod($masterAsset, Carbon $from, Carbon $to): array
    {
        $assetIds = $masterAsset->assets()->pluck('id');

        $salesData = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->where('in_process', false)
            ->whereBetween('date', [$from, $to])
            ->select(
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency')
            )
            ->first();

        $invoiceIdsQuery = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('date', [$from, $to])
            ->select('invoice_id')
            ->distinct();

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
