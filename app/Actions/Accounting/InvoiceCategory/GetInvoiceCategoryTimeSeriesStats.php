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
        $query = InvoiceCategory::query()
            ->select(['invoice_categories.id', 'invoice_categories.slug', 'invoice_categories.name', 'invoice_categories.state', 'invoice_categories.colour', 'invoice_categories.organisation_id', 'invoice_categories.group_id', 'invoice_categories.currency_id'])
            ->where('invoice_categories.state', InvoiceCategoryStateEnum::ACTIVE)
            ->with([
                'organisation'          => fn ($q) => $q->select(['id', 'slug', 'code', 'currency_id']),
                'organisation.currency' => fn ($q) => $q->select(['id', 'code']),
                'currency'              => fn ($q) => $q->select(['id', 'code']),
                'group'                 => fn ($q) => $q->select(['id', 'slug', 'currency_id']),
                'group.currency'        => fn ($q) => $q->select(['id', 'code']),
                'timeSeries'            => fn ($q) => $q->select(['id', 'invoice_category_id', 'frequency'])
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value),
            ]);

        if ($parent instanceof Group) {
            $query->where('invoice_categories.group_id', $parent->id)
                ->join('organisations', 'invoice_categories.organisation_id', '=', 'organisations.id')
                ->orderBy('organisations.code')
                ->orderBy('invoice_categories.name');
        } else {
            $query->where('invoice_categories.organisation_id', $parent->id)
                ->orderBy('invoice_categories.name');
        }

        $invoiceCategories = $query->get();

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
                    'sales_external'              => 'sales_external',
                    'sales_org_currency_external' => 'sales_org_currency_external',
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'lost_revenue'                => 'lost_revenue',
                    'lost_revenue_org_currency'   => 'lost_revenue_org_currency',
                    'lost_revenue_grp_currency'   => 'lost_revenue_grp_currency',
                    'invoices'                    => 'invoices',
                    'refunds'                     => 'refunds',
                    'customers_invoiced'          => 'customers_invoiced',
                ],
                'invoice_category_time_series_records',
                'invoice_category_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $parentType = $parent instanceof Organisation ? 'Organisation' : 'Group';

        $results = [];
        foreach ($invoiceCategories as $invoiceCategory) {
            $timeSeriesId = $invoiceCategoryToTimeSeriesMap[$invoiceCategory->id] ?? null;
            $stats        = $allStats[$timeSeriesId] ?? [];

            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $results[] = array_merge($stats, [
                'id'                         => $invoiceCategory->id,
                'slug'                       => $invoiceCategory->slug,
                'name'                       => $invoiceCategory->name,
                'state'                      => $invoiceCategory->state?->value,
                'colour'                     => $invoiceCategory->colour,
                'organisation_slug'          => $invoiceCategory->organisation?->slug,
                'organisation_code'          => $invoiceCategory->organisation?->code,
                'shop_currency_code'         => $invoiceCategory->currency?->code,
                'organisation_currency_code' => $invoiceCategory->organisation?->currency?->code,
                'group_currency_code'        => $invoiceCategory->group?->currency?->code,
                'parent_type'                => $parentType,
            ]);
        }

        return $results;
    }
}
