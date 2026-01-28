<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Http\Resources\Catalogue\OffersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
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

class IndexOffers extends OrgAction
{
    protected Group|Shop|OfferCampaign $parent;

    protected function getElementGroups(Group|Shop|OfferCampaign $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OfferStateEnum::labels(),
                    // OfferStateEnum::count($parent)
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('offers.state', $elements);
                }
            ],
        ];
    }

    public function handle(Group|Shop|OfferCampaign $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('offers.code', $value)
                    ->orWhereWith('offers.name', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Offer::class)
                ->leftJoin('organisations', 'offers.organisation_id', '=', 'organisations.id');

        if ($parent instanceof OfferCampaign) {
            $query->where('offers.offer_campaign_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $query->where('offers.group_id', $parent->id);
        } else {
            $query->where('offers.shop_id', $parent->id);
        }
        $query->leftjoin('shops', 'offers.shop_id', '=', 'shops.id');
        $query->leftjoin('offer_campaigns', 'offers.offer_campaign_id', '=', 'offer_campaigns.id');

        if ($this->bucket == 'active') {
            $query->where('offers.state', OfferStateEnum::ACTIVE);
        } elseif ($this->bucket == 'finished') {
            $query->where('offers.state', OfferStateEnum::FINISHED);
        } elseif ($this->bucket == 'suspended') {
            $query->where('offers.state', OfferStateEnum::SUSPENDED);
        } elseif ($this->bucket == 'in_process') {
            $query->where('offers.state', OfferStateEnum::IN_PROCESS);
        }

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $query->defaultSort('offers.id')
            ->select(
                'offers.id',
                'offers.slug',
                'offers.state',
                'offers.code',
                'offers.name',
                'offer_campaigns.slug as offer_campaign_slug',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            );

        return $query->allowedSorts(['id','code', 'name'])
            ->allowedFilters([$globalSearch, 'code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|OfferCampaign $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $emptyStateData = [
                'icons' => ['fal fa-badge-percent'],
                'title' => __('No offers found'),
            ];


            $emptyStateData['description'] = __("There are no offers");


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: '', type: 'icon', sortable: false);
            $table->column(key: 'name', label: __('Name'), sortable: true, );
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), sortable: true, )
                        ->column(key: 'shop_name', label: __('Shop'), sortable: true, );
            }
            $table->defaultSort('id');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("discounts.{$this->shop->id}.edit");

        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(parent: group());
    }

    public function jsonResponse(LengthAwarePaginator $offers): AnonymousResourceCollection
    {
        return OffersResource::collection($offers);
    }



    public function htmlResponse(LengthAwarePaginator $offers, ActionRequest $request): Response
    {
        $title      = __('Offers');
        $icon       = ['fal', 'fa-badge-percent'];
        $afterTitle = null;
        $iconRight  = null;

        return Inertia::render(
            'Org/Discounts/Offers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Offers'),
                'pageHead'    => [
                    'title'      => $title,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,
                ],
                'data'        => OffersResource::collection($offers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.overview.offer.offers.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Offers'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            )
        };
    }
}
