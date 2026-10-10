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
 * Nightly price tips (HELP-2331). For each master product whose stock, averaged over the
 * organisations by what each sold in the last year, is too high or running out, Jev is shown its
 * sales, stock, incoming goods, stock-outs, margin, family prices, running offers and past price
 * changes, and picks a change. New lines (no sales a year ago) are judged once on sale for
 * NEW_PRODUCT_MIN_DAYS, against their family and competitors. Jev only picks; the guard rails decide:
 * the direction must match the stock, a markdown never goes below cost x 1.25 and never follows a
 * drop Jev thinks is temporary. Only confident, non-hold picks are kept.
 *
 * Every product checked keeps the outcome of its last check, so a product without a tip says why. Jev is asked
 * about a product at most once every RECHECK_DAYS, sooner only when its price, stock direction, offers or the
 * staff feedback on it or its family change.
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

    public const int NEW_PRODUCT_MIN_DAYS = 60;

    public const int RECHECK_DAYS = 7;

    public const int MINIMUM_CHANGE = 3;

    public const int DISMISSED_QUIET_DAYS = 30;

    public const int MANUAL_CHANGE_QUIET_DAYS = 30;

    public const float WEBSITE_MIN_ONLINE_SHARE = 0.8;

    public const int WEBSITE_VISITORS_DROP_PCT = -25;

    public const int WEBSITE_MIN_VISITORS = 50;

    public const int WEBSITE_MIN_TRAFFIC_DAYS = 80;

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

        $direction   = static::direction($signals);
        $fingerprint = $direction ? static::fingerprint($signals) : null;

        if ($fingerprint && static::askedRecently($masterAsset, $fingerprint)) {
            return $openTip;
        }

        $answers = $direction ? $this->ask($signals) : [];

        if ($answers === null) {
            return $openTip;
        }

        $verdict  = static::verdict($signals, $answers);
        $decision = $verdict['decision'];
        $this->recordCheck($masterAsset, $fingerprint ? [
            ...$verdict['check'],
            'fingerprint'    => $fingerprint,
            'cover'          => $signals['cover'],
            'probabilities'  => array_map('floatval', (array) Arr::get($answers, 'change.probabilities', [])),
            'temporary_drop' => Arr::get($answers, 'temporary_drop.noul'),
        ] : $verdict['check']);

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
     * What makes Jev's answer go stale: the price, which way the stock points, the offers running and staff
     * feedback. Sales and stock drift slowly and are picked up by the weekly recheck.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function fingerprint(array $signals): string
    {
        return md5(json_encode([
            round((float) ($signals['price'] ?? 0), 2),
            static::direction($signals),
            $signals['offers'] ?? [],
            $signals['staff_feedback'] ?? [],
        ]));
    }

    public static function askedRecently(MasterAsset $masterAsset, string $fingerprint): bool
    {
        $stats = MasterAssetStats::where('master_asset_id', $masterAsset->id)->first(['price_tip_check', 'price_tip_checked_at']);

        return $stats
            && data_get($stats->price_tip_check, 'fingerprint') === $fingerprint
            && $stats->price_tip_checked_at?->gt(now()->subDays(self::RECHECK_DAYS));
    }

    /**
     * @param  array{outcome: string, text: string, fingerprint?: string, cover?: float, probabilities?: array<string, float>, temporary_drop?: mixed}  $check
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
     * Why a product is not put to Jev at all, or null when it is.
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
            $cover < self::OVERSTOCK_DAYS && !($cover > 0 && $cover < self::SHORT_DAYS) => [
                'outcome' => 'stock_balanced',
                'text'    => __('No tip: stock cover is normal (:days days)', ['days' => (int) $cover]),
            ],
            default => null,
        };
    }

    /**
     * Too much stock allows a markdown, stock running out a markup, anything else no tip. Stock is averaged
     * over the organisations by what each sold in the last year, as the price is the same in all of them.
     *
     * @param  array<string, mixed>  $signals
     */
    public static function direction(array $signals): ?int
    {
        if (static::precheck($signals)) {
            return null;
        }

        return $signals['cover'] >= self::OVERSTOCK_DAYS ? -1 : 1;
    }

    /**
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>|null  $answers
     * @return array{change: int, confidence: float, probabilities: array<string, float>, temporary_drop_probability: float|null, capped: bool}|null
     */
    public static function decide(array $signals, ?array $answers): ?array
    {
        return static::verdict($signals, $answers)['decision'];
    }

    /**
     * The tip kept, or null, with the outcome of the check staff see next to the product. Jev spreads its
     * vote over several sizes of cut or rise, so the sizes pointing the way the stock does are added up
     * before asking whether it is sure enough, and the most likely of them is the size tipped.
     *
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>|null  $answers
     * @return array{decision: array{change: int, confidence: float, probabilities: array<string, float>, temporary_drop_probability: float|null, capped: bool}|null, check: array{outcome: string, text: string}}
     */
    public static function verdict(array $signals, ?array $answers): array
    {
        $noTip = fn (string $outcome, string $text) => ['decision' => null, 'check' => ['outcome' => $outcome, 'text' => $text]];

        if ($precheck = static::precheck($signals)) {
            return ['decision' => null, 'check' => $precheck];
        }

        $direction     = static::direction($signals);
        $choice        = (string) Arr::get($answers, 'change.choice');
        $probabilities = array_map('floatval', (array) Arr::get($answers, 'change.probabilities', []));
        $temporaryDrop = Arr::get($answers, 'temporary_drop.noul');
        $temporaryDrop = is_numeric($temporaryDrop) ? (float) $temporaryDrop : null;

        $withStock = array_filter(
            $probabilities ?: [$choice => (float) Arr::get($answers, 'change.confidence', 0)],
            fn (string $option) => (self::OPTIONS[$option] ?? 0) * $direction > 0,
            ARRAY_FILTER_USE_KEY
        );
        uksort($withStock, fn (string $a, string $b) => [$withStock[$b], abs(self::OPTIONS[$a])] <=> [$withStock[$a], abs(self::OPTIONS[$b])]);
        $change     = self::OPTIONS[array_key_first($withStock)] ?? 0;
        $confidence = min(1.0, round(array_sum($withStock), 4));
        $sure       = (int) round(100 * $confidence);
        $keep       = (float) ($probabilities['hold'] ?? 0);

        if ($change === 0 || ($confidence < self::MIN_CONFIDENCE && $confidence <= $keep)) {
            return $noTip('ai_hold', __('No tip: the AI keeps the price (:pct% sure)', ['pct' => (int) round(100 * max($keep, (float) ($probabilities[$choice] ?? 0)))]));
        }

        if ($confidence < self::MIN_CONFIDENCE) {
            return $noTip('unsure', __('No tip: the AI is only :pct% sure of :change%', ['pct' => $sure, 'change' => ($change > 0 ? '+' : '').$change]));
        }

        $capped = false;
        if ($change < 0) {
            if (!static::websiteIsHealthy($signals['website'] ?? null)) {
                return $noTip('website', __('No tip: :reason', ['reason' => static::websiteReason($signals['website'])]));
            }

            if (!($signals['new'] ?? false) && ($temporaryDrop === null || $temporaryDrop >= self::TEMPORARY_DROP)) {
                return $noTip('temporary_drop', __('No tip: the fall in sales looks temporary (:pct% likely)', ['pct' => (int) round(100 * ($temporaryDrop ?? 1))]));
            }

            if ($signals['cost'] ?? null) {
                $floor = -(int) floor(100 * (1 - $signals['cost'] * self::MINIMUM_MARKUP_OVER_COST / $signals['price']));
                if ($floor > $change) {
                    $change = $floor;
                    $capped = true;
                }
            }

            if (-$change < self::MINIMUM_CHANGE) {
                return $noTip('cost_floor', __('No tip: the price is already close to cost + 25%'));
            }
        }

        return [
            'decision' => [
                'change'                     => $change,
                'confidence'                 => round($confidence, 4),
                'probabilities'              => $probabilities,
                'temporary_drop_probability' => $temporaryDrop !== null ? round($temporaryDrop, 4) : null,
                'capped'                     => $capped,
            ],
            'check'    => ['outcome' => 'tip', 'text' => ''],
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
                'instructions' => 'This product is sold wholesale to trade customers at one price in every country. Pick the price change most likely to earn the most gross profit over the next three months. Weigh stock cover and goods on order, the sales trend against last year and the season, days out of stock (lost sales, not lost demand), the margin, the price of similar products in the family, offers already running, how sales reacted to past price changes, and what competitors charge per unit (difference_pct below 0 means they are cheaper; a competitor selling to shoppers is compared with our recommended retail price). website says whether the product pages are online with images in every shop and, when known, how visitors to its family pages moved (last 90 days against the 90 before): when the pages are fine and family visitors hold up but this product sells less, the price is the likely cause; when pages are offline or family visitors fall, the website is the cause, not the price. family puts the product among its family over the last 12 months: its rank and share of family sales, and family sales against last year next to the product\'s own trend. Sales are supply and demand inside the family: a product falling behind a family that holds up is losing to its siblings and a cut can win it back; a product carrying the family, or growing faster than it, can take a higher price. staff_feedback lists earlier tips on this product or its family that staff rejected and why; do not repeat a change for a reason they gave. new is true for a product with no sales a year ago: there is no last year to compare with, so judge it on its sales over its days_on_sale against the rest of its family, and on its price against the family median and competitors. cover is the stock in days, averaged over the organisations by what each sold in the last year.',
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
            ? __('Stock for :days days, averaged by what each organisation sells', ['days' => $signals['cover'] >= 730 ? '730+' : (int) $signals['cover']])
            : __('Stock runs out in :days days, averaged by what each organisation sells', ['days' => (int) ceil($signals['cover'])]);

        $trend = null;
        if ($signals['new'] ?? false) {
            $parts[] = __('new, on sale for :days days', ['days' => (int) $signals['days_on_sale']]);
        } else {
            $trend   = (int) round(100 * ($signals['sales'] / $signals['sales_last_year'] - 1));
            $parts[] = $trend >= 0
                ? __('sales up :pct% on last year', ['pct' => $trend])
                : __('sales down :pct% on last year', ['pct' => -$trend]);
        }

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
            ->select(['id', 'code', 'name', 'units', 'master_prices', 'effective_cost', 'master_family_id'])
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
                        'tip_change_pct' => (int) $row->change,
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
            ->get(['master_asset_price_tips.master_asset_id', 'master_assets.master_family_id', 'master_assets.code', 'master_asset_price_tips.change', 'master_asset_price_tips.dismissed_reason', 'master_asset_price_tips.dismissed_at']);
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
