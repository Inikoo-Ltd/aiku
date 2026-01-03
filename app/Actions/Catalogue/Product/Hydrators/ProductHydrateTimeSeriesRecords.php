<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Models\Catalogue\AssetTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $timeSeriesId, string $from, string $to): string
    {
        return "hydrate-product-time-series-records:{$timeSeriesId}:{$from}:{$to}";
    }

    public function handle(int $timeSeriesId, Carbon $from, Carbon $to): int
    {
        return 0;
        $timeSeries = AssetTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return 0;
        }

        $asset = $timeSeries->asset;
        $frequency = $timeSeries->frequency;

        $periods = $this->generatePeriods($from, $to, $frequency);
        $recordsCreated = 0;

        foreach ($periods as $period) {
            $data = $this->aggregateDataForPeriod(
                $asset,
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
            ProductHydrateTimeSeriesNumberRecords::dispatch($timeSeries->id);
        }

        return $recordsCreated;
    }

    protected function aggregateDataForPeriod($asset, Carbon $from, Carbon $to): array
    {
        $data = DB::table('invoice_transactions')
            ->where('asset_id', $asset->id)
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
            ->where('transactions.asset_id', $asset->id)
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
