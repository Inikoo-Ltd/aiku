<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Helpers\Currency;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetCompetitorProduct;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * How far each confirmed same-item competitor price is from ours, per unit: shoppers' prices against our
 * RRP, everyone else's against our price, in their currency when we have a price in it. Negative means
 * they are cheaper.
 */
class GetConfirmedCompetitorPrices
{
    use AsObject;

    /**
     * @param  array<int, int>  $masterAssetIds
     * @return Collection<int, array<int, array{competitor: string, sells_to: string, same_item: bool, difference_pct: int}>>
     */
    public function handle(array $masterAssetIds, Currency $currency): Collection
    {
        $exchanges = [];

        return MasterAssetCompetitorProduct::whereIn('master_asset_id', $masterAssetIds)
            ->where('status', MasterAssetCompetitorProductStatusEnum::CONFIRMED)
            ->where('is_same_item', true)
            ->with(['masterAsset:id,price,rrp,units,master_prices,master_rrps', 'competitorProduct.competitor.currency'])
            ->get()
            ->map(function (MasterAssetCompetitorProduct $match) use ($currency, &$exchanges) {
                $competitorProduct = $match->competitorProduct;
                $competitor        = $competitorProduct->competitor;
                $masterAsset       = $match->masterAsset;

                [$ours, $theirs] = static::unitPrices($masterAsset, $competitor, $competitorProduct->price === null ? null : (float) $competitorProduct->price, (float) ($match->competitor_units ?? $competitorProduct->units), $currency, $exchanges);

                if (!$ours || !$theirs) {
                    return null;
                }

                return [
                    'master_asset_id' => $match->master_asset_id,
                    'competitor'      => $competitor->name,
                    'sells_to'        => $competitor->sells_to->value,
                    'same_item'       => $match->is_same_item,
                    'difference_pct'  => (int) round(100 * ($theirs / $ours - 1)),
                ];
            })
            ->filter()
            ->groupBy('master_asset_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => collect($row)->except('master_asset_id')->all())->values()->all());
    }

    /**
     * Ours and theirs per unit, as WithCompetitorPriceComparison compares them: in the competitor's currency
     * with our own price in it when we have one, otherwise both in $currency, which is returned third.
     *
     * @param  array<int, float|null>  $exchanges
     * @return array{0: float|null, 1: float|null, 2: Currency}
     */
    public static function unitPrices(MasterAsset $masterAsset, Competitor $competitor, ?float $price, float $units, Currency $currency, array &$exchanges = []): array
    {
        $isConsumer = $competitor->sells_to === CompetitorSellsToEnum::CONSUMER;
        $ourPriceIn = fn (Currency $in): float => (float) data_get($isConsumer ? $masterAsset->master_rrps : $masterAsset->master_prices, $in->code.'.value');
        $local      = $ourPriceIn($competitor->currency);
        $ourUnits   = max((float) $masterAsset->units, 1);
        $units      = max($units, 1);

        if ($local > 0) {
            return [$local / $ourUnits, $price === null ? null : $price / $units, $competitor->currency];
        }

        $exchanges[$competitor->currency_id] ??= GetCurrencyExchange::run($competitor->currency, $currency);

        return [
            $ourPriceIn($currency) > 0 ? $ourPriceIn($currency) / $ourUnits : null,
            $price !== null && $exchanges[$competitor->currency_id] ? $price * $exchanges[$competitor->currency_id] / $units : null,
            $currency,
        ];
    }
}
