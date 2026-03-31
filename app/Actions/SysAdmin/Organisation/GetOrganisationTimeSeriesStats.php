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
        $organisations = $group->organisations()
            ->select(['organisations.id', 'organisations.slug', 'organisations.name', 'organisations.code', 'organisations.colour', 'organisations.currency_id', 'organisations.group_id'])
            ->where('type', OrganisationTypeEnum::SHOP)
            ->with([
                'currency'       => fn ($q) => $q->select(['id', 'code']),
                'group'          => fn ($q) => $q->select(['id', 'slug', 'currency_id']),
                'group.currency' => fn ($q) => $q->select(['id', 'code']),
                'timeSeries'     => fn ($q) => $q->select(['id', 'organisation_id', 'frequency'])
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value),
            ])
            ->get();

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
                    'sales_org_currency_external'  => 'sales_org_currency_external',
                    'sales_grp_currency_external'  => 'sales_grp_currency_external',
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

        $groupSlug         = $group->slug ?? 'unknown';
        $groupCurrencyCode = $group->currency->code ?? 'GBP';

        $results = [];
        foreach ($organisations as $organisation) {
            $timeSeriesId = $organisationToTimeSeriesMap[$organisation->id] ?? null;
            $stats        = $allStats[$timeSeriesId] ?? [];

            $intervals         = ['tdy', 'ld', '3d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ctm'];
            $registrationsData = [];

            foreach ($intervals as $interval) {
                $with    = $stats["registrations_with_orders_{$interval}"] ?? 0;
                $without = $stats["registrations_without_orders_{$interval}"] ?? 0;
                $registrationsData["registrations_{$interval}"] = $with + $without;

                $withLy    = $stats["registrations_with_orders_{$interval}_ly"] ?? 0;
                $withoutLy = $stats["registrations_without_orders_{$interval}_ly"] ?? 0;
                $registrationsData["registrations_{$interval}_ly"] = $withLy + $withoutLy;
            }

            $results[] = array_merge($stats, $registrationsData, [
                'id'                          => $organisation->id,
                'slug'                        => $organisation->slug,
                'name'                        => $organisation->name,
                'code'                        => $organisation->code,
                'colour'                      => $organisation->colour,
                'group_slug'                  => $groupSlug,
                'organisation_currency_code'  => $organisation->currency->code ?? 'GBP',
                'group_currency_code'         => $groupCurrencyCode,
            ]);
        }

        return $results;
    }
}
