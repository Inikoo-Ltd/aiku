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
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use App\Actions\CRM\TrafficSource\GetTrafficSourceAudienceMix;
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
            ->leftJoinSub($this->periodCosts(), 'costs', 'costs.traffic_source_campaign_id', '=', 'traffic_source_campaigns.id');

        $selectFields = [
            'traffic_source_campaigns.id',
            'traffic_source_campaigns.slug',
            'traffic_source_campaigns.reference',
            'traffic_source_campaigns.name',
            'traffic_source_campaigns.channel_type',
            DB::raw("traffic_source_campaigns.data->>'currency' as currency_code"),
            DB::raw("(traffic_source_campaigns.data->>'budget_amount')::numeric as budget_amount"),

            /* primary_status, not status: a campaign reads ENABLED while showing nobody anything
               because its budget ran out or its ads are still in review, and that is precisely what
               somebody opening this page needs to see. Falls back for rows fetched before Google
               started reporting it. */
            DB::raw("COALESCE(traffic_source_campaigns.data->>'primary_status', traffic_source_campaigns.data->>'status') as status"),

            DB::raw('COALESCE(metrics.impressions, 0) as impressions'),
            DB::raw('COALESCE(metrics.clicks, 0) as clicks'),
            DB::raw('COALESCE(metrics.conversions, 0) as conversions'),

            /* Spend is read from traffic_source_costs rather than from the metrics rows, because that
               is the table the rest of the marketing dashboard totals and it is already converted into
               the shop's currency. The metrics copy stays in the account's currency, for reconciling
               against, never for showing beside shop-currency figures. */
            DB::raw('COALESCE(costs.spend, 0) as spend'),

            /* Null, never zero, wherever the denominator is missing: with no impressions the question
               "what share were clicked" has no answer, and a zero would read as the answer "none".
               The table prints a dash for null. */
            DB::raw('CASE WHEN COALESCE(metrics.impressions, 0) > 0
                        THEN ROUND(metrics.clicks::numeric * 100 / metrics.impressions, 2)
                    END as ctr'),
            DB::raw('CASE WHEN COALESCE(metrics.clicks, 0) > 0
                        THEN ROUND(metrics.source_cost / metrics.clicks, 2)
                    END as avg_cpc'),

            /* Both halves are Google's own, in the account's own currency, so the ratio is internally
               consistent. It is Google's attribution and not Aiku's; the campaign page puts the two
               next to each other and labels which is which. */
            DB::raw('CASE WHEN COALESCE(metrics.source_cost, 0) > 0
                        THEN ROUND(metrics.source_conversions_value / metrics.source_cost, 2)
                    END as roas'),
        ];

        return $queryBuilder
            ->select($selectFields)
            ->defaultSort('-spend')
            ->allowedSorts([
                'name',
                'status',
                'channel_type',
                'budget_amount',
                'impressions',
                'clicks',
                'ctr',
                'avg_cpc',
                'conversions',
                'spend',
                'roas',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function periodMetrics(): Builder
    {
        $metrics = DB::table('traffic_source_campaign_metrics')
            ->select(
                'traffic_source_campaign_id',
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(conversions) as conversions'),
                DB::raw('SUM(source_cost) as source_cost'),
                DB::raw('SUM(source_conversions_value) as source_conversions_value'),
            )
            ->groupBy('traffic_source_campaign_id');

        return $this->interval()->wherePeriod($metrics, 'date');
    }

    private function periodCosts(): Builder
    {
        $costs = DB::table('traffic_source_costs')
            ->select('traffic_source_campaign_id', DB::raw('SUM(amount) as spend'))
            ->whereNotNull('traffic_source_campaign_id')
            ->groupBy('traffic_source_campaign_id');

        return $this->interval()->wherePeriod($costs, 'date');
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title'       => __('No campaigns read from Google Ads yet'),
                    'description' => __('The nightly fetch reads this shop\'s account at 05:00 UTC. Campaigns and their daily figures appear here after it has run once.'),
                ]);

            $table
                ->column(key: 'name', label: __('Campaign'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'channel_type', label: __('Channel'), canBeHidden: true, sortable: true)
                ->column(key: 'impressions', label: __('Impressions'), canBeHidden: true, sortable: true, align: 'right')
                ->column(key: 'clicks', label: __('Clicks'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'ctr', label: __('CTR'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'avg_cpc', label: __('Avg. CPC'), canBeHidden: true, sortable: true, align: 'right')
                ->column(key: 'budget_amount', label: __('Daily budget'), canBeHidden: true, sortable: true, align: 'right')
                ->column(key: 'spend', label: __('Spend'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'conversions', label: __('Conversions'), canBeHidden: true, sortable: true, align: 'right')
                ->column(key: 'roas', label: __('ROAS'), canBeHidden: false, sortable: true, align: 'right');
        };
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

                /* The account-wide split, above the per-campaign table so the first question the page
                   answers is who the money reached rather than how many campaigns there are. */
                'audience'    => Inertia::defer(fn () => GetTrafficSourceAudienceMix::run(
                    $this->shop,
                    TrafficSourcesTypeEnum::GOOGLE_ADS->value
                )),
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
                'periods' => $this->intervalOptions(),
                'period'  => $this->interval()->value,
                'data'    => GoogleAdsCampaignsResource::collection($campaigns),
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
