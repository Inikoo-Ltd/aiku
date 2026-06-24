<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Discounts\Offer\UI\IndexOffersInsights;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Http\Resources\Catalogue\OffersInsightsResource;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOffersInsights extends OrgAction
{
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    protected ?OfferCampaign $offerCampaign = null;
    protected ?string $offerType = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        $campaignSlug = $request->input('campaign');
        if ($campaignSlug) {
            $this->offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('slug', $campaignSlug)->first();
        }

        $this->offerType = $request->input('type');

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $userSettings  = $request->user()->settings;
        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        [$fromDate, $toDate] = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $insights = GetOffersInsightsData::run($this->shop, $this->offerCampaign, $this->offerType, $fromDate, $toDate);

        $offers = IndexOffersInsights::make()->handle($this->shop, $this->offerCampaign, $this->offerType, $fromDate, $toDate);

        $campaignOptions = OfferCampaign::where('shop_id', $this->shop->id)
            ->where('status', true)
            ->orderBy('name')
            ->get(['slug', 'name', 'type'])
            ->map(fn (OfferCampaign $offerCampaign) => [
                'slug' => $offerCampaign->slug,
                'name' => $offerCampaign->name,
                'type' => $offerCampaign->type->value,
            ]);

        $typeOptions = DB::table('offers')
            ->where('shop_id', $this->shop->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'offers_insights',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $savedInterval->value,
                        'range_interval' => DashboardIntervalFilters::run($savedInterval, $userSettings),
                    ],
                    'insights'  => $insights,
                ],
            ],
        ];

        return Inertia::render(
            'Org/Discounts/OffersInsights',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Offers Insights'),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-badge-percent'],
                        'title' => __('Offers Insights')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-analytics'],
                        'title' => __('Insights')
                    ],
                    'title'     => __('Insights'),
                    'model'     => __('Offers'),
                ],
                'filters'     => [
                    'campaigns' => $campaignOptions,
                    'types'     => $typeOptions,
                    'campaign'  => $this->offerCampaign?->slug,
                    'type'      => $this->offerType,
                ],
                'offers'      => OffersInsightsResource::collection($offers),
                'dashboard'   => $dashboard,
            ]
        )->table(IndexOffersInsights::make()->tableStructure($this->shop));
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
                            'name'       => 'grp.org.shops.show.discounts.insights',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Offers Insights')
                    ]
                ]
            ]
        );
    }
}
