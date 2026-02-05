<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOfferCampaignsTimeSeriesStats
{
    use AsObject;

    public function handle(Shop $shop, $from_date = null, $to_date = null): array
    {
        // Get all active offer campaigns for the shop
        $offerCampaigns = OfferCampaign::where('shop_id', $shop->id)
            ->where('state', OfferCampaignStateEnum::ACTIVE)
            ->get();

        // Load only the timeSeries relationship (we don't need records hydrated anymore)
        $offerCampaigns->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        // Collect all time series IDs
        $timeSeriesIds = [];
        $offerCampaignToTimeSeriesMap = [];

        foreach ($offerCampaigns as $offerCampaign) {
            $dailyTimeSeries = $offerCampaign->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $offerCampaignToTimeSeriesMap[$offerCampaign->id] = $dailyTimeSeries->id;
            }
        }

        // Calculate stats in one batch query
        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'customers' => 'customers_invoiced',
                    'orders'    => 'orders',
                    'invoices'  => 'invoices',
                    'sales'     => 'sales_grp_currency',
                ],
                'offer_campaign_time_series_records',
                'offer_campaign_time_series_id',
                $from_date,
                $to_date
            );
        }

        $results = [];

        foreach ($offerCampaigns as $offerCampaign) {
            $timeSeriesId = $offerCampaignToTimeSeriesMap[$offerCampaign->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            // Skip if no stats generated or all stats are zero
            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            // Merge offer campaign attributes with stats
            $results[] = array_merge($offerCampaign->toArray(), $stats, [
                'shop_slug' => $shop->slug ?? 'unknown',
                'organisation_slug' => $shop->organisation->slug ?? 'unknown',
            ]);
        }

        return $results;
    }
}
