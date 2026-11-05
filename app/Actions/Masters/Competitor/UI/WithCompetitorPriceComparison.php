<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Helpers\Currency;
use App\Models\Masters\Competitor;
use App\Models\SysAdmin\Group;

/**
 * The one place that says how a competitor price compares with ours: both per unit in the group currency,
 * shoppers' prices against our RRP and everyone else's against our price. Negative means they are cheaper.
 * Built as SQL over competitors, competitor_products and master_assets so lists can sort and rank by it.
 */
trait WithCompetitorPriceComparison
{
    /**
     * @return array{ours: string, theirs: string, difference: string}
     */
    protected function competitorPriceSql(Group $group): array
    {
        $groupCurrency = $group->currency;
        $rates         = Competitor::where('group_id', $group->id)->distinct()->pluck('currency_id')
            ->mapWithKeys(fn (int $currencyId) => [
                $currencyId => $currencyId === $groupCurrency->id ? 1.0 : GetCurrencyExchange::run(Currency::find($currencyId), $groupCurrency),
            ])
            ->filter();

        $rate = $rates->isEmpty()
            ? 'NULL::numeric'
            : 'CASE competitors.currency_id '.$rates->map(fn (float $rate, int $currencyId) => "WHEN $currencyId THEN $rate")->implode(' ').' ELSE NULL END';

        $ours   = "((CASE competitors.sells_to WHEN 'consumer' THEN master_assets.rrp ELSE master_assets.price END) / GREATEST(master_assets.units, 1))";
        $theirs = "(competitor_products.price * ($rate) / GREATEST(competitor_products.units, 1))";

        return [
            'ours'       => "round($ours, 2)",
            'theirs'     => "round($theirs, 2)",
            'difference' => "CASE WHEN $ours > 0 AND $theirs IS NOT NULL THEN round(100 * ($theirs / $ours - 1)) END",
        ];
    }
}
