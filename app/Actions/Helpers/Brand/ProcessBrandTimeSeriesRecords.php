<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Brand\Hydrators\BrandTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Shop;
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

    public function getJobUniqueId(int $brandId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$brandId:$shopId:$frequency->value:$from:$to";
    }

    public function handle(int $brandId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $brand = Brand::find($brandId);
        $shop  = Shop::find($shopId);

        if (!$brand || !$shop) {
            return;
        }

        $timeSeries = BrandTimeSeries::where('brand_id', $brand->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $brand->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $shop, $from, $to);

        BrandTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(BrandTimeSeries $timeSeries, Shop $shop, string $from, string $to): void
    {
        $query = DB::table('invoice_transactions')
            ->where('invoice_transactions.brand_id', '=', $timeSeries->brand_id)
            ->where('invoice_transactions.shop_id', '=', $shop->id)
            ->where('invoice_transactions.date', '>=', $from)
            ->where('invoice_transactions.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'brand_time_series_id' => $timeSeries->id,
                    'shop_id'              => $shop->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'organisation_id'             => $shop->organisation_id,
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
        }
    }
}
