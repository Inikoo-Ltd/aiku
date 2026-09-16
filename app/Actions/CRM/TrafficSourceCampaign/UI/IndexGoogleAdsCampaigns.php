<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Http\Resources\CRM\GoogleAdsCampaignsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCampaignConversion;
use App\Models\CRM\TrafficSourceCampaignMetric;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexGoogleAdsCampaigns extends OrgAction
{
    use WithGoogleAdsInterval;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('traffic_source_campaigns.name', $value)
                    ->orWhereAnyWordStartWith('traffic_source_campaigns.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TrafficSourceCampaign::class);
        $queryBuilder
            ->join('traffic_sources', 'traffic_sources.id', '=', 'traffic_source_campaigns.traffic_source_id')
            ->where('traffic_sources.shop_id', $shop->id)
            ->where('traffic_sources.type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->leftJoinSub($this->periodMetrics(), 'metrics', 'metrics.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id')
            ->leftJoinSub($this->periodCosts(), 'costs', 'costs.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id')
            ->leftJoinSub($this->periodConversions(), 'conversions', 'conversions.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id');

        $selectFields = [
            'traffic_source_campaigns.id',
            'traffic_source_campaigns.slug',
            'traffic_source_campaigns.reference',
            'traffic_source_campaigns.name',
            'traffic_source_campaigns.channel_type',
            'traffic_source_campaigns.state',
            DB::raw("traffic_source_campaigns.data->>'currency' as currency_code"),
            DB::raw("(traffic_source_campaigns.data->>'budget_amount')::numeric as budget_amount"),

            /* primary_status, not status: a campaign reads ENABLED while showing nobody anything
               because its budget ran out or its ads are still in review, and that is precisely what
               somebody opening this page needs to see. Falls back for rows fetched before Google
               started reporting it. */
            DB::raw("COALESCE(traffic_source_campaigns.data->>'primary_status', traffic_source_campaigns.data->>'status') as status"),
        ];

        $selectFields = array_merge($selectFields, $this->metricFields());

        /* The same three subqueries again over the period before, every figure suffixed _previous,
           so a row carries both sides of the comparison and the resource works out the change. */
        if ($this->isComparing()) {
            $queryBuilder
                ->leftJoinSub($this->periodMetrics(true), 'metrics_previous', 'metrics_previous.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id')
                ->leftJoinSub($this->periodCosts(true), 'costs_previous', 'costs_previous.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id')
                ->leftJoinSub($this->periodConversions(true), 'conversions_previous', 'conversions_previous.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id');

            $selectFields = array_merge($selectFields, $this->metricFields(self::PREVIOUS_SUFFIX));
        }

        return $queryBuilder
            ->select($selectFields)
            ->defaultSort('-spend')
            ->allowedSorts(array_merge(
                [
                    'name',
                    'status',
                    'channel_type',
                    'budget_amount',
                    'impressions',
                    'clicks',
                    'ctr',
                    'avg_cpc',
                    'conversions',
                    'cost_per_conversion',
                    'conversions_value',
                    'spend',
                    'roas',
                    'all_conversions',
                    'all_conversions_value',
                ],
                array_keys(self::BREAKDOWN_COLUMNS),
                TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS,
            ))
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public const string PREVIOUS_SUFFIX = '_previous';

    /**
     * Every figure the table can show, in the order the resource emits them.
     */
    public const array METRIC_KEYS = [
        'impressions',
        'clicks',
        'ctr',
        'avg_cpc',
        'spend',
        'conversions',
        'cost_per_conversion',
        'conversions_value',
        'roas',
        'all_conversions',
        'all_conversions_value',
        'purchases',
        'cost_per_purchase',
        'purchase_rate',
        'registrations',
        'cost_per_registration',
        'registration_rate',
        ...TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS,
    ];

    /**
     * The metric columns, read from the subqueries whose aliases carry the same suffix, so one list
     * of formulas serves both the chosen period and the one before it.
     *
     * @return array<int, \Illuminate\Database\Query\Expression>
     */
    private function metricFields(string $suffix = ''): array
    {
        $metrics = 'metrics'.$suffix;
        $costs   = 'costs'.$suffix;

        $fields = [
            DB::raw("COALESCE({$metrics}.impressions, 0) as impressions{$suffix}"),
            DB::raw("COALESCE({$metrics}.clicks, 0) as clicks{$suffix}"),
            DB::raw("COALESCE({$metrics}.conversions, 0) as conversions{$suffix}"),

            /* Spend is read from traffic_source_costs rather than from the metrics rows, because that
               is the table the rest of the marketing dashboard totals and it is already converted into
               the shop's currency. The metrics copy stays in the account's currency, for reconciling
               against, never for showing beside shop-currency figures. */
            DB::raw("COALESCE({$costs}.spend, 0) as spend{$suffix}"),

            /* Null, never zero, wherever the denominator is missing: with no impressions the question
               "what share were clicked" has no answer, and a zero would read as the answer "none".
               The table prints a dash for null. */
            DB::raw("CASE WHEN COALESCE({$metrics}.impressions, 0) > 0
                        THEN ROUND({$metrics}.clicks::numeric * 100 / {$metrics}.impressions, 2)
                    END as ctr{$suffix}"),
            DB::raw("CASE WHEN COALESCE({$metrics}.clicks, 0) > 0
                        THEN ROUND({$metrics}.source_cost / {$metrics}.clicks, 2)
                    END as avg_cpc{$suffix}"),
            DB::raw("CASE WHEN COALESCE({$metrics}.conversions, 0) > 0
                        THEN ROUND({$metrics}.source_cost / {$metrics}.conversions, 2)
                    END as cost_per_conversion{$suffix}"),
            DB::raw("COALESCE({$metrics}.source_conversions_value, 0) as conversions_value{$suffix}"),

            /* Both halves are Google's own, in the account's own currency, so the ratio is internally
               consistent. It is Google's attribution and not Aiku's; the campaign page puts the two
               next to each other and labels which is which. */
            DB::raw("CASE WHEN COALESCE({$metrics}.source_cost, 0) > 0
                        THEN ROUND({$metrics}.source_conversions_value / {$metrics}.source_cost, 2)
                    END as roas{$suffix}"),
        ];

        return array_merge($fields, $this->breakdownFields($suffix), $this->impressionShareFields($suffix));
    }

    /**
     * Impression share is a ratio per day, so the period figure is weighted by the day's eligible
     * impressions, which Google does not send but which is impressions divided by the share received.
     * Days without a share, every campaign type outside Search and Shopping, weigh nothing.
     */
    private function periodMetrics(bool $previous = false): Builder
    {
        $columns = [
            'traffic_source_campaign_id',
            DB::raw('SUM(impressions) as impressions'),
            DB::raw('SUM(clicks) as clicks'),
            DB::raw('SUM(conversions) as conversions'),
            DB::raw('SUM(source_cost) as source_cost'),
            DB::raw('SUM(source_conversions_value) as source_conversions_value'),
            DB::raw('SUM(all_conversions) as all_conversions'),
            DB::raw('SUM(source_all_conversions_value) as source_all_conversions_value'),
            DB::raw('SUM(CASE WHEN search_impression_share > 0 THEN impressions / search_impression_share END) as eligible_impressions'),
        ];

        foreach (TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS as $column) {
            $columns[] = DB::raw("SUM(CASE WHEN search_impression_share > 0 THEN impressions / search_impression_share * {$column} END) as weighted_{$column}");
        }

        $metrics = DB::table('traffic_source_campaign_metrics')
            ->select($columns)
            ->groupBy('traffic_source_campaign_id');

        return $this->wherePeriodOrPrevious($metrics, 'date', $previous);
    }

    private function periodConversions(bool $previous = false): Builder
    {
        $purchase = TrafficSourceCampaignConversion::CATEGORY_PURCHASE;
        $signup   = TrafficSourceCampaignConversion::CATEGORY_SIGNUP;

        $conversions = DB::table('traffic_source_campaign_conversions')
            ->select(
                'traffic_source_campaign_id',
                DB::raw("SUM(CASE WHEN category = '{$purchase}' THEN conversions ELSE 0 END) as purchases"),
                DB::raw("SUM(CASE WHEN category = '{$signup}' THEN conversions ELSE 0 END) as registrations"),
            )
            ->groupBy('traffic_source_campaign_id');

        return $this->wherePeriodOrPrevious($conversions, 'date', $previous);
    }

    /**
     * Column key => [count column in the conversions subquery, derived from that count].
     */
    private const array BREAKDOWN_COLUMNS = [
        'purchases'             => ['purchases', 'count'],
        'cost_per_purchase'     => ['purchases', 'cost'],
        'purchase_rate'         => ['purchases', 'rate'],
        'registrations'         => ['registrations', 'count'],
        'cost_per_registration' => ['registrations', 'cost'],
        'registration_rate'     => ['registrations', 'rate'],
    ];

    /**
     * A count is null, never zero, for a campaign that converted before the split by conversion action
     * was first read: zero would say "no purchases" about days whose purchases are simply unknown.
     * Once the nightly fetch has covered the period, a missing row is a real zero, because Google
     * leaves out actions that recorded nothing.
     *
     * @return array<int, \Illuminate\Database\Query\Expression>
     */
    private function breakdownFields(string $suffix = ''): array
    {
        $metrics     = 'metrics'.$suffix;
        $conversions = 'conversions'.$suffix;
        $known       = "({$conversions}.traffic_source_campaign_id IS NOT NULL OR COALESCE({$metrics}.conversions, 0) = 0)";

        $fields = [
            DB::raw("COALESCE({$metrics}.all_conversions, 0) as all_conversions{$suffix}"),
            DB::raw("COALESCE({$metrics}.source_all_conversions_value, 0) as all_conversions_value{$suffix}"),
        ];

        foreach (self::BREAKDOWN_COLUMNS as $key => [$count, $kind]) {
            $fields[] = DB::raw(match ($kind) {
                'count' => "CASE WHEN {$known} THEN COALESCE({$conversions}.{$count}, 0) END as {$key}{$suffix}",
                'cost'  => "CASE WHEN COALESCE({$conversions}.{$count}, 0) > 0 THEN ROUND({$metrics}.source_cost / {$conversions}.{$count}, 2) END as {$key}{$suffix}",
                'rate'  => "CASE WHEN {$known} AND COALESCE({$metrics}.clicks, 0) > 0 THEN ROUND(COALESCE({$conversions}.{$count}, 0) * 100 / {$metrics}.clicks, 2) END as {$key}{$suffix}",
            });
        }

        return $fields;
    }

    /**
     * @return array<int, \Illuminate\Database\Query\Expression>
     */
    private function impressionShareFields(string $suffix = ''): array
    {
        $metrics = 'metrics'.$suffix;

        return array_map(
            fn (string $column) => DB::raw("CASE WHEN COALESCE({$metrics}.eligible_impressions, 0) > 0 THEN ROUND({$metrics}.weighted_{$column} * 100 / {$metrics}.eligible_impressions, 2) END as {$column}{$suffix}"),
            TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS
        );
    }

    private function periodCosts(bool $previous = false): Builder
    {
        $costs = DB::table('traffic_source_costs')
            ->select('traffic_source_campaign_id', DB::raw('SUM(amount) as spend'))
            ->whereNotNull('traffic_source_campaign_id')
            ->groupBy('traffic_source_campaign_id');

        return $this->wherePeriodOrPrevious($costs, 'date', $previous);
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withColumnChooser()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title'       => __('No campaigns read from Google Ads yet'),
                    'description' => __('The nightly fetch reads this shop\'s account at 05:00 UTC. Campaigns and their daily figures appear here after it has run once.'),
                ]);

            /* Status leads, as an icon: in a list of forty campaigns the first question is which ones
               are not serving, and a column of words that mostly read "Eligible" answers it slower
               than a column of shapes. The tooltip carries Google's own wording. */
            $table
                ->column(key: 'status', label: '', icon: 'fal fa-signal-stream', tooltip: __('Whether Google is showing this campaign'), type: 'icon', canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Campaign'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'channel_type', label: __('Type'), tooltip: __('Campaign type: Search, Performance Max, Demand Gen, Display, Shopping or Video'), canBeHidden: true, sortable: true, tooltipIcon: true);

            /* Every figure is a number, so every one of these is right aligned; the two columns above
               hold words and stay left. */
            foreach ($this->metricColumns() as $key => $column) {
                $table->column(
                    key: $key,
                    label: $column['label'] ?? '',
                    icon: $column['icon'] ?? null,
                    tooltip: $column['tooltip'],
                    canBeHidden: true,
                    hidden: $column['hidden'] ?? false,
                    sortable: true,
                    align: 'right',

                    /* The question mark only earns its place beside a word. Beside an icon it would be
                       a second glyph explaining the first, and the tooltip already covers the cell. */
                    tooltipIcon: !isset($column['icon']),
                );
            }
        };
    }

    /**
     * Every figure the table can show, in column order.
     *
     * An icon replaces the label only where the icon is unmistakable and the words were long enough
     * to widen the column past the number under it. Rates, ratios and the impression share family keep
     * their words: nine share metrics drawn as nine icons would be a rebus, and the wording is the
     * marketing team's own.
     *
     * The long tail starts hidden. The column chooser is where a marketer switches on what they read,
     * and a table thirty columns wide answers nothing at a glance.
     *
     * @return array<string, array{label?: string, icon?: string, tooltip: string, hidden?: bool}>
     */
    private function metricColumns(): array
    {
        return [
            'impressions'           => ['icon' => 'fal fa-eye', 'tooltip' => __('Impressions')],
            'clicks'                => ['icon' => 'fal fa-hand-pointer', 'tooltip' => __('Clicks')],
            'ctr'                   => ['label' => __('CTR'), 'tooltip' => __('Clicks as a share of impressions')],
            'avg_cpc'               => ['label' => __('CPC'), 'tooltip' => __('Average cost per click, in the ad account\'s currency')],
            'budget_amount'         => ['icon' => 'fal fa-wallet', 'tooltip' => __('Daily budget, in the ad account\'s currency')],
            'spend'                 => ['label' => __('Spend'), 'tooltip' => __('What this campaign cost, converted to the shop\'s currency at each day\'s rate')],
            'conversions'           => ['icon' => 'fal fa-bullseye-arrow', 'tooltip' => __('Conversions from the account\'s primary conversion actions, as Google counts them')],
            'cost_per_conversion'   => ['label' => __('Cost/conv.'), 'tooltip' => __('Cost divided by conversions, in the ad account\'s currency')],
            'conversions_value'     => ['icon' => 'fal fa-sack-dollar', 'tooltip' => __('Conversion value Google recorded, in the ad account\'s currency')],
            'roas'                  => ['label' => __('ROAS'), 'tooltip' => __('Conversion value divided by cost, both Google\'s own figures')],

            'all_conversions'       => ['icon' => 'fal fa-bullseye', 'tooltip' => __('Every conversion action in the account, primary and secondary. Conversions counts only the primary ones.'), 'hidden' => true],
            'all_conversions_value' => ['label' => __('All value'), 'tooltip' => __('Value Google recorded across every conversion action, in the ad account\'s currency.'), 'hidden' => true],
            'purchases'             => ['icon' => 'fal fa-shopping-cart', 'tooltip' => __('Conversions from primary actions Google categorises as Purchase, the same ones its Conversions column counts. Secondary actions such as a GA4 import of the same sales are left out so a sale is not counted twice. A dash means the split by action has not been read for these days yet.')],
            'cost_per_purchase'     => ['label' => __('Cost/purch.'), 'tooltip' => __('Cost divided by purchases, in the ad account\'s currency.'), 'hidden' => true],
            'purchase_rate'         => ['label' => __('Purch. rate'), 'tooltip' => __('Purchases as a share of clicks.'), 'hidden' => true],
            'registrations'         => ['icon' => 'fal fa-user-plus', 'tooltip' => __('Conversions from primary actions Google categorises as Sign-up, the same ones its Conversions column counts. A dash means the split by action has not been read for these days yet.')],
            'cost_per_registration' => ['label' => __('Cost/reg.'), 'tooltip' => __('Cost divided by registrations, in the ad account\'s currency.'), 'hidden' => true],
            'registration_rate'     => ['label' => __('Reg. rate'), 'tooltip' => __('Registrations as a share of clicks.'), 'hidden' => true],

            'search_impression_share'                          => ['label' => __('Search IS'), 'tooltip' => __('Impressions received as a share of those the campaign was eligible for on Google Search, weighted across the period by eligible impressions. Reported only for campaigns that run on Google Search. Google reports anything under 10% as 9.99% and anything over 90% as 90.01%, shown here as under 10% and over 90%.')],
            'search_rank_lost_impression_share'                => ['label' => __('Lost IS (rank)'), 'tooltip' => __('Share of eligible Search impressions missed because the Ad Rank was too low.'), 'hidden' => true],
            'search_budget_lost_impression_share'              => ['label' => __('Lost IS (budget)'), 'tooltip' => __('Share of eligible Search impressions missed because the budget had run out.'), 'hidden' => true],
            'search_top_impression_share'                      => ['label' => __('Top IS'), 'tooltip' => __('Share of eligible impressions shown anywhere above the organic results.'), 'hidden' => true],
            'search_rank_lost_top_impression_share'            => ['label' => __('Lost top IS (rank)'), 'tooltip' => __('Share of eligible impressions above the organic results missed because the Ad Rank was too low.'), 'hidden' => true],
            'search_budget_lost_top_impression_share'          => ['label' => __('Lost top IS (budget)'), 'tooltip' => __('Share of eligible impressions above the organic results missed because the budget had run out.'), 'hidden' => true],
            'search_absolute_top_impression_share'             => ['label' => __('Abs. top IS'), 'tooltip' => __('Share of eligible impressions shown in the very first position.'), 'hidden' => true],
            'search_rank_lost_absolute_top_impression_share'   => ['label' => __('Lost abs. top IS (rank)'), 'tooltip' => __('Share of eligible first-position impressions missed because the Ad Rank was too low.'), 'hidden' => true],
            'search_budget_lost_absolute_top_impression_share' => ['label' => __('Lost abs. top IS (budget)'), 'tooltip' => __('Share of eligible first-position impressions missed because the budget had run out.'), 'hidden' => true],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $campaigns, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaigns',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Google Ads'),
                'pageHead'    => [
                    'title' => $this->shop->name,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-google'],
                        'title' => __('Google Ads'),
                    ],
                    'model' => __('Google Ads'),

                    /* Offered only once the account can actually be reached: a create form that can
                       only fail wastes the time of whoever fills it in. */
                    'actions' => GoogleAdsClient::unreachableReason($this->shop) ? [] : [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New campaign'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.google_ads.create',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],

                /* A sentence rather than a boolean: "not connected to a Google account", "no Customer
                   ID set" and "no developer token on this installation" are three different jobs for
                   three different people, and one flag sent whoever read the page off to reconnect an
                   account that was connected all along. */
                'unreachable_reason' => GoogleAdsClient::unreachableReason($this->shop),
                'last_fetched_at'    => $this->lastFetchedAt(),
                'shop_currency'      => $this->shop->currency->code,
                'settings_route'     => [
                    'name'       => 'grp.org.shops.show.settings.edit',
                    'parameters' => $request->route()->originalParameters(),
                ],
                ...$this->periodProps(),
                'data' => GoogleAdsCampaignsResource::collection($campaigns),
            ]
        )->table($this->tableStructure());
    }

    /**
     * When this shop's account was last read, so the page can say how old its figures are instead of
     * presenting a fortnight-old snapshot as though it were this morning's.
     */
    private function lastFetchedAt(): ?string
    {
        return DB::table('traffic_source_campaigns')
            ->join('traffic_sources', 'traffic_sources.id', '=', 'traffic_source_campaigns.traffic_source_id')
            ->where('traffic_sources.shop_id', $this->shop->id)
            ->where('traffic_sources.type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->max(DB::raw("traffic_source_campaigns.data->>'fetched_at'"));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.google_ads.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Google Ads'),
                        'icon'  => 'fab fa-google',
                    ],
                ],
            ],
        );
    }
}
