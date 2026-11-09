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
 * The one place that says how a competitor price compares with ours, per unit: shoppers' prices against our
 * RRP and everyone else's against our price. Negative means they are cheaper. Compared in the competitor's
 * currency with our own price in that currency (master_prices / master_rrps), so an AW class product sold in
 * euros is not read as pounds; only when we have no price in their currency are both converted to the group
 * currency.
 * Built as SQL over competitors, competitor_products, master_assets and master_asset_competitor_products (whose
 * competitor_units, when set, says how many units their pack holds for that match) so lists can sort and rank by it.
 */
trait WithCompetitorPriceComparison
{
    /**
     * @return array{ours: string, theirs: string, difference: string, currency: string}
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

        $code       = '(SELECT currencies.code FROM currencies WHERE currencies.id = competitors.currency_id)';
        $ourPriceIn = function (string $currencyCode): string {
            $value = "(CASE competitors.sells_to WHEN 'consumer' THEN master_assets.master_rrps -> $currencyCode ->> 'value' ELSE master_assets.master_prices -> $currencyCode ->> 'value' END)";

            return "(CASE WHEN $value ~ '^[0-9]+(\\.[0-9]+)?$' THEN $value::numeric END)";
        };
        $local      = $ourPriceIn($code);
        $inGroup    = $ourPriceIn("'{$groupCurrency->code}'");
        $units = 'GREATEST(COALESCE(master_asset_competitor_products.competitor_units, competitor_products.units), 1)';

        $ours   = "(CASE WHEN $local > 0 THEN $local ELSE $inGroup END / GREATEST(master_assets.units, 1))";
        $theirs = "(CASE WHEN $local > 0 THEN competitor_products.price ELSE competitor_products.price * ($rate) END / $units)";

        return [
            'ours'       => "round($ours, 2)",
            'theirs'     => "round($theirs, 2)",
            'difference' => "CASE WHEN $ours > 0 AND $theirs IS NOT NULL THEN round(100 * ($theirs / $ours - 1)) END",
            'currency'   => "CASE WHEN $local > 0 THEN $code ELSE '{$groupCurrency->code}' END",
        ];
    }
}
