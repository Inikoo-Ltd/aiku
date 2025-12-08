<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\StoredItems\UI;

use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletDelivery;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaStoredItemsInPalletDelivery extends RetinaAction
{
    public function handle(PalletDelivery $palletDelivery, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for($palletDelivery->pallets())
            ->defaultSort('slug')
            ->allowedSorts(['slug', 'state'])
            ->allowedFilters([$globalSearch, 'slug', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No stored items found'),
                        'count' => $parent->count(),
                        'description' => __('No items stored in any pallets'),
                    ]
                )
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'notes', label: __('Notes'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('slug');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }

    public function htmlResponse(LengthAwarePaginator $storedItems, ActionRequest $request): Response
    {
        return Inertia::render(
            'Fulfilment/StoredItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __("Customer's SKUs"),
                'pageHead' => [
                    'title' => __("Customer's SKUs"),
                    'actions' => [
                        'buttons' => [
                            'route' => [
                                'name' => 'grp.org.hr.employees.create',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                            'label' => __("Customer's SKUs"),
                        ],
                    ],
                ],
                'data' => StoredItemResource::collection($storedItems),
            ]
        )->table($this->tableStructure($storedItems));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;

        $this->initialisation($request);

        return $this->handle($fulfilmentCustomer);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.fulfilment.stored-items.index',
                        ],
                        'label' => __("Customer's SKUs"),
                        'icon' => 'fal fa-bars',
                    ],

                ],
            ]
        );
    }
}
