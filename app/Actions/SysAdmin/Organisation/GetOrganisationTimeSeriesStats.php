<?php

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationTimeSeriesStats
{
    use AsObject;

    public function handle(Group $group, $from_date = null, $to_date = null): array
    {
        $organisations = $group->organisations()->where('type', OrganisationTypeEnum::SHOP)->get();

        $organisations->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        $timeSeriesIds = [];
        $organisationToTimeSeriesMap = [];

        foreach ($organisations as $organisation) {
            $dailyTimeSeries = $organisation->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $organisationToTimeSeriesMap[$organisation->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales_org_currency'           => 'sales_org_currency',
                    'sales_grp_currency'           => 'sales_grp_currency',
                    'lost_revenue_org_currency'    => 'lost_revenue_org_currency',
                    'lost_revenue_grp_currency'    => 'lost_revenue_grp_currency',
                    'baskets_created_org_currency' => 'baskets_created_org_currency',
                    'baskets_created_grp_currency' => 'baskets_created_grp_currency',
                    'baskets_updated_org_currency' => 'baskets_updated_org_currency',
                    'baskets_updated_grp_currency' => 'baskets_updated_grp_currency',
                    'invoices'                     => 'invoices',
                    'refunds'                      => 'refunds',
                    'orders'                       => 'orders',
                    'delivery_notes'               => 'delivery_notes',
                    'registrations_with_orders'    => 'registrations_with_orders',
                    'registrations_without_orders' => 'registrations_without_orders',
                    'customers_invoiced'           => 'customers_invoiced',
                ],
                'organisation_time_series_records',
                'organisation_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $results = [];
        foreach ($organisations as $organisation) {
            $timeSeriesId = $organisationToTimeSeriesMap[$organisation->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            $intervals = ['tdy', 'ld', '3d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ctm'];
            $registrationsData = [];

            foreach ($intervals as $interval) {
                $with = $stats["registrations_with_orders_{$interval}"] ?? 0;
                $without = $stats["registrations_without_orders_{$interval}"] ?? 0;
                $registrationsData["registrations_{$interval}"] = $with + $without;

                $withLy = $stats["registrations_with_orders_{$interval}_ly"] ?? 0;
                $withoutLy = $stats["registrations_without_orders_{$interval}_ly"] ?? 0;
                $registrationsData["registrations_{$interval}_ly"] = $withLy + $withoutLy;
            }

            $organisationData = array_merge($organisation->toArray(), $stats, $registrationsData, [
                'slug' => $organisation->slug ?? 'Unknown',
                'group_slug' => $organisation->group->slug ?? 'Unknown',
                'organisation_currency_code' => $organisation->currency->code ?? 'GBP',
                'group_currency_code' => $organisation->group->currency->code ?? 'GBP',
            ]);

            $results[] = $organisationData;
        }

        return $results;
    }
}
