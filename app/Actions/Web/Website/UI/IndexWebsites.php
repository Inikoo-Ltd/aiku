<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 May 2023 12:18:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\Web\WebsitesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsites extends OrgAction
{
    use WithWebAuthorisation;
    private Group|Organisation|Fulfilment|Shop $parent;



    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope  = $organisation;
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->scope = $this->parent;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }


    protected function getElementGroups(Group|Organisation|Shop|Fulfilment $parent): array
    {
        return
            [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        WebsiteStateEnum::labels(),
                        WebsiteStateEnum::count($parent)
                    ),

                    'engine' => function ($query, $elements) {
                        $query->whereIn('websites.state', $elements);
                    }

                ]
            ];
    }

    public function handle(Group|Organisation|Shop|Fulfilment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('websites.name', $value)
                    ->orWhereWith('websites.domain', $value)
                    ->orWhereStartWith('websites.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Website::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('websites.organisation_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('websites.shop_id', $parent->shop->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('websites.shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('websites.group_id', $parent->id);
        }


        if ($parent instanceof Group || $parent instanceof Organisation) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('websites.code')
            ->select(['websites.id', 'websites.code', 'websites.slug', 'websites.name', 'websites.slug', 'websites.domain', 'websites.status', 'websites.state','websites.shop_id',
                      'shops.type as shop_type', 'shops.slug as shop_slug','shops.name as shop_name','organisations.name as organisation_name','organisations.slug as organisation_slug'])
            ->leftJoin('shops', 'websites.shop_id', 'shops.id')
            ->leftJoin('organisations', 'websites.organisation_id', 'organisations.id')
            ->allowedSorts([ 'code', 'name','domain','state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Shop|Fulfilment $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($parent instanceof Group || $parent instanceof Organisation) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $countWebsites = match (class_basename($parent)) {
                'Group', 'Organisation' => $parent->webStats->number_websites,
                'Shop'  => $parent->website()->count(),
                default => $parent->shop->website()->count(),
            };

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No websites found'),
                        'count' => $countWebsites

                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], sortable: true, type: 'icon')
                ->column(key: 'code', label: __('code'), sortable: true)
                ->column(key: 'name', label: __('name'), sortable: true);
            if (!($parent instanceof Group)) {
                $table->column(key: 'routeUniqueVisitor', label: __('unique visitor'));
            }
            $table->column(key: 'domain', label: __('domain'), sortable: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $websites): AnonymousResourceCollection
    {
        return WebsitesResource::collection($websites);
    }

    public function htmlResponse(LengthAwarePaginator $websites, ActionRequest $request): Response
    {
        $scope     = $this->parent;
        $container = null;
        $title     = __('Websites');
        if (class_basename($scope) == 'Shop') {
            $title     = __("Shop Websites");
            $container = [
                'icon'    => ['fal', 'fa-store-alt'],
                'tooltip' => __('Shop'),
                'label'   => Str::possessive($scope->code)
            ];
        } elseif (class_basename($scope) == 'Fulfilment') {
            $title     = __("Fulfilment Shop Websites");
            $container = [
                'icon'    => ['fal', 'fa-pallet-alt'],
                'tooltip' => __('Fulfilment shop'),
                'label'   => Str::possessive($scope->shop->code)
            ];
        }

        $createWebsite = null;
        if ($this->canEdit
            && ($this->parent instanceof Shop || $this->parent instanceof Fulfilment)
        ) {
            if ($this->parent instanceof Shop) {
                $website = $this->parent->website;
            } else {
                $website = $this->parent->shop->website;
            }

            if (!$website) {
                $createWebsite = [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __("Set up shop's website"),
                    'label'   => __('set up website'),
                    'route'   => match (class_basename($this->parent)) {
                        'Shop' => [
                            'name'       => 'grp.org.shops.show.web.websites.create',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        'Fulfilment' => [
                            'name'       => 'grp.org.fulfilments.show.web.websites.create',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        default => null
                    }
                ];
            }
        }


        return Inertia::render(
            'Org/Web/Websites',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,

                'pageHead' => [
                    'title'     => __('websites'),
                    'container' => $container,
                    'icon'      => [
                        'title' => __('website'),
                        'icon'  => 'fal fa-globe'
                    ],


                    'actions' => [
                        $createWebsite


                    ]
                ],
                'data'     => WebsitesResource::collection($websites),

            ]
        )->table($this->tableStructure($this->parent));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Websites'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.websites.index' =>
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ),
            ),

            'grp.org.fulfilments.show.web.websites.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ),
            ),


            'grp.org.shops.show.web.websites.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.dashboard',
                        'parameters' => $routeParameters
                    ]
                ),
            ),

            'grp.overview.web.websites.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ),
            ),

            default => []
        };
    }

}
