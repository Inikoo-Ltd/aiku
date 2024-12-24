<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Rentals;

use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\UI\Fulfilment\RentalsTabsEnum;
use App\Http\Resources\Fulfilment\RentalsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
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
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }

    public function handle(Fulfilment $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('rentals.shop_id', $parent->shop_id);
        $queryBuilder->join('assets', 'rentals.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');




        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
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
                'rentals.description',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
            ]);


        return $queryBuilder->allowedSorts(['code','name','rental_price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        $this->canDelete = $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
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
        return Inertia::render(
            'Org/Fulfilment/Rentals',
            [
                'title'       => __('fulfilment'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-garage'],
                        'title' => __('rentals')
                    ],
                    'title'         => __('rentals'),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'primary',
                            'icon'  => 'fal fa-plus',
                            'label' => __('Create rental'),
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.catalogue.rentals.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ],
                    ]
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
                parent: $this->fulfilment,
                prefix: RentalsTabsEnum::RENTALS->value
            )
        );
    }

    public function tableStructure(
        Fulfilment $parent,
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

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No rentals found"),
                        'count' => $parent->shop->stats->number_assets_type_rental
                    ]
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rental_price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono')
                ->column(key: 'workflow', label: __('workflow'), canBeHidden: false, searchable: true, className: 'hello')
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $rentals): AnonymousResourceCollection
    {
        return RentalsResource::collection($rentals);
    }


    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
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

        return
            array_merge(
                ShowFulfilmentCatalogueDashboard::make()->getBreadcrumbs(routeParameters: $routeParameters, icon: 'fal fa-ballot'),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.catalogue.rentals.index',
                        'parameters' => $routeParameters
                    ]
                )
            );
    }


}
