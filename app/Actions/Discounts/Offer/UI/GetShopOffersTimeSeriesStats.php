<?php

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopOffersTimeSeriesStats
{
    use AsObject;

    public function handle(Shop $shop): array
    {
        // Get all offers for the shop
        $offers = Offer::where('shop_id', $shop->id)->get();

        // Load daily time series and their records
        // This assumes that the 'timeSeries' relationship exists on the Offer model
        $offers->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
                  ->with('records');
        }]);

        $results = [];

        foreach ($offers as $offer) {
            $dailyTimeSeries = $offer->timeSeries->first();

            $stats = [];

            if ($dailyTimeSeries && $dailyTimeSeries->records->isNotEmpty()) {
                $stats = CalculateTimeSeriesStats::run(
                    $dailyTimeSeries->records,
                    [
                        'customers' => 'customers_invoiced',
                        'orders' => 'orders'
                    ]
                );
            }

            // Skip if no stats generated or all stats are zero
            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            // Merge offer attributes with stats
            // The resource expects keys like 'customers_mtd', 'orders_1y' mixed with offer data
            $results[] = array_merge($offer->toArray(), $stats);
        }

        return $results;
    }
}
