<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Flags a master price per unit that is far away from the rest of its master family (HELP-3496),
 * e.g. a variant created as a bundle of 17 trade units and priced at 17 times its siblings.
 * Prices are compared in the master shop base currency (master_assets.price).
 */
class GetMasterAssetPriceOutlier
{
    use AsObject;

    /** ponytail: fixed factor; tune per master shop if families mix very different pack sizes. */
    public const float FACTOR = 3;

    public const int MINIMUM_SIBLINGS = 3;

    /**
     * Correlated subquery for a select on master_assets: median price per unit of the other
     * current main products in the same master family, null when the family is too small. The
     * scalar price is in euros in every master shop, so a caller that shows the median next to
     * prices in another currency passes that currency and gets the median of those master prices.
     */
    public static function familyUnitPriceMedianSql(?string $currencyCode = null): string
    {
        $price = $currencyCode && preg_match('/^[A-Z]{3}$/', $currencyCode)
            ? "nullif(siblings.master_prices->'$currencyCode'->>'value', '')::numeric"
            : 'siblings.price';

        return "select percentile_cont(0.5) within group (order by $price / siblings.units)
                from master_assets siblings
                where siblings.master_family_id = master_assets.master_family_id
                  and siblings.id <> master_assets.id
                  and siblings.status and siblings.is_main
                  and $price > 0 and siblings.units > 0
                having count(*) >= ".self::MINIMUM_SIBLINGS;
    }

    public static function familyUnitPriceMedian(int $masterFamilyId): ?float
    {
        $median = DB::table('master_assets')
            ->where('master_family_id', $masterFamilyId)
            ->where('status', true)
            ->where('is_main', true)
            ->where('price', '>', 0)
            ->where('units', '>', 0)
            ->havingRaw('count(*) >= ?', [self::MINIMUM_SIBLINGS])
            ->selectRaw('percentile_cont(0.5) within group (order by price / units) as median')
            ->value('median');

        return $median !== null ? (float) $median : null;
    }

    /**
     * @return array{times: float, reason: string}|null
     */
    public function handle(mixed $price, mixed $units, mixed $familyUnitPriceMedian): ?array
    {
        $price  = (float) $price;
        $units  = (float) $units ?: 1;
        $median = (float) $familyUnitPriceMedian;

        if ($price <= 0 || $median <= 0) {
            return null;
        }

        $times = ($price / $units) / $median;

        if ($times >= self::FACTOR) {
            return [
                'times'  => round($times, 1),
                'reason' => __('Price per unit is :times times the usual price in this family, check it', ['times' => round($times, 1)]),
            ];
        }

        if ($times <= 1 / self::FACTOR) {
            return [
                'times'  => round($times, 2),
                'reason' => __('Price per unit is only :pct% of the usual price in this family, check it', ['pct' => (int) round($times * 100)]),
            ];
        }

        return null;
    }
}
