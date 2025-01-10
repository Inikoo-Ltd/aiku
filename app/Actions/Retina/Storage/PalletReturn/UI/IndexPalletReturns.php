<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Storage\PalletReturn\UI;

use App\Actions\RetinaAction;
use App\Actions\UI\Retina\Storage\UI\ShowRetinaStorageDashboard;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletReturns extends RetinaAction
{
    private FulfilmentCustomer $parent;

    /*    public function authorize(ActionRequest $request): bool
        {
            return $request->user()->hasPermissionTo("fulfilment.{$this->customer->fulfilmentCustomer->id}.view");
        }*/

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $this->customer->fulfilmentCustomer;

        return $this->handle($this->customer->fulfilmentCustomer, 'pallet_returns');
    }

    public function handle(FulfilmentCustomer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reference', $value)
                    ->orWhereStartWith('customer_reference', $value)
                    ->orWhereStartWith('slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder->where('pallet_returns.fulfilment_customer_id', $parent->id);

        return $queryBuilder
            ->defaultSort('reference')
            ->allowedSorts(['reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    $parent instanceof Fulfilment ? [
                        'title'       => __("You don't have any customer yet") . ' 😭',
                        'description' => __("Dont worry soon you will be pretty busy"),
                        'count'       => $parent->shop->crmStats->number_customers,
                        'action'      => [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new customer'),
                            'label'   => __('customer'),
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.customers.create',
                                'parameters' => [$parent->organisation->slug, $parent->slug]
                            ]
                        ]
                    ] : null
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'type', label: __('type'), type: 'icon', canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'reference', label: __('reference number'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer reference', label: __('return name'), canBeHidden: false, sortable: false, searchable: true)
                ->column(key: 'pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return PalletReturnsResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $container = [
            'icon'    => ['fal', 'fa-pallet-alt'],
            'tooltip' => __('Customer Fulfilment'),
            'label'   => Str::possessive($this->customer->fulfilmentCustomer->slug)
        ];

        return Inertia::render(
            'Storage/RetinaPalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('returns'),
                'pageHead' => [
                    'title'     => __('returns'),
                    // 'container' => $container,
                    'model'     => __('Storage'),
                    'icon' => [
                        'icon'  => ['fal', 'fa-truck-ramp'],
                        'title' => __('return')
                    ],
                    'actions'       => [
                        match (class_basename($this->parent)) {
                            'FulfilmentCustomer' =>
                                $this->customer->fulfilmentCustomer->number_pallets_status_storing ? [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => !$this->parent->number_stored_items_status_storing ? __('Create new return (whole pallet)') : __('Create new return'),
                                    'label'   => !$this->parent->number_stored_items_status_storing ? __('Return (whole pallet)') : __('Return'),
                                    'route'   => [
                                        'method'     => 'post',
                                        'name'       => 'retina.models.pallet-return.store',
                                        'parameters' => []
                                    ]
                                ] : false,

                            default => null
                        },
                        match (class_basename($this->parent)) {
                            'FulfilmentCustomer' =>
                            !$this->parent->number_stored_items_status_storing ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('Create new return (stored items)'),
                                'label'   => __('Return (Stored items)'),
                                'route'   => [
                                    'method'     => 'post',
                                    'name'       => 'retina.models.pallet-return-stored-items.store',
                                    'parameters' => []
                                ]
                            ] : false,

                            default => null
                        }
                    ]
                ],
                'data' => PalletReturnsResource::collection($customers),

            ]
        )->table($this->tableStructure($this->parent, prefix: 'pallet_returns'));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('pallet returns'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {

            'retina.storage.pallet-returns.index', 'retina.storage.pallet-returns.show' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'retina.storage.pallet-returns.index',
                        'parameters' => []
                    ]
                )
            ),
        };
    }
}
