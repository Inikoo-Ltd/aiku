<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget\Concerns;

use App\Models\Catalogue\ShopStats;
use Illuminate\Support\Carbon;

/**
 * Reads the nightly TimesFM forecast in shop_stats.sales_forecast (ForecastShopSales) and turns it
 * into the target block's forecast line with its uncertainty band. Days are added up assuming
 * they are independent, then widened by a factor because good and bad days come in runs: with it
 * the band held the real month total 79% of the time and the real year total 79% (backtested on
 * 2025), so it reads as "8 times in 10 the result lands inside".
 */
trait HasSalesForecast
{
    private const float BAND_Z = 1.2816;

    private const float MONTH_BAND_FACTOR = 1.5;

    private const float YEAR_BAND_FACTOR = 2.3;

    /**
     * The forecast days after today of each shop, from a forecast made earlier this month.
     *
     * @return array<int, array<string, array{0: float, 1: float}>> shop id => date => [expected, variance]
     */
    private function salesForecastByShop(array $shopIds, Carbon $today, string $currency): array
    {
        $byShop = [];
        foreach (ShopStats::whereIn('shop_id', $shopIds)->whereNotNull('sales_forecast')->get(['shop_id', 'sales_forecast']) as $stats) {
            $forecast = $stats->sales_forecast;
            $madeOn   = Carbon::parse($forecast['from'] ?? '1970-01-01');
            if (!is_array($forecast[$currency] ?? null) || !$madeOn->isSameMonth($today) || $madeOn->gt($today)) {
                continue;
            }
            $days = array_filter($forecast[$currency], fn ($day, $date) => strlen((string) $date) === 10 && $date > $today->toDateString() && is_array($day), ARRAY_FILTER_USE_BOTH);
            if ($days) {
                $byShop[$stats->shop_id] = array_map(fn (array $day) => [(float) $day[0], (float) $day[1]], $days);
            }
        }

        return $byShop;
    }

    /**
     * The chart's forecast: cumulative expected sales from the last known point, with the low and
     * high edges of the band, one value per chart unit (day or month) and null where it does not apply.
     *
     * @param  array<int, array{0: float, 1: float}>  $increments  unit => [expected, variance] added in that unit
     *
     * @return array{expected: list<float|null>, low: list<float|null>, high: list<float|null>}
     */
    private function forecastLine(float $start, int $startUnit, array $increments, int $units, float $bandFactor): array
    {
        $line     = ['expected' => array_fill(0, $units, null), 'low' => array_fill(0, $units, null), 'high' => array_fill(0, $units, null)];
        $expected = $start;
        $variance = 0.0;

        if ($startUnit >= 1) {
            $line['expected'][$startUnit - 1] = $line['low'][$startUnit - 1] = $line['high'][$startUnit - 1] = round($start, 2);
        }

        ksort($increments);
        foreach ($increments as $unit => [$unitExpected, $unitVariance]) {
            $expected += $unitExpected;
            $variance += $unitVariance;
            $halfBand = self::BAND_Z * $bandFactor * sqrt($variance);

            $line['expected'][$unit - 1] = round($expected, 2);
            $line['low'][$unit - 1]      = round(max($start, $expected - $halfBand), 2);
            $line['high'][$unit - 1]     = round($expected + $halfBand, 2);
        }

        return $line;
    }
}
