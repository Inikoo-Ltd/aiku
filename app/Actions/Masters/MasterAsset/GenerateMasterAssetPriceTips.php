<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Masters\Competitor\GetConfirmedCompetitorPrices;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipKindEnum;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetPriceTip;
use App\Models\Masters\MasterAssetStats;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Nightly price tips (HELP-2331, INI-075). A tip flags a master product worth a look and says why; it
 * never names a price. Stock is averaged over the organisations by what each sold in the last year. A
 * product is flagged when it holds a year and a half of stock while sales fall, when it is running out while
 * sales rise and nothing is on the way, or when it sells much worse than a family that holds up. What to
 * do about it is decided by the person, on their own or with their assistant and the whole family in view.
 *
 * Every product checked keeps the outcome of its last check, so a product without a tip says why. A price
 * changed by hand, or a tip turned down, keeps the product quiet for a while.
 */
class GenerateMasterAssetPriceTips
{
    use AsAction;

    public string $commandSignature = 'masters:price-tips {--s|master-shop= : Only this master shop slug} {--sync : Run in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public const array EXCLUDED_MASTER_SHOPS = ['aroma'];

    public const int OVERSTOCK_DAYS = 540;

    public const int SALES_FALL_PCT = -40;

    public const int SHORT_DAYS = 30;

    public const int SALES_RISE_PCT = 25;

    public const int BEHIND_FAMILY_POINTS = 50;

    public const int BEHIND_FAMILY_MIN_COVER_DAYS = 120;

    public const int FAMILY_HOLDS_PCT = -10;

    public const int MIN_SALES = 500;

    public const int NEW_PRODUCT_MIN_DAYS = 60;

    public const int DISMISSED_QUIET_DAYS = 30;

    public const int MANUAL_CHANGE_QUIET_DAYS = 30;

    public const float WEBSITE_MIN_ONLINE_SHARE = 0.8;

    public const int WEBSITE_VISITORS_DROP_PCT = -25;

    public const int WEBSITE_MIN_VISITORS = 50;

    public const int WEBSITE_MIN_TRAFFIC_DAYS = 80;

    private const int CHUNK = 100;

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(MasterShop $masterShop, array $masterAssetIds): int
    {
        $recorded = 0;
        $signals  = $this->signals($masterShop, $masterAssetIds);

        foreach ($masterAssetIds as $masterAssetId) {
            $masterAsset = MasterAsset::find($masterAssetId);
            if (!$masterAsset) {
                continue;
            }

            if (!isset($signals[$masterAssetId])) {
                $this->recordCheck($masterAsset, ['outcome' => 'no_stock', 'text' => __('No tip: no stock linked')]);

                continue;
            }

            if ($this->settle($masterAsset, $signals[$masterAssetId])) {
                $recorded++;
            }
        }

        return $recorded;
    }

    /**
     * Flag one product, refresh the flag it already has, or expire it when the reason is gone.
     *
     * @param  array<string, mixed>  $signals
     */
    public function settle(MasterAsset $masterAsset, array $signals): ?MasterAssetPriceTip
    {
        $openTip = MasterAssetPriceTip::where('master_asset_id', $masterAsset->id)
            ->where('status', MasterAssetPriceTipStatusEnum::OPEN)
            ->first();

        $verdict = static::verdict($signals);
        $kind    = $verdict['kind'];
        $this->recordCheck($masterAsset, $verdict['check']);

        if (!$kind) {
            $openTip?->update(['status' => MasterAssetPriceTipStatusEnum::EXPIRED, 'expired_at' => now()]);

            return null;
        }

        $attributes = [
            'kind'          => $kind,
            'change'        => 0,
            'confidence'    => 0,
            'probabilities' => [],
            'reason'        => static::reason($signals, $kind),
            'state'         => $signals,
            'price'         => $signals['price'],
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
     * @param  array{outcome: string, text: string}  $check
     */
    public function recordCheck(MasterAsset $masterAsset, array $check): void
    {
        MasterAssetStats::updateOrCreate(
            ['master_asset_id' => $masterAsset->id],
            ['price_tip_check' => $check, 'price_tip_checked_at' => now()]
        );
    }

    /**
     * Stock cover averaged over the organisations by what each sold in the last year, so an organisation that
     * sells little of the product cannot block a tip the organisations selling most of it call for. Null when
     * none sold any: their cover is then only the 730 day placeholder.
     *
     * @param  array<int, array<string, mixed>>  $organisations
     */
    public static function salesWeightedCover(array $organisations): ?float
    {
        if (!$organisations) {
            return null;
        }

        $sold = array_sum(array_map(fn ($organisation) => max(0, (float) ($organisation['sold_last_year'] ?? 0)), $organisations));

        if ($sold <= 0) {
            return null;
        }

        return round(array_sum(array_map(
            fn ($organisation) => (float) $organisation['days_of_cover'] * max(0, (float) ($organisation['sold_last_year'] ?? 0)) / $sold,
            $organisations
        )), 1);
    }

    /**
     * Why a product is not looked at, or null when it is.
     *
     * @param  array<string, mixed>  $signals
     * @return array{outcome: string, text: string}|null
     */
    public static function precheck(array $signals): ?array
    {
        $cover = $signals['cover'] ?? null;

        return match (true) {
            ($signals['price'] ?? 0) <= 0 => ['outcome' => 'no_price', 'text' => __('No tip: no price')],
            ($signals['price_changed_at'] ?? null) && Carbon::parse($signals['price_changed_at'])->gt(now()->subDays(self::MANUAL_CHANGE_QUIET_DAYS)) => [
                'outcome' => 'price_changed',
                'text'    => __('No tip: price changed by hand on :date, no new tip for :days days after that', ['date' => Carbon::parse($signals['price_changed_at'])->format('j M'), 'days' => self::MANUAL_CHANGE_QUIET_DAYS]),
            ],
            ($signals['cost'] ?? 0) >= $signals['price'] => ['outcome' => 'cost_above_price', 'text' => __('No tip: the cost on record is above the price, check the cost')],
            ($signals['sales'] ?? 0) <= 0 && ($signals['sales_last_year'] ?? 0) <= 0 => ['outcome' => 'no_sales', 'text' => __('No tip: no sales in two years')],
            ($signals['new'] ?? false) && ($signals['days_on_sale'] ?? 0) < self::NEW_PRODUCT_MIN_DAYS => [
                'outcome' => 'too_new',
                'text'    => __('No tip yet: new, on sale for :days days', ['days' => (int) ($signals['days_on_sale'] ?? 0)]),
            ],
            $cover === null && ($signals['organisations'] ?? []) => ['outcome' => 'no_recent_sales', 'text' => __('No tip: no sales last year in any organisation')],
            $cover === null => ['outcome' => 'no_stock', 'text' => __('No tip: no stock linked')],
            default => null,
        };
    }

    /**
     * Sales of the last twelve months against the twelve before, in percent. Null for a line with no last year.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function trend(array $signals): ?int
    {
        return ($signals['sales_last_year'] ?? 0) > 0
            ? (int) round(100 * ($signals['sales'] / $signals['sales_last_year'] - 1))
            : null;
    }

    /**
     * What makes a product worth a look. Plain rules on stock, sales and the family, nothing that names a
     * price: what to do about it is for the person, or for their assistant with the whole family in front of it.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function flag(array $signals): ?MasterAssetPriceTipKindEnum
    {
        $trend = static::trend($signals);
        if ($trend === null) {
            return null;
        }

        $cover        = (float) $signals['cover'];
        $incoming     = array_sum(array_column($signals['organisations'] ?? [], 'incoming'));
        $familyChange = data_get($signals, 'family.family_change_pct');

        return match (true) {
            $cover >= self::OVERSTOCK_DAYS && $trend <= self::SALES_FALL_PCT && $signals['sales_last_year'] >= self::MIN_SALES => MasterAssetPriceTipKindEnum::OVERSTOCKED,
            $cover > 0 && $cover < self::SHORT_DAYS && $trend >= self::SALES_RISE_PCT && $signals['sales'] >= self::MIN_SALES && $incoming <= 0 => MasterAssetPriceTipKindEnum::RUNNING_OUT,
            $familyChange !== null && $familyChange >= self::FAMILY_HOLDS_PCT && $trend <= $familyChange - self::BEHIND_FAMILY_POINTS
                && $signals['sales_last_year'] >= self::MIN_SALES && $cover >= self::BEHIND_FAMILY_MIN_COVER_DAYS => MasterAssetPriceTipKindEnum::BEHIND_FAMILY,
            default => null,
        };
    }

    /**
     * The flag kept, or null, with the outcome of the check staff see next to the product.
     *
     * @param  array<string, mixed>  $signals
     * @return array{kind: MasterAssetPriceTipKindEnum|null, check: array{outcome: string, text: string}}
     */
    public static function verdict(array $signals): array
    {
        if ($precheck = static::precheck($signals)) {
            return ['kind' => null, 'check' => $precheck];
        }

        $kind = static::flag($signals);

        if (!$kind) {
            return ['kind' => null, 'check' => ['outcome' => 'nothing', 'text' => __('No tip: stock and sales look normal')]];
        }

        if ($kind !== MasterAssetPriceTipKindEnum::RUNNING_OUT && !static::websiteIsHealthy($signals['website'] ?? null)) {
            return ['kind' => null, 'check' => ['outcome' => 'website', 'text' => __('No tip: :reason', ['reason' => static::websiteReason($signals['website'])])]];
        }

        return ['kind' => $kind, 'check' => ['outcome' => 'tip', 'text' => '']];
    }

    /**
     * The facts staff read next to the flag, built from the signals.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function reason(array $signals, MasterAssetPriceTipKindEnum $kind): string
    {
        $parts = [];

        $parts[] = $kind === MasterAssetPriceTipKindEnum::RUNNING_OUT
            ? __('Stock runs out in :days days, averaged by what each organisation sells', ['days' => (int) ceil($signals['cover'])])
            : __('Stock for :days days, averaged by what each organisation sells', ['days' => $signals['cover'] >= 730 ? '730+' : (int) $signals['cover']]);

        $trend   = static::trend($signals);
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
            $parts[] = __('family median price :price', ['price' => $signals['currency'] ? Number::currency($signals['family_median_price'], $signals['currency']) : $signals['family_median_price']]);
        }

        if ($signals['offers'] ?? []) {
            $parts[] = __(':n offers running, latest: :offer', ['n' => count($signals['offers']), 'offer' => Arr::first($signals['offers'])]);
        }

        if ($family = $signals['family'] ?? null) {
            $parts[] = static::familyReason($family, $trend);
        }

        if ($website = $signals['website'] ?? null) {
            $parts[] = static::websiteReason($website);
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

        return ucfirst(implode(', ', $parts));
    }

    /**
     * What is known about each product with stock in at least one organisation.
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
            ->select(['id', 'code', 'name', 'units', 'master_prices', 'effective_cost', 'master_family_id'])
            ->selectSub(GetMasterAssetPriceOutlier::familyUnitPriceMedianSql($baseCurrencyCode), 'family_unit_price_median')
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
        $website      = $this->websiteHealth($masterAssetIds);
        $family       = $this->familyContext($masterAssets->pluck('master_family_id')->filter()->unique()->values()->all(), $months);
        $feedback     = $this->staffFeedback($masterAssetIds, $masterAssets->pluck('master_family_id')->filter()->unique()->all());
        $changedByHand = $this->lastManualPriceChanges($masterAssetIds);

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
                'sold_last_year'    => round((float) $rows->sum(fn ($row) => collect($usage->get($row->org_stock_id, []))->sum('sales')), 1),
                'incoming'          => round((float) $rows->sum(fn ($row) => $incoming->get($row->org_stock_id, 0)), 1),
                'days_out_of_stock' => $rows->flatMap(fn ($row) => $usage->get($row->org_stock_id, collect()))
                    ->groupBy('period')
                    ->map(fn ($quarters) => $quarters->max('days_out_of_stock'))
                    ->all(),
            ])->values()->all();

            $firstSaleMonth = Arr::first(array_keys(array_filter($sales, fn ($month) => $month['sales'] > 0)));
            $salesLastYear  = round(array_sum(array_slice($salesValues, 0, 12)), 2);

            $signals[$masterAsset->id] = [
                'product'             => $masterAsset->code.' '.$masterAsset->name,
                'currency'            => $baseCurrencyCode,
                'price'               => $price,
                'units'               => $units,
                'cost'                => $cost,
                'margin_pct'          => $cost && $price > 0 ? (int) round(100 * (1 - $cost / $price)) : null,
                'family_median_price' => $masterAsset->family_unit_price_median ? round($masterAsset->family_unit_price_median * $units, 2) : null,
                'sales'               => round(array_sum(array_slice($salesValues, 12)), 2),
                'sales_last_year'     => $salesLastYear,
                'new'                 => $salesLastYear <= 0,
                'days_on_sale'        => $firstSaleMonth ? (int) Carbon::parse($firstSaleMonth.'-01')->diffInDays(now()) : 0,
                'cover'               => static::salesWeightedCover($organisations),
                'monthly_sales'       => $sales,
                'organisations'       => $organisations,
                'offers'              => $offers->get($masterAsset->id, collect())->values()->all(),
                'price_changes'       => static::withSalesResponse($priceChanges->get($masterAsset->id, []), $sales),
                'price_changed_at'    => $changedByHand->get($masterAsset->id),
                'competitors'         => $competitors->get($masterAsset->id, []),
                'website'             => $website[$masterAsset->id] ?? null,
                'family'              => $family[$masterAsset->id] ?? null,
                'staff_feedback'      => $feedback
                    ->filter(fn ($row) => $row->master_asset_id == $masterAsset->id || ($masterAsset->master_family_id && $row->master_family_id == $masterAsset->master_family_id))
                    ->take(5)
                    ->map(fn ($row) => [
                        'product'        => $row->code,
                        'tip'            => $row->kind ? (MasterAssetPriceTipKindEnum::labels()[$row->kind] ?? $row->kind) : sprintf('%+d%%', $row->change),
                        'why_wrong'      => $row->dismissed_reason,
                        'date'           => Carbon::parse($row->dismissed_at)->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $signals;
    }

    public static function familyReason(array $family, ?int $productTrend): string
    {
        $parts = [__('#:rank of :products by sales', ['rank' => $family['rank'], 'products' => $family['products']])];
        $parts[] = __(':pct% of family sales', ['pct' => $family['share_pct']]);
        if ($family['family_change_pct'] !== null && $productTrend !== null) {
            $parts[] = __('family :family% vs this product :product%', [
                'family'  => ($family['family_change_pct'] > 0 ? '+' : '').$family['family_change_pct'],
                'product' => ($productTrend > 0 ? '+' : '').$productTrend,
            ]);
        }

        return __('in the family: :details', ['details' => implode(', ', $parts)]);
    }

    /**
     * Each product of these families against its family over the last 12 months: rank by sales, share of family
     * sales and the family's sales against the 12 months before.
     *
     * @param  array<int, int>  $masterFamilyIds
     * @param  Collection<int, string>  $months  the last 24 months, oldest first
     * @return array<int, array<string, mixed>>
     */
    public function familyContext(array $masterFamilyIds, Collection $months): array
    {
        if (!$masterFamilyIds) {
            return [];
        }

        $lastYear = $months->slice(0, 12)->values()->all();

        $rows = DB::table('master_assets')
            ->join('master_asset_time_series as ts', fn ($join) => $join->on('ts.master_asset_id', '=', 'master_assets.id')->where('ts.frequency', 'monthly'))
            ->join('master_asset_time_series_records as r', 'r.master_asset_time_series_id', '=', 'ts.id')
            ->whereIn('master_assets.master_family_id', $masterFamilyIds)
            ->where('master_assets.status', true)
            ->where('master_assets.is_main', true)
            ->whereIn('r.period', $months->all())
            ->groupBy('master_assets.id', 'master_assets.master_family_id')
            ->select(['master_assets.id', 'master_assets.master_family_id'])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.period <> all(?::text[])), 0) as sales', ['{'.implode(',', $lastYear).'}'])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.period = any(?::text[])), 0) as sales_last_year', ['{'.implode(',', $lastYear).'}'])
            ->get();

        $context = [];
        foreach ($rows->groupBy('master_family_id') as $products) {
            $familySales         = (float) $products->sum('sales');
            $familySalesLastYear = (float) $products->sum('sales_last_year');
            $ranked              = $products->sortByDesc(fn ($product) => (float) $product->sales)->values();

            foreach ($ranked as $index => $product) {
                $context[$product->id] = [
                    'rank'               => $index + 1,
                    'products'           => $ranked->count(),
                    'products_selling'   => $ranked->filter(fn ($row) => (float) $row->sales > 0)->count(),
                    'share_pct'          => $familySales > 0 ? (int) round(100 * (float) $product->sales / $familySales) : 0,
                    'family_change_pct'  => $familySalesLastYear > 0 ? (int) round(100 * ($familySales / $familySalesLastYear - 1)) : null,
                ];
            }
        }

        return $context;
    }

    public static function websiteIsHealthy(?array $website): bool
    {
        if (!$website || !$website['shops']) {
            return true;
        }

        return $website['online'] >= self::WEBSITE_MIN_ONLINE_SHARE * $website['shops']
            && $website['online_without_images'] === 0
            && ($website['family_visitors_change_pct'] === null || $website['family_visitors_change_pct'] > self::WEBSITE_VISITORS_DROP_PCT);
    }

    public static function websiteReason(array $website): string
    {
        if (!static::websiteIsHealthy($website)) {
            $problems = [];
            if ($website['online'] < self::WEBSITE_MIN_ONLINE_SHARE * $website['shops']) {
                $problems[] = __('offline in :n of :shops shops', ['n' => $website['shops'] - $website['online'], 'shops' => $website['shops']]);
            }
            if ($website['online_without_images']) {
                $problems[] = __('no images in :n shops', ['n' => $website['online_without_images']]);
            }
            if ($website['family_visitors_change_pct'] !== null && $website['family_visitors_change_pct'] <= self::WEBSITE_VISITORS_DROP_PCT) {
                $problems[] = __('family page visitors :pct%', ['pct' => $website['family_visitors_change_pct']]);
            }

            return __('website problem: :problems', ['problems' => implode(', ', $problems)]);
        }

        $parts = [__('online in :online of :shops shops', ['online' => $website['online'], 'shops' => $website['shops']])];
        if ($website['family_visitors_change_pct'] !== null) {
            $parts[] = __('family page visitors :pct%', ['pct' => ($website['family_visitors_change_pct'] > 0 ? '+' : '').$website['family_visitors_change_pct']]);
        }

        return __('website OK: :details', ['details' => implode(', ', $parts)]);
    }

    /**
     * Product pages of each master asset in open shops (how many are online, and with images) and visitors to its
     * family pages in the last 90 days against the 90 before. The family page is where trade customers browse and
     * add to basket; product pages get few direct visits. Visitors are left out when the daily traffic records do
     * not cover most days of both windows.
     *
     * @param  array<int, int>  $masterAssetIds
     * @return array<int, array<string, mixed>>
     */
    public function websiteHealth(array $masterAssetIds): array
    {
        $products = DB::table('products')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->leftJoin('product_categories as families', 'families.id', '=', 'products.family_id')
            ->whereIn('products.master_product_id', $masterAssetIds)
            ->whereNull('products.deleted_at')
            ->where('shops.state', 'open')
            ->whereIn('products.state', ['active', 'discontinuing'])
            ->get([
                'products.master_product_id',
                'families.webpage_id as family_webpage_id',
                DB::raw("webpages.state = 'live' as online"),
                DB::raw("products.web_images is null or products.web_images::text in ('{}', '[]', 'null') as without_images"),
            ]);

        if ($products->isEmpty()) {
            return [];
        }

        $familyWebpageIds = $products->pluck('family_webpage_id')->filter()->unique()->values()->all();
        $windows          = $familyWebpageIds ? $this->trafficWindows() : null;

        $traffic = collect();
        if ($windows) {
            $traffic = DB::table('webpage_time_series_records as records')
                ->join('webpage_time_series as series', 'series.id', '=', 'records.webpage_time_series_id')
                ->whereIn('series.webpage_id', $familyWebpageIds)
                ->where('series.frequency', 'daily')
                ->where('records.frequency', 'D')
                ->where('records.from', '>=', $windows['start'])
                ->where('records.from', '<', $windows['end'])
                ->groupBy('series.webpage_id')
                ->selectRaw('series.webpage_id')
                ->selectRaw('coalesce(sum(records.visitors) filter (where records.from >= ?), 0) as visitors', [$windows['split']])
                ->selectRaw('coalesce(sum(records.visitors) filter (where records.from < ?), 0) as visitors_before', [$windows['split']])
                ->get()
                ->keyBy('webpage_id');
        }

        return $products->groupBy('master_product_id')->map(function ($pages) use ($traffic, $windows) {
            $rows           = $traffic->only($pages->pluck('family_webpage_id')->filter()->unique()->all());
            $visitors       = (float) $rows->sum('visitors');
            $visitorsBefore = (float) $rows->sum('visitors_before');

            return [
                'shops'                      => $pages->count(),
                'online'                     => $pages->where('online', true)->count(),
                'online_without_images'      => $pages->where('online', true)->where('without_images', true)->count(),
                'family_visitors'            => $windows ? (int) $visitors : null,
                'family_visitors_change_pct' => $windows && $visitorsBefore >= self::WEBSITE_MIN_VISITORS ? (int) round(100 * ($visitors / $visitorsBefore - 1)) : null,
            ];
        })->all();
    }

    /**
     * The last 90 days against the 90 before, ending on the newest day with recorded traffic, or null when the daily
     * traffic records miss too many days in either window to compare them.
     *
     * @return array{start: Carbon, split: Carbon, end: Carbon}|null
     */
    public function trafficWindows(): ?array
    {
        $latestDay = DB::table('webpage_time_series_records')->where('frequency', 'D')->where('visitors', '>', 0)->max('from');
        if (!$latestDay) {
            return null;
        }

        $end   = Carbon::parse($latestDay)->startOfDay()->addDay();
        $split = $end->copy()->subDays(90);
        $start = $end->copy()->subDays(180);

        $coverage = DB::table('webpage_time_series_records')
            ->where('frequency', 'D')
            ->where('visitors', '>', 0)
            ->where('from', '>=', $start)
            ->where('from', '<', $end)
            ->selectRaw('count(distinct "from"::date) filter (where "from" >= ?) as recent_days', [$split])
            ->selectRaw('count(distinct "from"::date) filter (where "from" < ?) as earlier_days', [$split])
            ->first();

        if ($coverage->recent_days < self::WEBSITE_MIN_TRAFFIC_DAYS || $coverage->earlier_days < self::WEBSITE_MIN_TRAFFIC_DAYS) {
            return null;
        }

        return ['start' => $start, 'split' => $split, 'end' => $end];
    }

    /**
     * Why staff rejected earlier tips on these products or their families, newest first.
     *
     * @param  array<int, int>  $masterAssetIds
     * @param  array<int, int>  $masterFamilyIds
     */
    public function staffFeedback(array $masterAssetIds, array $masterFamilyIds): Collection
    {
        return DB::table('master_asset_price_tips')
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_price_tips.master_asset_id')
            ->where('master_asset_price_tips.status', MasterAssetPriceTipStatusEnum::DISMISSED->value)
            ->whereNotNull('master_asset_price_tips.dismissed_reason')
            ->where('master_asset_price_tips.dismissed_at', '>', now()->subYear())
            ->where(fn ($query) => $query->whereIn('master_asset_price_tips.master_asset_id', $masterAssetIds)->orWhereIn('master_assets.master_family_id', $masterFamilyIds))
            ->orderByDesc('master_asset_price_tips.dismissed_at')
            ->limit(500)
            ->get(['master_asset_price_tips.master_asset_id', 'master_assets.master_family_id', 'master_assets.code', 'master_asset_price_tips.change', 'master_asset_price_tips.kind', 'master_asset_price_tips.dismissed_reason', 'master_asset_price_tips.dismissed_at']);
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
            ->get(['products.master_product_id', 'offers.name', DB::raw('coalesce(offers.start_at, offers.created_at) as started_at')])
            ->sortByDesc('started_at')
            ->groupBy('master_product_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->take(5)->values());
    }

    /**
     * The day staff last changed each master price themselves, when that is recent enough to keep tips away:
     * a price someone has just set is their decision, not something to second-guess the next morning.
     *
     * @param  array<int, int>  $masterAssetIds
     * @return Collection<int, string>
     */
    public function lastManualPriceChanges(array $masterAssetIds): Collection
    {
        return DB::table('audits')
            ->where('auditable_type', (new MasterAsset())->getMorphClass())
            ->whereIn('auditable_id', $masterAssetIds)
            ->where('event', 'updated')
            ->whereNotNull('user_id')
            ->where('created_at', '>', now()->subDays(self::MANUAL_CHANGE_QUIET_DAYS))
            ->whereRaw("jsonb_exists(new_values, 'price')")
            ->groupBy('auditable_id')
            ->selectRaw('auditable_id, max(created_at) as changed_at')
            ->pluck('changed_at', 'auditable_id')
            ->map(fn ($changedAt) => Carbon::parse($changedAt)->toDateString());
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
     * ponytail: fixed thresholds for the whole catalogue, set on 10 Oct 2026 so about 500 of 20,000 products
     * are flagged; move them per master shop only if one shop's flags turn out too many or too few to be read.
     */
    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        MasterAssetPriceTip::where('status', MasterAssetPriceTipStatusEnum::OPEN)
            ->whereHas('masterAsset', fn ($query) => $query->where('status', false))
            ->update(['status' => MasterAssetPriceTipStatusEnum::EXPIRED, 'expired_at' => now()]);

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
                    ->where('status', MasterAssetPriceTipStatusEnum::DISMISSED)
                    ->where('dismissed_at', '>', now()->subDays(self::DISMISSED_QUIET_DAYS)))
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
