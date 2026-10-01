<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Helpers\AI\AskJev;
use App\Actions\Masters\Competitor\GetConfirmedCompetitorPrices;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetPriceTip;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Nightly price tips (HELP-2331). For each master product that is overstocked in every
 * organisation, or running out in every organisation, Jev is shown its sales, stock, incoming
 * goods, stock-outs, margin, family prices, running offers and past price changes, and picks a
 * change. Jev only picks; the guard rails decide: the direction must match the stock, a markdown
 * never goes below cost x 1.25, never follows a drop Jev thinks is temporary, and products without
 * sales a year ago (new lines) never get one. Only confident, non-hold picks are kept.
 *
 * The same run measures applied tips once their measuring window has passed.
 */
class GenerateMasterAssetPriceTips
{
    use AsAction;

    public string $commandSignature = 'masters:price-tips {--s|master-shop= : Only this master shop slug} {--sync : Run in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public const array EXCLUDED_MASTER_SHOPS = ['aroma'];

    public const float MINIMUM_MARKUP_OVER_COST = 1.25;

    public const float MIN_CONFIDENCE = 0.5;

    public const float TEMPORARY_DROP = 0.6;

    public const float OVERSTOCK_DAYS = 120;

    public const float SHORT_DAYS = 45;

    public const int MINIMUM_CHANGE = 3;

    public const int DISMISSED_QUIET_DAYS = 30;

    public const int MEASURE_AFTER_DAYS = 56;

    private const int CHUNK = 100;

    public const array OPTIONS = [
        'down_15' => -15,
        'down_10' => -10,
        'down_5'  => -5,
        'hold'    => 0,
        'up_5'    => 5,
        'up_10'   => 10,
    ];

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(MasterShop $masterShop, array $masterAssetIds): int
    {
        $recorded = 0;

        foreach ($this->signals($masterShop, $masterAssetIds) as $masterAssetId => $signals) {
            if ($this->settle(MasterAsset::find($masterAssetId), $signals)) {
                $recorded++;
            }
        }

        return $recorded;
    }

    /**
     * Ask Jev about one product and refresh or expire its open tip. When Jev cannot be reached
     * the open tip is left as it is.
     *
     * @param  array<string, mixed>  $signals
     */
    public function settle(MasterAsset $masterAsset, array $signals): ?MasterAssetPriceTip
    {
        $openTip = MasterAssetPriceTip::where('master_asset_id', $masterAsset->id)
            ->where('status', MasterAssetPriceTipStatusEnum::OPEN)
            ->first();

        $answers = static::direction($signals) ? $this->ask($signals) : [];

        if ($answers === null) {
            return $openTip;
        }

        $decision = static::decide($signals, $answers);

        if (!$decision) {
            $openTip?->update(['status' => MasterAssetPriceTipStatusEnum::EXPIRED, 'expired_at' => now()]);

            return null;
        }

        $attributes = [
            'change'                     => $decision['change'],
            'confidence'                 => $decision['confidence'],
            'probabilities'              => $decision['probabilities'],
            'temporary_drop_probability' => $decision['temporary_drop_probability'],
            'reason'                     => static::reason($signals, $decision),
            'state'                      => $signals,
            'price'                      => $signals['price'],
        ];

        if ($openTip) {
            $openTip->update($attributes);

            return $openTip;
        }

        return MasterAssetPriceTip::create([
            ...$attributes,
            'group_id'        => $masterAsset->group_id,
            'master_shop_id'  => $masterAsset->master_shop_id,
            'master_asset_id' => $masterAsset->id,
            'status'          => MasterAssetPriceTipStatusEnum::OPEN,
        ]);
    }

    /**
     * Overstocked everywhere allows a markdown, running out everywhere a markup, anything else no tip:
     * the price is global, so every organisation has to agree.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function direction(array $signals): ?int
    {
        if (($signals['sales_last_year'] ?? 0) <= 0 || ($signals['price'] ?? 0) <= 0 || ($signals['min_cover'] ?? null) === null) {
            return null;
        }

        return match (true) {
            $signals['min_cover'] >= self::OVERSTOCK_DAYS                            => -1,
            $signals['max_cover'] > 0 && $signals['max_cover'] < self::SHORT_DAYS => 1,
            default                                                                 => null,
        };
    }

    /**
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>|null  $answers
     * @return array{change: int, confidence: float, probabilities: array<string, float>, temporary_drop_probability: float|null, capped: bool}|null
     */
    public static function decide(array $signals, ?array $answers): ?array
    {
        $direction = static::direction($signals);
        $choice    = (string) Arr::get($answers, 'change.choice');
        $change    = self::OPTIONS[$choice] ?? 0;

        if (!$direction || $change === 0 || $change * $direction < 0) {
            return null;
        }

        $probabilities = array_map('floatval', (array) Arr::get($answers, 'change.probabilities', []));
        $confidence    = (float) ($probabilities[$choice] ?? Arr::get($answers, 'change.confidence', 0));
        $temporaryDrop = Arr::get($answers, 'temporary_drop.noul');
        $temporaryDrop = is_numeric($temporaryDrop) ? (float) $temporaryDrop : null;

        if ($confidence < self::MIN_CONFIDENCE) {
            return null;
        }

        $capped = false;
        if ($change < 0) {
            if ($temporaryDrop === null || $temporaryDrop >= self::TEMPORARY_DROP) {
                return null;
            }

            if ($signals['cost'] ?? null) {
                $floor = -(int) floor(100 * (1 - $signals['cost'] * self::MINIMUM_MARKUP_OVER_COST / $signals['price']));
                if ($floor > $change) {
                    $change = $floor;
                    $capped = true;
                }
            }

            if (-$change < self::MINIMUM_CHANGE) {
                return null;
            }
        }

        return [
            'change'                     => $change,
            'confidence'                 => round($confidence, 4),
            'probabilities'              => $probabilities,
            'temporary_drop_probability' => $temporaryDrop !== null ? round($temporaryDrop, 4) : null,
            'capped'                     => $capped,
        ];
    }

    /**
     * @param  array<string, mixed>  $signals
     * @return array<string, mixed>|null
     */
    private function ask(array $signals): ?array
    {
        return AskJev::make()->handle($signals, [
            'change'         => [
                'type'         => 'choice',
                'instructions' => 'This product is sold wholesale to trade customers at one price in every country. Pick the price change most likely to earn the most gross profit over the next three months. Weigh stock cover and goods on order, the sales trend against last year and the season, days out of stock (lost sales, not lost demand), the margin, the price of similar products in the family, offers already running, how sales reacted to past price changes, and what competitors charge per unit (difference_pct below 0 means they are cheaper; a competitor selling to shoppers is compared with our recommended retail price).',
                'criteria'     => [
                    'down_15' => 'Cut the price 15%: far too much stock, demand fell and is not coming back',
                    'down_10' => 'Cut the price 10%: too much stock and falling demand',
                    'down_5'  => 'Cut the price 5%: somewhat too much stock, demand soft',
                    'hold'    => 'Keep the price: the evidence for a change is weak or mixed',
                    'up_5'    => 'Raise the price 5%: stock is running out and demand is strong',
                    'up_10'   => 'Raise the price 10%: stock is running out fast, demand is strong and the price is low for the family',
                ],
            ],
            'temporary_drop' => [
                'type'         => 'noul',
                'instructions' => 'Is the drop in sales against last year temporary, explained by stock-outs, the season or a one-off order last year, rather than by weaker demand?',
                'criteria'     => [
                    'true'  => 'Temporary: stock-outs, season or a one-off explain it',
                    'false' => 'Real: customers are buying less of it',
                ],
            ],
        ]);
    }

    /**
     * The reason staff read next to the tip, built from the signals (Jev does not write).
     *
     * @param  array<string, mixed>  $signals
     * @param  array{change: int, capped: bool}  $decision
     */
    public static function reason(array $signals, array $decision): string
    {
        $parts = [];

        $parts[] = $decision['change'] < 0
            ? __('Stock for :days days in every organisation', ['days' => $signals['min_cover'] >= 730 ? '730+' : (int) $signals['min_cover']])
            : __('Stock runs out in :days days everywhere', ['days' => (int) ceil($signals['max_cover'])]);

        $trend = (int) round(100 * ($signals['sales'] / $signals['sales_last_year'] - 1));
        $parts[] = $trend >= 0
            ? __('sales up :pct% on last year', ['pct' => $trend])
            : __('sales down :pct% on last year', ['pct' => -$trend]);

        $incoming = array_sum(array_column($signals['organisations'] ?? [], 'incoming'));
        if ($incoming > 0) {
            $parts[] = __(':qty more on the way', ['qty' => round($incoming)]);
        }

        $daysOutOfStock = max([0, ...array_map(fn ($organisation) => array_sum($organisation['days_out_of_stock'] ?? []), $signals['organisations'] ?? [])]);
        if ($daysOutOfStock > 0) {
            $parts[] = __(':days days out of stock in the last year', ['days' => $daysOutOfStock]);
        }

        if ($signals['margin_pct'] ?? null) {
            $parts[] = __(':pct% margin', ['pct' => $signals['margin_pct']]);
        }

        if (($signals['family_median_price'] ?? null) && $signals['price']) {
            $parts[] = __('family median price :price', ['price' => $signals['family_median_price']]);
        }

        if ($signals['offers'] ?? []) {
            $parts[] = __(':n offers running', ['n' => count($signals['offers'])]);
        }

        $lastChange = Arr::last($signals['price_changes'] ?? [], fn ($priceChange) => $priceChange['sales_change_pct'] !== null);
        if ($lastChange) {
            $parts[] = __('price :change% on :date moved sales :sales%', [
                'change' => ($lastChange['change_pct'] > 0 ? '+' : '').$lastChange['change_pct'],
                'date'   => $lastChange['date'],
                'sales'  => ($lastChange['sales_change_pct'] > 0 ? '+' : '').$lastChange['sales_change_pct'],
            ]);
        }

        $cheapestCompetitor = collect($signals['competitors'] ?? [])->sortBy('difference_pct')->first();
        if ($cheapestCompetitor) {
            $parts[] = $cheapestCompetitor['difference_pct'] < 0
                ? __(':competitor :pct% cheaper per unit', ['competitor' => $cheapestCompetitor['competitor'], 'pct' => -$cheapestCompetitor['difference_pct']])
                : __('competitors :pct% dearer per unit or more', ['pct' => $cheapestCompetitor['difference_pct']]);
        }

        if ($decision['capped']) {
            $parts[] = __('limited by the cost floor');
        }

        return ucfirst(implode(', ', $parts));
    }

    /**
     * What Jev sees for each product with stock in at least one organisation.
     *
     * @param  array<int, int>  $masterAssetIds
     * @return array<int, array<string, mixed>>
     */
    public function signals(MasterShop $masterShop, array $masterAssetIds): array
    {
        $baseCurrencyCode = GetMasterShopCurrenciesRate::baseCurrencyCode($masterShop->price_exchanges ?? []);
        $baseCurrency     = $baseCurrencyCode ? Currency::where('code', $baseCurrencyCode)->first() : null;
        $costRate         = $baseCurrency ? GetCurrencyExchange::run($masterShop->group->currency, $baseCurrency) : null;

        $masterAssets = DB::table('master_assets')
            ->whereIn('id', $masterAssetIds)
            ->select(['id', 'code', 'name', 'units', 'master_prices', 'effective_cost'])
            ->selectSub(GetMasterAssetPriceOutlier::familyUnitPriceMedianSql(), 'family_unit_price_median')
            ->get();

        $months       = collect(range(24, 1))->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m'));
        $monthlySales = DB::table('master_asset_time_series as ts')
            ->join('master_asset_time_series_records as r', 'r.master_asset_time_series_id', '=', 'ts.id')
            ->where('ts.frequency', 'monthly')
            ->whereIn('ts.master_asset_id', $masterAssetIds)
            ->whereIn('r.period', $months)
            ->get(['ts.master_asset_id', 'r.period', 'r.sales_grp_currency_external as sales', 'r.sold'])
            ->groupBy('master_asset_id');

        $orgStocks = DB::table('master_asset_has_stocks')
            ->join('org_stocks', 'org_stocks.stock_id', '=', 'master_asset_has_stocks.stock_id')
            ->join('org_stock_stats', 'org_stock_stats.org_stock_id', '=', 'org_stocks.id')
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->whereIn('master_asset_has_stocks.master_asset_id', $masterAssetIds)
            ->whereNotIn('org_stocks.state', [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::SUSPENDED->value])
            ->get([
                'master_asset_has_stocks.master_asset_id',
                'org_stocks.id as org_stock_id',
                'organisations.code as organisation',
                'org_stocks.quantity_available',
                DB::raw('coalesce(org_stock_stats.days_of_cover, 730) as days_of_cover'),
            ]);

        $orgStockIds = $orgStocks->pluck('org_stock_id')->unique()->values();
        $incoming    = collect(GetProductIncomingStock::make()->forOrgStocks($orgStockIds->all()))->groupBy('org_stock_id')->map(fn ($lines) => $lines->sum('quantity'));
        $usage       = GetOrgStocksQuarterlyUsage::make()->handle($orgStockIds);
        $orgStocks   = $orgStocks->groupBy('master_asset_id');

        $offers       = $this->offers($masterAssetIds);
        $priceChanges = $this->priceChanges($masterAssetIds);
        $competitors  = GetConfirmedCompetitorPrices::run($masterAssetIds, $masterShop->group->currency);

        $signals = [];
        foreach ($masterAssets as $masterAsset) {
            $stocks = $orgStocks->get($masterAsset->id);
            if (!$stocks) {
                continue;
            }

            $sales = $months->mapWithKeys(fn ($month) => [$month => ['sales' => 0.0, 'sold' => 0]])->all();
            foreach ($monthlySales->get($masterAsset->id, collect()) as $record) {
                $sales[$record->period] = ['sales' => round((float) $record->sales, 2), 'sold' => (int) $record->sold];
            }
            $salesValues = array_column($sales, 'sales');

            $price  = (float) data_get(json_decode($masterAsset->master_prices ?? '{}', true), "$baseCurrencyCode.value", 0);
            $units  = (float) $masterAsset->units ?: 1;
            $cost   = $costRate && $masterAsset->effective_cost ? round((float) $masterAsset->effective_cost * $costRate, 2) : null;

            $organisations = $stocks->groupBy('organisation')->map(fn (Collection $rows, string $organisation) => [
                'organisation'      => $organisation,
                'days_of_cover'     => round((float) $rows->min('days_of_cover')),
                'stock'             => round((float) $rows->sum('quantity_available'), 1),
                'incoming'          => round((float) $rows->sum(fn ($row) => $incoming->get($row->org_stock_id, 0)), 1),
                'days_out_of_stock' => $rows->flatMap(fn ($row) => $usage->get($row->org_stock_id, collect()))
                    ->groupBy('period')
                    ->map(fn ($quarters) => $quarters->max('days_out_of_stock'))
                    ->all(),
            ])->values()->all();

            $signals[$masterAsset->id] = [
                'product'             => $masterAsset->code.' '.$masterAsset->name,
                'currency'            => $baseCurrencyCode,
                'price'               => $price,
                'units'               => $units,
                'cost'                => $cost,
                'margin_pct'          => $cost && $price > 0 ? (int) round(100 * (1 - $cost / $price)) : null,
                'family_median_price' => $masterAsset->family_unit_price_median ? round($masterAsset->family_unit_price_median * $units, 2) : null,
                'sales'               => round(array_sum(array_slice($salesValues, 12)), 2),
                'sales_last_year'     => round(array_sum(array_slice($salesValues, 0, 12)), 2),
                'min_cover'           => (float) min(array_column($organisations, 'days_of_cover')),
                'max_cover'           => (float) max(array_column($organisations, 'days_of_cover')),
                'monthly_sales'       => $sales,
                'organisations'       => $organisations,
                'offers'              => $offers->get($masterAsset->id, collect())->values()->all(),
                'price_changes'       => static::withSalesResponse($priceChanges->get($masterAsset->id, []), $sales),
                'competitors'         => $competitors->get($masterAsset->id, []),
            ];
        }

        return $signals;
    }

    /**
     * @param  array<int, int>  $masterAssetIds
     * @return Collection<int, Collection<int, string>>
     */
    private function offers(array $masterAssetIds): Collection
    {
        return DB::table('products')
            ->join('offers', fn ($join) => $join->whereRaw("(offers.trigger_type = 'Product' and offers.trigger_id = products.id) or (offers.trigger_type = 'ProductCategory' and offers.trigger_id = products.family_id)"))
            ->whereIn('products.master_product_id', $masterAssetIds)
            ->whereNull('products.deleted_at')
            ->whereNull('offers.deleted_at')
            ->where('offers.state', OfferStateEnum::ACTIVE->value)
            ->distinct()
            ->get(['products.master_product_id', 'offers.name'])
            ->groupBy('master_product_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->take(5));
    }

    /**
     * Past changes of the price per unit in any shop following the master, one per month.
     * Same-day edits collapse to the last price and the first month after a product is
     * created is skipped, as are jumps too large to be a real price change.
     *
     * @param  array<int, int>  $masterAssetIds
     * @return Collection<int, array<int, array{date: string, change_pct: float}>>
     */
    private function priceChanges(array $masterAssetIds): Collection
    {
        return DB::table('historic_assets')
            ->join('products', 'products.asset_id', '=', 'historic_assets.asset_id')
            ->whereIn('products.master_product_id', $masterAssetIds)
            ->where('historic_assets.created_at', '>=', now()->subMonths(27))
            ->where('historic_assets.price', '>', 0)
            ->orderBy('historic_assets.created_at')
            ->get(['products.master_product_id', 'products.id as product_id', 'products.created_at as product_created_at', 'historic_assets.price', 'historic_assets.units', 'historic_assets.created_at'])
            ->groupBy('master_product_id')
            ->map(function (Collection $rows) {
                $changes = [];
                foreach ($rows->groupBy('product_id') as $productRows) {
                    $settleFrom = Carbon::parse($productRows->first()->product_created_at)->addMonth();
                    $byDay      = $productRows->keyBy(fn ($row) => substr($row->created_at, 0, 10))
                        ->map(fn ($row) => (float) $row->price / ((float) $row->units ?: 1));
                    $previous = null;
                    foreach ($byDay as $day => $unitPrice) {
                        if ($previous && Carbon::parse($day)->gte($settleFrom)) {
                            $pct = round(100 * ($unitPrice / $previous - 1), 1);
                            if (abs($pct) >= 2 && abs($pct) <= 50) {
                                $changes[substr($day, 0, 7)] ??= ['date' => $day, 'change_pct' => $pct];
                            }
                        }
                        $previous = $unitPrice;
                    }
                }
                ksort($changes);

                return array_values($changes);
            });
    }

    /**
     * Sales in the three months before a price change against the three months after, when
     * both are inside the 24 months of sales.
     *
     * @param  array<int, array{date: string, change_pct: float}>  $priceChanges
     * @param  array<string, array{sales: float, sold: int}>  $monthlySales
     * @return array<int, array{date: string, change_pct: float, sales_change_pct: float|null}>
     */
    public static function withSalesResponse(array $priceChanges, array $monthlySales): array
    {
        return array_map(function (array $priceChange) use ($monthlySales) {
            $month  = Carbon::parse($priceChange['date'])->startOfMonth();
            $before = array_map(fn ($monthsAgo) => $monthlySales[$month->copy()->subMonths($monthsAgo)->format('Y-m')]['sales'] ?? null, [1, 2, 3]);
            $after  = array_map(fn ($monthsAhead) => $monthlySales[$month->copy()->addMonths($monthsAhead)->format('Y-m')]['sales'] ?? null, [1, 2, 3]);

            $salesChange = in_array(null, $before, true) || in_array(null, $after, true) || array_sum($before) <= 0
                ? null
                : round(100 * (array_sum($after) / array_sum($before) - 1), 1);

            return [...$priceChange, 'sales_change_pct' => $salesChange];
        }, $priceChanges);
    }

    /**
     * Applied tips whose window has passed: sales in the same number of days before and after
     * the change, and the after window a year earlier, from the daily sales.
     */
    public function measureOutcomes(): int
    {
        $measured = 0;

        MasterAssetPriceTip::where('status', MasterAssetPriceTipStatusEnum::APPLIED)
            ->whereNull('measured_at')
            ->where('applied_at', '<=', now()->subDays(self::MEASURE_AFTER_DAYS))
            ->each(function (MasterAssetPriceTip $tip) use (&$measured) {
                $appliedAt = $tip->applied_at->copy()->startOfDay();
                $days      = self::MEASURE_AFTER_DAYS;

                $before         = $this->dailySales($tip->master_asset_id, $appliedAt->copy()->subDays($days), $appliedAt);
                $after          = $this->dailySales($tip->master_asset_id, $appliedAt, $appliedAt->copy()->addDays($days));
                $afterLastYear  = $this->dailySales($tip->master_asset_id, $appliedAt->copy()->subYear(), $appliedAt->copy()->subYear()->addDays($days));
                $beforeLastYear = $this->dailySales($tip->master_asset_id, $appliedAt->copy()->subYear()->subDays($days), $appliedAt->copy()->subYear());

                $tip->update([
                    'outcome'     => [
                        'days'                          => $days,
                        'sales_before'                  => $before['sales'],
                        'sales_after'                   => $after['sales'],
                        'sold_before'                   => $before['sold'],
                        'sold_after'                    => $after['sold'],
                        'sales_after_last_year'         => $afterLastYear['sales'],
                        'sales_before_last_year'        => $beforeLastYear['sales'],
                        'sales_change_pct'              => $before['sales'] > 0 ? round(100 * ($after['sales'] / $before['sales'] - 1), 1) : null,
                        'sales_change_last_year_pct'    => $beforeLastYear['sales'] > 0 ? round(100 * ($afterLastYear['sales'] / $beforeLastYear['sales'] - 1), 1) : null,
                    ],
                    'measured_at' => now(),
                ]);
                $measured++;
            });

        return $measured;
    }

    /**
     * @return array{sales: float, sold: int}
     */
    private function dailySales(int $masterAssetId, Carbon $from, Carbon $to): array
    {
        $totals = DB::table('master_asset_time_series as ts')
            ->join('master_asset_time_series_records as r', 'r.master_asset_time_series_id', '=', 'ts.id')
            ->where('ts.frequency', 'daily')
            ->where('ts.master_asset_id', $masterAssetId)
            ->where('r.from', '>=', $from)
            ->where('r.from', '<', $to)
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external), 0) as sales, coalesce(sum(r.sold), 0) as sold')
            ->first();

        return ['sales' => round((float) $totals->sales, 2), 'sold' => (int) $totals->sold];
    }

    /**
     * ponytail: one Jev call per product that passes the stock pre-screen, in chunks of 100 per job;
     * widen the pre-screen (OVERSTOCK_DAYS, SHORT_DAYS) only if too few products get asked.
     */
    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        MasterAssetPriceTip::where('status', MasterAssetPriceTipStatusEnum::OPEN)
            ->whereHas('masterAsset', fn ($query) => $query->where('status', false))
            ->update(['status' => MasterAssetPriceTipStatusEnum::EXPIRED, 'expired_at' => now()]);

        $command->info('Measured '.$this->measureOutcomes().' applied tips');

        $masterShops = MasterShop::whereNotIn('slug', self::EXCLUDED_MASTER_SHOPS)
            ->where('type', '!=', ShopTypeEnum::FULFILMENT)
            ->when($command->option('master-shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($masterShops as $masterShop) {
            $ids = MasterAsset::where('master_shop_id', $masterShop->id)
                ->where('status', true)
                ->where('is_main', true)
                ->where('type', MasterAssetTypeEnum::PRODUCT)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('master_asset_price_tips')
                    ->whereColumn('master_asset_price_tips.master_asset_id', 'master_assets.id')
                    ->where(fn ($query) => $query
                        ->where(fn ($query) => $query->where('status', MasterAssetPriceTipStatusEnum::DISMISSED)->where('dismissed_at', '>', now()->subDays(self::DISMISSED_QUIET_DAYS)))
                        ->orWhere(fn ($query) => $query->where('status', MasterAssetPriceTipStatusEnum::APPLIED)->whereNull('measured_at'))))
                ->pluck('id');

            foreach ($ids->chunk(self::CHUNK) as $chunk) {
                if ($command->option('sync')) {
                    $this->handle($masterShop, $chunk->values()->all());
                } else {
                    static::dispatch($masterShop, $chunk->values()->all());
                }
            }

            $command->info("$masterShop->slug: ".$ids->count().' products');
        }

        return 0;
    }
}
