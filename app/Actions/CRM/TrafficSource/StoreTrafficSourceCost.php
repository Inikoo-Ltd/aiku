<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCosts;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCost;
use App\Models\Helpers\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTrafficSourceCost
{
    use AsAction;

    /**
     * Records what was spent on a traffic source, optionally on one of its campaigns, for a single day.
     *
     * Re-importing the same day is an update rather than an insert: advertisers routinely re-export a
     * report once a platform has finished attributing late conversions, and the second export is the
     * more accurate one. Keyed on source + campaign + date, so a campaign-level row and the
     * campaign-less total for the same source and day stay separate rows.
     *
     * @param array{date: Carbon|string, source_amount: float|string, source_currency_id: int, traffic_source_campaign_id?: int|null} $modelData
     */
    public function handle(TrafficSource $trafficSource, array $modelData): TrafficSourceCost
    {
        $date           = Carbon::parse(Arr::get($modelData, 'date'))->startOfDay();
        $sourceAmount   = (float) Arr::get($modelData, 'source_amount');
        $sourceCurrency = Currency::find(Arr::get($modelData, 'source_currency_id'));

        $shopCurrency = $trafficSource->shop->currency;
        $orgCurrency  = $trafficSource->organisation->currency;

        $trafficSourceCost = TrafficSourceCost::updateOrCreate(
            [
                'traffic_source_id'          => $trafficSource->id,
                'traffic_source_campaign_id' => Arr::get($modelData, 'traffic_source_campaign_id'),
                'date'                       => $date,
            ],
            [
                'group_id'           => $trafficSource->group_id,
                'organisation_id'    => $trafficSource->organisation_id,
                'shop_id'            => $trafficSource->shop_id,
                'source_amount'      => $sourceAmount,
                'source_currency_id' => $sourceCurrency->id,
                'amount'             => $sourceAmount * $this->rate($sourceCurrency, $shopCurrency, $date),
                'org_amount'         => $sourceAmount * $this->rate($sourceCurrency, $orgCurrency, $date),
            ]
        );

        TrafficSourceHydrateCosts::dispatch($trafficSource);

        return $trafficSourceCost;
    }

    /**
     * Ad spend is converted at the rate of the day it was spent, not today's: a report imported months
     * later would otherwise restate historic spend every time the exchange rate moved, and the ROAS of
     * a closed period would never sit still. Falls back to the current rate when no historic rate has
     * been fetched for that day.
     */
    private function rate(Currency $from, Currency $to, Carbon $date): float
    {
        if ($from->id === $to->id) {
            return 1.0;
        }

        return GetHistoricCurrencyExchange::run($from, $to, $date)
            ?? GetCurrencyExchange::run($from, $to);
    }
}
