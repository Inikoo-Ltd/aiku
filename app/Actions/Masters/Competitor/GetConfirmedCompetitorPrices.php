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
use App\Models\Masters\MasterAssetCompetitorProduct;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * How far each confirmed same-item competitor price is from ours, per unit: shoppers' prices against our
 * RRP, everyone else's against our price. Negative means they are cheaper.
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
            ->with(['masterAsset:id,price,rrp,units', 'competitorProduct.competitor'])
            ->get()
            ->map(function (MasterAssetCompetitorProduct $match) use ($currency, &$exchanges) {
                $competitorProduct = $match->competitorProduct;
                $competitor        = $competitorProduct->competitor;
                $masterAsset       = $match->masterAsset;

                $exchanges[$competitor->currency_id] ??= GetCurrencyExchange::run($competitor->currency, $currency);

                $ours   = ($competitor->sells_to === CompetitorSellsToEnum::CONSUMER ? $masterAsset->rrp : $masterAsset->price) / max((float) $masterAsset->units, 1);
                $theirs = $competitorProduct->price !== null && $exchanges[$competitor->currency_id]
                    ? $competitorProduct->price * $exchanges[$competitor->currency_id] / max((float) $competitorProduct->units, 1)
                    : null;

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
}
