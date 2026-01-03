<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
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

        $data = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->where('in_process', false)
            ->whereBetween('date', [$from, $to])
            ->select(
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )
            ->first();

        $deliveryNotesCount = DB::table('delivery_note_items')
            ->join('transactions', 'delivery_note_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.asset_id', $assetIds)
            ->whereBetween('delivery_note_items.date', [$from, $to])
            ->distinct()
            ->count('delivery_note_id');

        return [
            'sales' => $data->sales ?? 0,
            'sales_org_currency' => $data->sales_org_currency ?? 0,
            'sales_grp_currency' => $data->sales_grp_currency ?? 0,
            'invoices' => $data->invoices ?? 0,
            'refunds' => $data->refunds ?? 0,
            'orders' => $data->orders ?? 0,
            'delivery_notes' => $deliveryNotesCount,
            'customers_invoiced' => $data->customers_invoiced ?? 0,
        ];
    }
}
