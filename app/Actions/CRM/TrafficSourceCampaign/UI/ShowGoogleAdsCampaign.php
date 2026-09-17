<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\CRM\TrafficSource\GetTrafficSourceAudienceMix;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\GetGoogleAdsSearchTerms;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsCampaign;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsImage;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\UpdateInProcessGoogleAdsCampaign;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Models\Helpers\Media;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCampaignConversion;
use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Models\CRM\TrafficSourceCampaignMetric;
use Illuminate\Support\Arr;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowGoogleAdsCampaign extends OrgAction
{
    use WithGoogleAdsInterval;

    public function handle(TrafficSourceCampaign $trafficSourceCampaign): TrafficSourceCampaign
    {
        return $trafficSourceCampaign;
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($shop, $request);

        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        return $this->handle($trafficSourceCampaign);
    }

    public function htmlResponse(TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): Response
    {
        $data     = $trafficSourceCampaign->data ?? [];
        $currency = $data['currency'] ?? $this->shop->currency->code;

        /* A campaign that exists only in Aiku has no figures, no ad groups read back from Google and
           nothing to compare periods over. Showing it through the same page would be a wall of dashes,
           so it gets its own, which is about finishing it rather than reading it. */
        if ($trafficSourceCampaign->state->isInProcess()) {
            return $this->inProcessResponse($trafficSourceCampaign, $request);
        }

        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaign',
            [
                'breadcrumbs' => $this->getBreadcrumbs($trafficSourceCampaign, $request->route()->originalParameters()),
                'title'       => $trafficSourceCampaign->name,
                'pageHead'    => [
                    'title'   => $trafficSourceCampaign->name,
                    'icon'    => [
                        'icon'  => ['fab', 'fa-google'],
                        'title' => __('Google Ads campaign'),
                    ],
                    'model'   => GoogleAdsCampaignStateEnum::labels()[$trafficSourceCampaign->state->value],
                    'actions' => $this->stateActions($trafficSourceCampaign),
                ],

                /* Deferred: the page is about the campaign's own figures and those are already stored,
                   whereas this walks the click table and the visitor history behind it. */
                'audience' => Inertia::defer(fn () => GetTrafficSourceAudienceMix::run(
                    $trafficSourceCampaign->trafficSource->shop,
                    $trafficSourceCampaign->trafficSource->type,
                    $trafficSourceCampaign->reference
                )),

                'state'    => $this->stateProps($trafficSourceCampaign),
                'campaign' => [
                    'reference'             => $trafficSourceCampaign->reference,
                    'name'                  => $trafficSourceCampaign->name,
                    'status'                => $data['status'] ?? null,
                    'primary_status'        => $data['primary_status'] ?? null,
                    'primary_status_reasons' => $data['primary_status_reasons'] ?? [],
                    'channel_type'          => $trafficSourceCampaign->channel_type ?? ($data['channel_type'] ?? null),
                    'bidding_strategy_type' => $data['bidding_strategy_type'] ?? null,
                    'budget_amount'         => isset($data['budget_amount']) ? (float) $data['budget_amount'] : null,
                    'budget_is_shared'      => (bool) ($data['budget_is_shared'] ?? false),
                    'start_date'            => $data['start_date'] ?? null,
                    'end_date'              => $data['end_date'] ?? null,
                    'currency'              => $currency,
                    'shop_currency'         => $this->shop->currency->code,
                    'fetched_at'            => $data['fetched_at'] ?? null,
                ],

                'update_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.update',
                    'parameters' => [
                        'organisation'          => $this->organisation->id,
                        'shop'                  => $this->shop->id,
                        'trafficSourceCampaign' => $trafficSourceCampaign->id,
                    ],
                ],
                'ad_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.ad.store',
                    'parameters' => [
                        'organisation'          => $this->organisation->id,
                        'shop'                  => $this->shop->id,
                        'trafficSourceCampaign' => $trafficSourceCampaign->id,
                    ],
                ],
                'keyword_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.keyword.store',
                    'parameters' => [
                        'organisation'          => $this->organisation->id,
                        'shop'                  => $this->shop->id,
                        'trafficSourceCampaign' => $trafficSourceCampaign->id,
                    ],
                ],
                'negative_keywords_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.negative_keywords.update',
                    'parameters' => [
                        'organisation'          => $this->organisation->id,
                        'shop'                  => $this->shop->id,
                        'trafficSourceCampaign' => $trafficSourceCampaign->id,
                    ],
                ],
                'element_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.element.update',
                    'parameters' => [
                        'organisation'          => $this->organisation->id,
                        'shop'                  => $this->shop->id,
                        'trafficSourceCampaign' => $trafficSourceCampaign->id,
                    ],
                ],

                ...$this->periodProps(),
                'google'                => $this->googleFigures($trafficSourceCampaign),
                'google_previous'       => $this->isComparing() ? $this->googleFigures($trafficSourceCampaign, true) : null,
                'impression_share'      => $this->impressionShare($trafficSourceCampaign),
                'conversions_by_action' => $this->conversionsByAction($trafficSourceCampaign),
                'daily'                 => $this->daily($trafficSourceCampaign),
                'attribution'           => $this->attribution($trafficSourceCampaign),
                'ad_groups'             => $data['ad_groups'] ?? [],
                'asset_groups'          => $data['asset_groups'] ?? [],
                'exclusions'            => $data['exclusions'] ?? [],
                'structure_window'      => $data['structure_window'] ?? null,
                'negative_keywords'     => $data['negative_keywords'] ?? [],

                /* Deferred: this one goes out to Google rather than to Postgres, and the rest of the
                   page has no reason to wait behind it. A slow or refusing account then costs this
                   panel and nothing else. */
                'search_terms' => Inertia::defer(function () use ($trafficSourceCampaign) {
                    [$from, $to] = $this->intervalDates();

                    return GetGoogleAdsSearchTerms::run($trafficSourceCampaign, $from, $to);
                }),
            ]
        );
    }

    /**
     * The in process page: what has been written so far, and the one button that commits it.
     */
    private function inProcessResponse(TrafficSourceCampaign $campaign, ActionRequest $request): Response
    {
        $parameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaignInProcess',
            [
                'breadcrumbs' => $this->getBreadcrumbs($campaign, $parameters),
                'title'       => $campaign->name,
                'pageHead'    => [
                    'title'   => $campaign->name,
                    'icon'    => ['icon' => ['fab', 'fa-google'], 'title' => __('Google Ads campaign')],
                    'model'   => __('In process'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'save',
                            'label' => __('Publish to Google Ads'),
                            'icon'  => ['fal', 'fa-cloud-upload'],
                            'route' => [
                                'name'       => 'grp.models.org.shop.google_ads.campaign.publish',
                                'parameters' => ['organisation' => $this->organisation->id, 'shop' => $this->shop->id, 'trafficSourceCampaign' => $campaign->id],
                                'method'     => 'post',
                            ],
                        ],
                    ],
                ],
                'state'    => $this->stateProps($campaign),
                'campaign' => [
                    'slug'         => $campaign->slug,
                    'name'         => $campaign->name,
                    'channel_type' => $campaign->channel_type,
                    'data'         => $campaign->data ?? [],
                ],
                'currency'      => $this->shop->currency->code,

                /* The type is picked here rather than before the campaign existed, and stays a choice
                   for as long as nothing has been published: every field below it depends on it. */
                'campaign_types' => StoreGoogleAdsCampaign::campaignTypes(),

                /* What Google still needs before it would create this, so the page can say why the
                   publish button will refuse rather than leaving somebody to guess which box is
                   empty. */
                'missing'       => UpdateInProcessGoogleAdsCampaign::missing((string) $campaign->channel_type, $campaign->data ?? []),
                'countries'     => collect(GetCountriesOptions::run())
                    ->map(fn (array $country) => ['value' => $country['code'], 'label' => $country['label']])
                    ->sortBy('label')
                    ->values()
                    ->all(),
                'images'        => Inertia::defer(fn () => $this->images($campaign, (string) $request->query('image_search', ''))),
                'image_search'  => (string) $request->query('image_search', ''),
                'image_route'   => [
                    'name'       => 'grp.models.org.shop.google_ads.image.store',
                    'parameters' => ['organisation' => $this->organisation->id, 'shop' => $this->shop->id],
                ],
                'update_route'  => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.in_process.update',
                    'parameters' => ['organisation' => $this->organisation->id, 'shop' => $this->shop->id, 'trafficSourceCampaign' => $campaign->id],
                ],
                'index_route' => [
                    'name'       => 'grp.org.shops.show.marketing.google_ads.index',
                    'parameters' => Arr::except($parameters, 'trafficSourceCampaign'),
                ],
            ]
        );
    }

    /**
     * Images this shop could advertise with, the same list the create form offers.
     *
     * @return array<int, array{id: int, name: string, thumbnail: string}>
     */
    private function images(TrafficSourceCampaign $campaign, string $search = ''): array
    {
        return Media::where('group_id', $campaign->trafficSource->shop->group_id)
            ->whereIn('mime_type', ['image/jpeg', 'image/png'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('file_name', 'ilike', '%'.$search.'%')
            ))
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (Media $media) => StoreGoogleAdsImage::shape($media))
            ->all();
    }

    /**
     * Switching a campaign on and off, in the page head where a mailshot keeps its stop and resume.
     *
     * Only ever one of them: a campaign is either serving or it is not, and offering both would leave
     * somebody deciding which one applies to what they are looking at.
     *
     * @return array<int, array>
     */
    private function stateActions(TrafficSourceCampaign $campaign): array
    {
        $isServing = $campaign->state === GoogleAdsCampaignStateEnum::PUBLISHED_SERVING;

        return [[
            'type'  => 'button',
            'style' => 'edit',
            'label' => $isServing ? __('Pause') : __('Switch on'),
            'icon'  => $isServing ? ['fal', 'fa-pause'] : ['fal', 'fa-play'],
            'route' => [
                'name'       => $isServing
                    ? 'grp.models.org.shop.google_ads.campaign.pause'
                    : 'grp.models.org.shop.google_ads.campaign.resume',
                'parameters' => ['organisation' => $this->organisation->id, 'shop' => $this->shop->id, 'trafficSourceCampaign' => $campaign->id],
                'method'     => 'post',
            ],
        ]];
    }

    /**
     * The state strip: where the campaign is now, and the three points it passes through, each with
     * the moment it happened where that has happened. The ones still ahead carry no timestamp, which
     * is what the page draws as the part not yet reached.
     *
     * @return array{current: string, label: string, description: string, last_error: string|null, timeline: array<int, array>}
     */
    private function stateProps(TrafficSourceCampaign $campaign): array
    {
        $labels       = GoogleAdsCampaignStateEnum::labels();
        $descriptions = GoogleAdsCampaignStateEnum::descriptions();
        $icons        = GoogleAdsCampaignStateEnum::stateIcon();

        return [
            'current'     => $campaign->state->value,
            'label'       => $labels[$campaign->state->value],
            'description' => $descriptions[$campaign->state->value],
            'last_error'  => $campaign->last_error,
            'timeline'    => collect(GoogleAdsCampaignStateEnum::cases())
                ->map(fn (GoogleAdsCampaignStateEnum $state) => [
                    'key'       => $state->value,
                    'label'     => $labels[$state->value],
                    'tooltip'   => $descriptions[$state->value],
                    'icon'      => $icons[$state->value]['icon'],
                    'timestamp' => $campaign->{$state->timestampColumn()},
                ])
                ->all(),
        ];
    }

    /**
     * What Google reports for the chosen period, in the account's own currency throughout, so the
     * ROAS below is a ratio between two figures Google produced under one attribution model.
     *
     * Purchases and registrations count primary conversion actions only, as Google's own Conversions
     * column does: an account that records the same sale through its website tag and again through a
     * GA4 import would otherwise count it twice. They are null rather than zero when the campaign
     * converted on days the split by conversion action has not been read for, because zero would
     * answer a question the data cannot.
     *
     * @return array{impressions: int, clicks: int, conversions: float, cost: float, conversions_value: float, ctr: float|null, avg_cpc: float|null, cost_per_conversion: float|null, roas: float|null, days: int, all_conversions: float, all_conversions_value: float, has_breakdown: bool, purchases: float|null, cost_per_purchase: float|null, purchase_rate: float|null, registrations: float|null, cost_per_registration: float|null, registration_rate: float|null}
     */
    private function googleFigures(TrafficSourceCampaign $campaign, bool $previous = false): array
    {
        $query = DB::table('traffic_source_campaign_metrics')
            ->where('traffic_source_campaign_id', $campaign->id);

        $row = $this->wherePeriodOrPrevious($query, 'date', $previous)
            ->selectRaw('COUNT(*) as days, COALESCE(SUM(impressions),0) as impressions, COALESCE(SUM(clicks),0) as clicks,
                         COALESCE(SUM(conversions),0) as conversions, COALESCE(SUM(source_cost),0) as cost,
                         COALESCE(SUM(source_conversions_value),0) as conversions_value,
                         COALESCE(SUM(all_conversions),0) as all_conversions,
                         COALESCE(SUM(source_all_conversions_value),0) as all_conversions_value')
            ->first();

        $impressions    = (int) $row->impressions;
        $clicks         = (int) $row->clicks;
        $cost           = (float) $row->cost;
        $conversions    = (float) $row->conversions;
        $value          = (float) $row->conversions_value;
        $allConversions = (float) $row->all_conversions;

        $breakdown = $this->wherePeriodOrPrevious(
            DB::table('traffic_source_campaign_conversions')->where('traffic_source_campaign_id', $campaign->id),
            'date',
            $previous
        )
            ->selectRaw(
                'COUNT(*) as rows_count,
                 COALESCE(SUM(CASE WHEN category = ? THEN conversions ELSE 0 END), 0) as purchases,
                 COALESCE(SUM(CASE WHEN category = ? THEN conversions ELSE 0 END), 0) as registrations',
                [TrafficSourceCampaignConversion::CATEGORY_PURCHASE, TrafficSourceCampaignConversion::CATEGORY_SIGNUP]
            )
            ->first();

        $hasBreakdown  = (int) $breakdown->rows_count > 0 || $conversions == 0;
        $purchases     = $hasBreakdown ? (float) $breakdown->purchases : null;
        $registrations = $hasBreakdown ? (float) $breakdown->registrations : null;

        return [
            'days'                  => (int) $row->days,
            'impressions'           => $impressions,
            'clicks'                => $clicks,
            'conversions'           => $conversions,
            'cost'                  => $cost,
            'conversions_value'     => $value,
            'all_conversions'       => $allConversions,
            'all_conversions_value' => (float) $row->all_conversions_value,

            /* Null wherever the denominator is zero, so the page prints a dash. A zero here would
               claim an answer the data does not contain: "nobody clicked" is not the same fact as
               "nobody was shown it". */
            'ctr'                 => $impressions > 0 ? round($clicks * 100 / $impressions, 2) : null,
            'avg_cpc'             => $clicks > 0 ? round($cost / $clicks, 2) : null,
            'cost_per_conversion' => $conversions > 0 ? round($cost / $conversions, 2) : null,
            'roas'                => $cost > 0 ? round($value / $cost, 2) : null,

            'has_breakdown'         => $hasBreakdown,
            'purchases'             => $purchases,
            'cost_per_purchase'     => $purchases > 0 ? round($cost / $purchases, 2) : null,
            'purchase_rate'         => $purchases !== null && $clicks > 0 ? round($purchases * 100 / $clicks, 2) : null,
            'registrations'         => $registrations,
            'cost_per_registration' => $registrations > 0 ? round($cost / $registrations, 2) : null,
            'registration_rate'     => $registrations !== null && $clicks > 0 ? round($registrations * 100 / $clicks, 2) : null,
        ];
    }

    /**
     * Google's search impression share for the period, weighted by eligible impressions rather than
     * averaged by day. Null for campaigns that never ran on Google Search in the period, so the page
     * can leave the block out instead of printing a row of dashes.
     *
     * @return array<string, float|null>|null
     */
    private function impressionShare(TrafficSourceCampaign $campaign): ?array
    {
        $selects = ['SUM(CASE WHEN search_impression_share > 0 THEN impressions / search_impression_share END) as eligible_impressions'];

        foreach (TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS as $column) {
            $selects[] = "SUM(CASE WHEN search_impression_share > 0 THEN impressions / search_impression_share * {$column} END) as {$column}";
        }

        $query = DB::table('traffic_source_campaign_metrics')
            ->where('traffic_source_campaign_id', $campaign->id);

        $row      = $this->wherePeriod($query, 'date')->selectRaw(implode(', ', $selects))->first();
        $eligible = (float) ($row->eligible_impressions ?? 0);

        if ($eligible <= 0) {
            return null;
        }

        $shares = [];

        foreach (TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS as $column) {
            $shares[$column] = $row->{$column} !== null ? round((float) $row->{$column} * 100 / $eligible, 2) : null;
        }

        return $shares;
    }

    /**
     * @return array<int, array{category: string, action_name: string, conversions: float, all_conversions: float, conversions_value: float, all_conversions_value: float}>
     */
    private function conversionsByAction(TrafficSourceCampaign $campaign): array
    {
        $query = DB::table('traffic_source_campaign_conversions')
            ->where('traffic_source_campaign_id', $campaign->id);

        return $this->wherePeriod($query, 'date')
            ->select(
                'category',
                'action_name',
                DB::raw('SUM(conversions) as conversions'),
                DB::raw('SUM(all_conversions) as all_conversions'),
                DB::raw('SUM(source_conversions_value) as conversions_value'),
                DB::raw('SUM(source_all_conversions_value) as all_conversions_value'),
            )
            ->groupBy('category', 'action_name')
            ->orderByDesc('all_conversions')
            ->get()
            ->map(fn ($row) => [
                'category'              => $row->category,
                'action_name'           => $row->action_name,
                'conversions'           => (float) $row->conversions,
                'all_conversions'       => (float) $row->all_conversions,
                'conversions_value'     => (float) $row->conversions_value,
                'all_conversions_value' => (float) $row->all_conversions_value,
            ])
            ->all();
    }

    /**
     * The day-by-day series behind the chart and the table. Spend is joined from traffic_source_costs
     * in the shop's currency beside Google's own in the account currency: when a shop bills in one
     * currency and advertises in another, the two columns are the same money seen from both sides,
     * and seeing them disagree is how a bad exchange rate gets noticed.
     *
     * @return array<int, array>
     */
    private function daily(TrafficSourceCampaign $campaign): array
    {
        $query = DB::table('traffic_source_campaign_metrics as m')
            ->leftJoin('traffic_source_costs as c', function ($join) use ($campaign) {
                $join->on('c.date', '=', 'm.date')
                    ->where('c.traffic_source_campaign_id', '=', $campaign->id);
            })
            ->where('m.traffic_source_campaign_id', $campaign->id);

        return $this->wherePeriod($query, 'm.date')
            ->orderByDesc('m.date')
            ->get([
                'm.date',
                'm.impressions',
                'm.clicks',
                'm.conversions',
                'm.source_cost',
                'm.source_conversions_value',
                DB::raw('COALESCE(c.amount, 0) as shop_cost'),
            ])
            ->map(fn ($row) => [
                'date'              => $row->date,
                'impressions'       => (int) $row->impressions,
                'clicks'            => (int) $row->clicks,
                'conversions'       => (float) $row->conversions,
                'cost'              => (float) $row->source_cost,
                'conversions_value' => (float) $row->source_conversions_value,
                'shop_cost'         => (float) $row->shop_cost,
            ])
            ->all();
    }

    /**
     * Aiku's own attribution, which is a different question from Google's and deliberately kept in a
     * block of its own on the page.
     *
     * These totals are for everything since attribution started recording, not for the chosen period:
     * the campaign stats are running aggregates rather than a time series, and quietly presenting
     * them under a period label would put a month of spend beside a year of revenue.
     */
    private function attribution(TrafficSourceCampaign $trafficSourceCampaign): array
    {
        $stats   = $trafficSourceCampaign->stats;
        $revenue = (float) ($stats->total_customer_revenue ?? 0);
        $cost    = (float) ($stats->total_cost ?? 0);

        return [
            'customers' => (float) ($stats->number_customers ?? 0),
            'purchases' => (float) ($stats->number_customer_purchases ?? 0),
            'revenue'   => $revenue,
            'cost'      => $cost,
            'roas'      => $cost > 0 ? round($revenue / $cost, 2) : null,
        ];
    }

    public function getBreadcrumbs(TrafficSourceCampaign $trafficSourceCampaign, array $routeParameters): array
    {
        return array_merge(
            IndexGoogleAdsCampaigns::make()->getBreadcrumbs([
                'organisation' => $routeParameters['organisation'],
                'shop'         => $routeParameters['shop'],
            ]),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $trafficSourceCampaign->name,
                        'icon'  => 'fab fa-google',
                    ],
                ],
            ],
        );
    }
}
