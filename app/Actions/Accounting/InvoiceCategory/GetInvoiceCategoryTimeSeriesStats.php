<?php

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetInvoiceCategoryTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation $parent, $from_date = null, $to_date = null): array
    {
        $invoiceCategories = [];

        if ($parent instanceof Group) {
            $invoiceCategories = InvoiceCategory::where('group_id', $parent->id)
                ->where('state', InvoiceCategoryStateEnum::ACTIVE)
                ->get();
        } else {
            $invoiceCategories = InvoiceCategory::where('organisation_id', $parent->id)
                ->where('state', InvoiceCategoryStateEnum::ACTIVE)
                ->get();
        }

        $invoiceCategories->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        $timeSeriesIds = [];
        $invoiceCategoryToTimeSeriesMap = [];

        foreach ($invoiceCategories as $invoiceCategory) {
            $dailyTimeSeries = $invoiceCategory->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $invoiceCategoryToTimeSeriesMap[$invoiceCategory->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales'                     => 'sales',
                    'sales_org_currency'        => 'sales_org_currency',
                    'sales_grp_currency'        => 'sales_grp_currency',
                    'lost_revenue'              => 'lost_revenue',
                    'lost_revenue_org_currency' => 'lost_revenue_org_currency',
                    'lost_revenue_grp_currency' => 'lost_revenue_grp_currency',
                    'invoices'                  => 'invoices',
                    'refunds'                   => 'refunds',
                    'customers_invoiced'        => 'customers_invoiced'
                ],
                'invoice_category_time_series_records',
                'invoice_category_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $results = [];
        foreach ($invoiceCategories as $invoiceCategory) {
            $timeSeriesId = $invoiceCategoryToTimeSeriesMap[$invoiceCategory->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $invoiceCategoryData = array_merge($invoiceCategory->toArray(), $stats);

            if ($parent instanceof Group) {
                $invoiceCategoryData['group_id'] = $parent->id;
                $invoiceCategoryData['group_slug'] = $parent->slug;
            } else {
                $invoiceCategoryData['organisation_id'] = $parent->id;
                $invoiceCategoryData['organisation_slug'] = $parent->slug;
            }

            $results[] = $invoiceCategoryData;
        }

        return $results;
    }
}
