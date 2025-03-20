<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn\UI;

use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPalletReturns extends RetinaAction
{
    private FulfilmentCustomer $parent;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $this->customer->fulfilmentCustomer;

        return $this->handle($this->customer->fulfilmentCustomer, 'pallet_returns');
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder->where('pallet_returns.fulfilment_customer_id', $fulfilmentCustomer->id);

        return $queryBuilder
            ->defaultSort('reference', 'state', 'type', 'date')
            ->allowedSorts(['reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
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
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'created_at', label: __('Created at'), canBeHidden: false, type: 'date')
                ->column(key: 'reference', label: __('reference number'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('Your reference'), canBeHidden: false, sortable: true, searchable: true)
                // ->column(key: 'customer', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'total_amount', label: __('total amount'), canBeHidden: false, sortable: false, searchable: false, type: 'currency');
        };
    }


    public function htmlResponse(LengthAwarePaginator $palletReturns, ActionRequest $request): Response
    {

        $fulfilmentCustomer = $this->customer->fulfilmentCustomer;

        $actions = [];

        // if (!app()->environment('production')) {
        $actions = [
            $fulfilmentCustomer->number_pallets_status_storing ? [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('Create new return (whole goods)') : __('Create new return'),
                'label'   => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('Goods Return (whole goods)') : __('Goods Return'),
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return.store',
                    'parameters' => []
                ]
            ] : false,
            $this->customer->fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('Create new return (Selected SKUs)'),
                'label'   => __('Goods Return (Selected SKUs)'),
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return-stored-items.store',
                    'parameters' => []
                ]
            ] : false,
        ];
        // }

        return Inertia::render(
            'Storage/RetinaPalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'    => __('goods returns'),
                'pageHead' => [
                    'title'     => __('Goods Returns'),
                    'model'     => __('Storage'),
                    'icon' => [
                        'icon'  => ['fal', 'fa-truck-ramp'],
                        'title' => __('return')
                    ],
                    'actions'       => $actions
                ],
                'data' => PalletReturnsResource::collection($palletReturns),

            ]
        )->table($this->tableStructure($this->parent, prefix: 'pallet_returns'));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.fulfilment.storage.pallet_returns.index',
                        ],
                        'label' => __('Goods Returns'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }

}
