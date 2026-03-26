<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Brand\Hydrators\BrandTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Helpers\Brand;
use App\Models\Helpers\BrandTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessBrandTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $brandId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$brandId:$frequency->value:$from:$to";
    }

    public function handle(int $brandId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $brand = Brand::find($brandId);

        if (!$brand) {
            return;
        }

        $timeSeries = BrandTimeSeries::where('brand_id', $brand->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $brand->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        BrandTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(BrandTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($timeSeries) {
                $query->select(DB::raw(1))
                      ->from('invoice_transaction_has_trade_units')
                      ->join('model_has_brands', function ($join) use ($timeSeries) {
                          $join->on('model_has_brands.model_id', '=', 'invoice_transaction_has_trade_units.trade_unit_id')
                               ->where('model_has_brands.model_type', '=', 'TradeUnit')
                               ->where('model_has_brands.brand_id', '=', $timeSeries->brand_id);
                      })
                      ->whereColumn('invoice_transaction_has_trade_units.invoice_transaction_id', 'invoice_transactions.id');
            })
            ->where('invoice_transactions.date', '>=', $from)
            ->where('invoice_transactions.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'brand_time_series_id' => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'sales_internal'              => 0,
                    'sales_org_currency_internal' => 0,
                    'sales_grp_currency_internal' => 0,
                    'lost_revenue'                => $result->lost_revenue,
                    'lost_revenue_org_currency'   => $result->lost_revenue_org_currency,
                    'lost_revenue_grp_currency'   => $result->lost_revenue_grp_currency,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                    'orders'                      => $result->orders,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(BrandTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'brand_time_series_id' => $timeSeries->id,
                    'period'               => $periodData['period'],
                    'frequency'            => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'sales_internal'              => 0,
                    'sales_org_currency_internal' => 0,
                    'sales_grp_currency_internal' => 0,
                    'lost_revenue'                => 0,
                    'lost_revenue_org_currency'   => 0,
                    'lost_revenue_grp_currency'   => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                ]
            );
        }
    }
}
