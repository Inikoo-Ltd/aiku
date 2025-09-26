<?php

/*
 * author Arya Permana - Kirin
 * created on 21-01-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Pricing\UI;

use App\Actions\Retina\Fulfilment\UI\IndexRetinaPricing;
use App\Actions\RetinaAction;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Http\Resources\Fulfilment\RentalsResource;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaRentals extends RetinaAction
{
    use WithRetinaPricingSubNavigation;
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
                    $query->whereIn('rentals.state', $elements);
                }

            ],
        ];
    }

    public function handle($prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('rentals.shop_id', $this->fulfilment->shop_id);
        $queryBuilder->join('assets', 'rentals.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');


        foreach ($this->getElementGroups($this->fulfilment) as $key => $elementGroup) {
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
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle();
    }



    public function htmlResponse(LengthAwarePaginator $rentals, ActionRequest $request): Response
    {
        // dd(ServicesResource::collection($services));
        return Inertia::render(
            'Pricing/RetinaRentals',
            [
                'title'       => __('rentals'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-garage'],
                        'title' => __('Rentals')
                    ],
                    'model'    => __('Pricing'),
                    'title'         => __('rentals'),
                    'subNavigation' => $this->getPricingNavigation($this->fulfilment),
                ],

                'data'        => RentalsResource::collection($rentals),
            ]
        )->table(
            $this->tableStructure(
                parent: $this->fulfilment,
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
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __("No rentals found"),
                            'count' => $parent->shop->stats->number_assets_type_rental,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rental_price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, align: 'right', className: 'text-right font-mono')
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $rentals): AnonymousResourceCollection
    {
        return RentalsResource::collection($rentals);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
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
                IndexRetinaPricing::make()->getBreadcrumbs(routeName: $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            );
    }


}
