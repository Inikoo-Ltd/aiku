<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Catalogue\OfferCampaignsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOfferCampaigns extends OrgAction
{
    protected Group|Shop $parent;

    public function handle(Group|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('offer_campaigns.code', $value)
                    ->orWhereWith('offer_campaigns.name', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(OfferCampaign::class)
                ->leftJoin('organisations', 'offer_campaigns.organisation_id', '=', 'organisations.id');

        if ($parent instanceof Group) {
            $query->where('offer_campaigns.group_id', $parent->id);
        } else {
            $query->where('offer_campaigns.shop_id', $parent->id);
        }

        $query->leftjoin('shops', 'offer_campaigns.shop_id', '=', 'shops.id')
            ->leftJoin('offer_campaign_stats', 'offer_campaigns.id', 'offer_campaign_stats.offer_campaign_id');
        $query->defaultSort('offer_campaigns.id')
            ->select(
                'offer_campaigns.id',
                'offer_campaigns.slug',
                'offer_campaigns.code',
                'offer_campaigns.name',
                'offer_campaigns.type',
                'offer_campaigns.state',
                'offer_campaigns.status',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'offer_campaign_stats.number_current_offers as number_current_offers'
            );

        return $query->
            allowedSorts(['code', 'name', 'state', 'number_current_offers'])
            ->allowedFilters([$globalSearch, 'code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-comment-dollar'],
                'title' => __('No offer campaigns found'),
            ];


            $emptyStateData['description'] = __("There are no offer campaigns in this shop");


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'number_current_offers', label: __('Number Offers'), canBeHidden: false, sortable: true, searchable: true);
            $table->defaultSort('id');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("discounts.{$this->parent->id}.edit");

        return $request->user()->authTo("discounts.{$this->parent->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $campaigns): AnonymousResourceCollection
    {
        return OfferCampaignsResource::collection($campaigns);
    }



    public function htmlResponse(LengthAwarePaginator $campaigns, ActionRequest $request): Response
    {
        $title      = __('Campaigns');
        $icon       = ['fal', 'fa-comment-dollar'];
        $afterTitle = null;
        $iconRight  = null;

        return Inertia::render(
            'Org/Discounts/Campaigns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Campaigns'),
                'pageHead'    => [
                    'title'      => $title,
                    'model'      => __('Offers'),
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,
                ],
                'data'        => OfferCampaignsResource::collection($campaigns),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        // return ;
        return match ($routeName) {
            'grp.overview.offer.campaigns.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Offer Campaigns'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),

            default =>  array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Offer Campaigns'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            )
        };
    }
}
