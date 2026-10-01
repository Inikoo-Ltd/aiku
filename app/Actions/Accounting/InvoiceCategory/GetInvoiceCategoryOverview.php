<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetInvoiceCategoryOverview
{
    use AsObject;

    /**
     * @return array{
     *     currency_code: string|null,
     *     month: array<string, float|int>,
     *     year: array<string, float|int>,
     *     monthly: list<array{period: string, sales: float, invoices: int, refunds: int}>
     * }
     */
    public function handle(InvoiceCategory $invoiceCategory, ?Carbon $today = null): array
    {
        $today ??= now('UTC')->startOfDay();
        $end   = $today->copy()->addDay();

        $seriesIds = $invoiceCategory->timeSeries()->pluck('id', 'frequency');

        $periods = [
            'month' => $today->copy()->startOfMonth(),
            'year'  => $today->copy()->startOfYear(),
        ];

        $totals = [];
        foreach ($periods as $key => $start) {
            $totals[$key] = [
                ...$this->sumDaily($seriesIds[TimeSeriesFrequencyEnum::DAILY->value] ?? null, $start, $end, ''),
                ...$this->sumDaily($seriesIds[TimeSeriesFrequencyEnum::DAILY->value] ?? null, $start->copy()->subYear(), $end->copy()->subYear(), '_last_year'),
            ];
        }

        $monthly = DB::table('invoice_category_time_series_records')
            ->where('invoice_category_time_series_id', $seriesIds[TimeSeriesFrequencyEnum::MONTHLY->value] ?? 0)
            ->where('from', '>=', $today->copy()->startOfMonth()->subMonths(12))
            ->where('from', '<', $end)
            ->orderBy('from')
            ->get(['period', DB::raw('coalesce(sales_external, 0) + coalesce(sales_internal, 0) as sales'), 'invoices', 'refunds'])
            ->map(fn ($record) => ['period' => $record->period, 'sales' => (float) $record->sales, 'invoices' => (int) $record->invoices, 'refunds' => (int) $record->refunds])
            ->all();

        return [
            'currency_code' => $invoiceCategory->currency?->code,
            ...$totals,
            'monthly'       => $monthly,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function sumDaily(?int $seriesId, Carbon $from, Carbon $to, string $suffix): array
    {
        $sums = DB::table('invoice_category_time_series_records')
            ->where('invoice_category_time_series_id', $seriesId ?? 0)
            ->where('from', '>=', $from)
            ->where('from', '<', $to)
            ->selectRaw('coalesce(sum(coalesce(sales_external, 0) + coalesce(sales_internal, 0)), 0) as sales, coalesce(sum(invoices), 0) as invoices, coalesce(sum(refunds), 0) as refunds')
            ->first();

        return [
            'sales'.$suffix    => (float) $sums->sales,
            'invoices'.$suffix => (int) $sums->invoices,
            'refunds'.$suffix  => (int) $sums->refunds,
        ];
    }
}
