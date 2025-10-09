<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Rentals;

use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\UI\Fulfilment\RentalsTabsEnum;
use App\Http\Resources\Fulfilment\RentalsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
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

class IndexFulfilmentRentals extends OrgAction
{
    public Fulfilment|Group $parent;

    protected function getElementGroups(Fulfilment $parent): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    RentalStateEnum::labels(),
                    RentalStateEnum::count($parent->shop)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('assets.state', $elements);
                }

            ],
        ];
    }

    public function handle(Group|Fulfilment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('rentals.name', $value)
                    ->orWhereStartWith('rentals.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Rental::class);

        if ($parent instanceof Group) {
            $queryBuilder->where('rentals.group_id', $parent->id);
        } else {
            $queryBuilder->where('rentals.shop_id', $parent->shop_id);
        }

        $queryBuilder->join('assets', 'rentals.asset_id', '=', 'assets.id');
        $queryBuilder->leftJoin('asset_sales_intervals', 'assets.id', '=', 'asset_sales_intervals.asset_id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');

        if ($parent instanceof Fulfilment) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        $queryBuilder
            ->defaultSort('rentals.id')
            ->select([
                'rentals.id',
                'rentals.slug',
                'rentals.state',
                'rentals.auto_assign_asset',
                'rentals.auto_assign_asset_type',
                'rentals.created_at',
                'rentals.price as rental_price',
                'rentals.unit',
                'assets.name',
                'assets.code',
                'assets.price',
                'asset_sales_intervals.sales_all as sales',
                'rentals.description',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
            ]);


        return $queryBuilder->allowedSorts(['code','name','rental_price', 'sales'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(RentalsTabsEnum::values());

        return $this->handle($fulfilment, RentalsTabsEnum::RENTALS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function maya(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    public function htmlResponse(LengthAwarePaginator $rentals, ActionRequest $request): Response
    {

        $title = __('fulfilment');
        $icon = [
            'icon'  => ['fal', 'fa-garage'],
            'title' => __('Rentals')
        ];
        $pageHeadTitle = __('Rentals');

        $actions = null;
        if ($request->user()->authTo("supervisor-fulfilment-shop.".$this->fulfilment->id)) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Create rental'),
                    'route' => [
                        'name'       => 'grp.org.fulfilments.show.catalogue.rentals.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ]
            ];
        }

        if ($this->parent instanceof Group) {
            $title = __('Rentals');
            $actions = null;
        }

        return Inertia::render(
            'Org/Fulfilment/Rentals',
            [
                'title'       => $title,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'    => $icon,
                    'model'   => __('Catalogue'),
                    'title'         => $pageHeadTitle,
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RentalsTabsEnum::navigation()
                ],

                RentalsTabsEnum::RENTALS->value => $this->tab == RentalsTabsEnum::RENTALS->value ?
                    fn () => RentalsResource::collection($rentals)
                    : Inertia::lazy(fn () => RentalsResource::collection($rentals)),

            ]
        )->table(
            $this->tableStructure(
                parent: $this->parent,
                prefix: RentalsTabsEnum::RENTALS->value
            )
        );
    }

    public function tableStructure(
        Group|Fulfilment $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($parent instanceof Fulfilment) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No rentals found"),
                        'count' => ($parent instanceof Group) ? $parent->catalogueStats->number_rentals : $parent->shop->stats->number_assets_type_rental
                    ]
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rental_price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, align: 'right', className: 'text-right font-mono')
                ->column(key: 'workflow', label: __('workflow'), canBeHidden: false, searchable: true, className: 'hello')
                ->column(key: 'sales', label: __('sales'), canBeHidden: false, sortable: true)
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $rentals): AnonymousResourceCollection
    {
        return RentalsResource::collection($rentals);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request)->withTab(RentalsTabsEnum::values());
        return $this->handle($this->parent, RentalsTabsEnum::RENTALS->value);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) use ($suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Rentals'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.fulfilments.show.catalogue.rentals.index' =>
            array_merge(
                ShowFulfilmentCatalogueDashboard::make()->getBreadcrumbs(routeParameters: $routeParameters, icon: 'fal fa-ballot'),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.catalogue.rentals.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.billables.rentals.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };

    }


}
