<?php

namespace App\Actions\Catalogue\Shop;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation $parent, $from_date = null, $to_date = null, ?ShopTypeEnum $filterType = null): array
    {
        $query = $parent->shops()
            ->select(['shops.id', 'shops.slug', 'shops.name', 'shops.state', 'shops.type', 'shops.colour', 'shops.is_aiku', 'shops.migrated_to_aiku_on', 'shops.currency_id', 'shops.organisation_id', 'shops.group_id'])
            ->whereNull('shops.closed_at')
            ->with([
                'currency'                  => fn ($q) => $q->select(['id', 'code']),
                'organisation'              => fn ($q) => $q->select(['id', 'slug', 'currency_id', 'image_id']),
                'organisation.currency'     => fn ($q) => $q->select(['id', 'code']),
                'organisation.image',
                'group'                     => fn ($q) => $q->select(['id', 'slug', 'currency_id']),
                'group.currency'            => fn ($q) => $q->select(['id', 'code']),
                'timeSeries'                => fn ($q) => $q->select(['id', 'shop_id', 'frequency'])
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value),
                'website'                   => fn ($q) => $q->select(['id', 'shop_id', 'domain']),
            ]);

        if ($filterType) {
            $query->where('shops.type', $filterType->value);
        }

        $shops = $query->get();

        $timeSeriesIds = [];
        $shopToTimeSeriesMap = [];

        foreach ($shops as $shop) {
            $dailyTimeSeries = $shop->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $shopToTimeSeriesMap[$shop->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales_external'               => 'sales_external',
                    'sales_org_currency_external'  => 'sales_org_currency_external',
                    'sales_grp_currency_external'  => 'sales_grp_currency_external',
                    'lost_revenue'                 => 'lost_revenue',
                    'lost_revenue_org_currency'    => 'lost_revenue_org_currency',
                    'lost_revenue_grp_currency'    => 'lost_revenue_grp_currency',
                    'baskets_created'              => 'baskets_created',
                    'baskets_created_org_currency' => 'baskets_created_org_currency',
                    'baskets_created_grp_currency' => 'baskets_created_grp_currency',
                    'baskets_updated'              => 'baskets_updated',
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
                'shop_time_series_records',
                'shop_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $parentType = $parent instanceof Organisation ? 'Organisation' : 'Group';

        $results = [];
        foreach ($shops as $shop) {
            $timeSeriesId = $shopToTimeSeriesMap[$shop->id] ?? null;
            $stats        = $allStats[$timeSeriesId] ?? [];

            $intervals         = ['1d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ld', 'lq', 'ly', 'tly', 'py', 'pq', 'pm', 'pw', 'ctm'];
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
                'id'                          => $shop->id,
                'slug'                        => $shop->slug,
                'name'                        => $shop->name,
                'state'                       => $shop->state?->value,
                'type'                        => $shop->type,
                'colour'                      => $shop->colour,
                'is_aiku'                     => $shop->is_aiku,
                'migrated_to_aiku_on'         => $shop->migrated_to_aiku_on,
                'organisation_slug'           => $shop->organisation?->slug ?? 'unknown',
                'organisation_logo'           => $shop->organisation?->imageSources(48, 48),
                'group_slug'                  => $shop->group?->slug ?? 'unknown',
                'shop_currency_code'          => $shop->currency?->code ?? 'GBP',
                'organisation_currency_code'  => $shop->organisation?->currency?->code ?? 'GBP',
                'group_currency_code'         => $shop->group?->currency?->code ?? 'GBP',
                'parent_type'                 => $parentType,
                'website_url'                 => $shop->website?->domain ? 'https://'.$shop->website->domain : null,
            ]);
        }

        return $results;
    }
}
