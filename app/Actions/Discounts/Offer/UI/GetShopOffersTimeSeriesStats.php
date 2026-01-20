<?php

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopOffersTimeSeriesStats
{
    use AsObject;

    public function handle(Shop $shop, $from_date = null, $to_date = null): array
    {
        // Get all active offers for the shop
        $offers = Offer::where('shop_id', $shop->id)
            ->where('state', OfferStateEnum::ACTIVE)
            ->get();

        // Load only the timeSeries relationship (we don't need records hydrated anymore)
        $offers->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        // Collect all time series IDs
        $timeSeriesIds = [];
        $offerToTimeSeriesMap = [];

        foreach ($offers as $offer) {
            $dailyTimeSeries = $offer->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $offerToTimeSeriesMap[$offer->id] = $dailyTimeSeries->id;
            }
        }

        // Calculate stats in one batch query
        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'customers' => 'customers_invoiced',
                    'orders' => 'orders'
                ],
                'offer_time_series_records',
                'offer_time_series_id',
                $from_date,
                $to_date
            );
        }

        $results = [];

        foreach ($offers as $offer) {
            $timeSeriesId = $offerToTimeSeriesMap[$offer->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            // Skip if no stats generated or all stats are zero
            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            // Merge offer attributes with stats
            $results[] = array_merge($offer->toArray(), $stats);
        }

        return $results;
    }
}
