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
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexGoogleAdsCampaigns extends OrgAction
{
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
            ->where('traffic_sources.type', TrafficSourcesTypeEnum::GOOGLE_ADS->value);

        $selectFields = [
            'traffic_source_campaigns.id',
            'traffic_source_campaigns.slug',
            'traffic_source_campaigns.reference',
            'traffic_source_campaigns.name',
            DB::raw("traffic_source_campaigns.data->>'status' as status"),
            DB::raw("traffic_source_campaigns.data->>'channel_type' as channel_type"),
            DB::raw("(traffic_source_campaigns.data->>'budget_amount')::numeric as budget_amount"),
            DB::raw("traffic_source_campaigns.data->>'currency' as currency_code"),
            DB::raw("(SELECT COALESCE(SUM(amount), 0) FROM traffic_source_costs
                        WHERE traffic_source_costs.traffic_source_campaign_id = traffic_source_campaigns.id
                        AND traffic_source_costs.date >= CURRENT_DATE - INTERVAL '30 days') as spend_30d"),
            DB::raw('(SELECT COALESCE(SUM(amount), 0) FROM traffic_source_costs
                        WHERE traffic_source_costs.traffic_source_campaign_id = traffic_source_campaigns.id) as spend_total'),
        ];

        $queryBuilder->select($selectFields);

        return $queryBuilder
            ->defaultSort('traffic_source_campaigns.name')
            ->allowedSorts(['name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false)
                ->column(key: 'channel_type', label: __('Channel'), canBeHidden: true)
                ->column(key: 'budget_amount', label: __('Budget'), canBeHidden: false, type: 'currency')
                ->column(key: 'spend_30d', label: __('Spend (30d)'), canBeHidden: false, type: 'currency')
                ->column(key: 'spend_total', label: __('Spend (total)'), canBeHidden: true, type: 'currency');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $campaigns, ActionRequest $request): Response
    {
        $isConnected = filled(Arr::get($this->shop->settings, 'google_ads.refresh_token'));

        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaigns',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Google Ads'),
                'pageHead'      => [
                    'title' => $this->shop->name,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-google'],
                        'title' => __('Google Ads'),
                    ],
                    'model' => __('Google Ads'),
                ],
                'is_connected'  => $isConnected,
                'shop_currency' => $this->shop->currency->code,
                'settings_route' => [
                    'name'       => 'grp.org.shops.show.settings.edit',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'data' => GoogleAdsCampaignsResource::collection($campaigns),
            ]
        )->table($this->tableStructure());
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
