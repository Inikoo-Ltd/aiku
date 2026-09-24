<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Markdown / markup suggestions for the master products pricing tab (HELP-2331).
 * Built on the out-of-stock forecast (days of cover already carry demand smoothing and
 * seasonality) plus sales against the same period a year earlier. The price is global,
 * so a tip is only given when every organisation stocking the product agrees.
 * Staff review and apply each tip through the normal master price editor.
 */
class GetMasterProductsPriceTips
{
    use AsObject;

    public const array EXCLUDED_MASTER_SHOPS = ['aroma'];

    private const float SLOW_MOVER_DAYS = 180;
    private const float FAST_SELLER_DAYS = 30;
    private const float MINIMUM_MARKUP_OVER_COST = 1.25;
    private const int MINIMUM_CHANGE = 3;

    /**
     * @param Collection<int, object> $masterAssets rows carrying id, master_prices, effective_cost, sales, sales_ly
     *
     * @return array<int, array{change: int, reason: string}>
     */
    public function handle(MasterShop $masterShop, Collection $masterAssets): array
    {
        if (in_array($masterShop->slug, self::EXCLUDED_MASTER_SHOPS) || $masterAssets->isEmpty()) {
            return [];
        }

        $baseCurrencyCode = GetMasterShopCurrenciesRate::baseCurrencyCode($masterShop->price_exchanges ?? []);
        $baseCurrency     = $baseCurrencyCode ? Currency::where('code', $baseCurrencyCode)->first() : null;
        $costRate         = $baseCurrency ? GetCurrencyExchange::run($masterShop->group->currency, $baseCurrency) : null;

        $coverByMasterAsset = DB::table('master_asset_has_stocks')
            ->join('org_stocks', 'org_stocks.stock_id', '=', 'master_asset_has_stocks.stock_id')
            ->join('org_stock_stats', 'org_stock_stats.org_stock_id', '=', 'org_stocks.id')
            ->whereIn('master_asset_has_stocks.master_asset_id', $masterAssets->pluck('id'))
            ->whereNotIn('org_stocks.state', [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::SUSPENDED->value])
            ->groupBy('master_asset_has_stocks.master_asset_id')
            ->select('master_asset_has_stocks.master_asset_id')
            ->selectRaw('min(coalesce(org_stock_stats.days_of_cover, 730)) as min_cover, max(coalesce(org_stock_stats.days_of_cover, 730)) as max_cover')
            ->get()
            ->keyBy('master_asset_id');

        $tips = [];
        foreach ($masterAssets as $masterAsset) {
            $cover = $coverByMasterAsset[$masterAsset->id] ?? null;
            if (!$cover) {
                continue;
            }

            $price = (float) data_get($masterAsset->master_prices, "$baseCurrencyCode.value", 0);
            $cost  = $costRate && $masterAsset->effective_cost ? (float) $masterAsset->effective_cost * $costRate : null;

            $tip = static::tip(
                (float) $cover->min_cover,
                (float) $cover->max_cover,
                (float) ($masterAsset->sales ?? 0),
                (float) ($masterAsset->sales_ly ?? 0),
                $price,
                $cost
            );

            if ($tip) {
                $tips[$masterAsset->id] = $tip;
            }
        }

        return $tips;
    }

    /**
     * ponytail: fixed thresholds and steps; tune per master shop if staff disagree with the tips.
     * Products with no sales a year ago never get a tip, so new lines are not marked down.
     *
     * @return array{change: int, reason: string}|null
     */
    public static function tip(float $minCover, float $maxCover, float $sales, float $salesLastYear, float $price, ?float $cost): ?array
    {
        if ($salesLastYear <= 0 || $price <= 0) {
            return null;
        }

        if ($minCover >= self::SLOW_MOVER_DAYS && $sales <= $salesLastYear * 0.9) {
            $change = match (true) {
                $minCover >= 730 => 15,
                $minCover >= 365 => 10,
                default          => 5,
            };

            if ($cost) {
                $change = min($change, (int) floor(100 * (1 - $cost * self::MINIMUM_MARKUP_OVER_COST / $price)));
            }

            if ($change < self::MINIMUM_CHANGE) {
                return null;
            }

            return [
                'change' => -$change,
                'reason' => __('Slow mover: :days days of stock and sales down :pct% on last year', [
                    'days' => $minCover >= 730 ? '730+' : (int) $minCover,
                    'pct'  => (int) round(100 * (1 - $sales / $salesLastYear)),
                ]),
            ];
        }

        if ($maxCover > 0 && $maxCover < self::FAST_SELLER_DAYS && $sales >= $salesLastYear) {
            return [
                'change' => $maxCover < 15 ? 8 : 5,
                'reason' => __('Fast seller: stock runs out in :days days everywhere and sales are up on last year', [
                    'days' => (int) ceil($maxCover),
                ]),
            ];
        }

        return null;
    }
}
