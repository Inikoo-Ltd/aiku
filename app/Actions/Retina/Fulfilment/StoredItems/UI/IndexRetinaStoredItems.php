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
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaStoredItems extends RetinaAction
{
    public function handle(FulfilmentCustomer|Pallet $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(StoredItem::class)
            ->select(
                'stored_items.id',
                'stored_items.slug',
                'stored_items.reference',
                'stored_items.state',
                'stored_items.name',
                'stored_items.total_quantity',
                'stored_items.number_pallets',
                'stored_items.number_audits',
            )
            ->defaultSort('reference')
            ->when($parent, function ($query) use ($parent) {
                if ($parent instanceof FulfilmentCustomer) {
                    $query->where('fulfilment_customer_id', $parent->id);
                } elseif ($parent instanceof Pallet) {
                    $query->join('pallet_stored_items', 'pallet_stored_items.stored_item_id', '=', 'stored_items.id')
                        ->where('pallet_stored_items.pallet_id', $parent->id);
                }
            })
            ->allowedSorts(['reference', 'total_quantity', 'name', 'number_pallets', 'number_audits', 'pallet_reference'])
            ->allowedFilters([$globalSearch, 'slug', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'         => __("No stored items found"),
                        'count'         => $parent->count(),
                        'description'   => __("No items stored in any pallets")
                    ]
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true)
                ->column(key: 'number_pallets', label: __("Pallets"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_audits', label: __("Audits"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'total_quantity', label: __("Quantity"), canBeHidden: false, sortable: true, align: 'right');

            $table->defaultSort('reference');
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
            'Storage/RetinaStoredItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __("SKUs"),
                'pageHead'    => [
                    'title'   => __("SKUs"),

                ],
                'data' => StoredItemResource::collection($storedItems),
            ]
        )->table($this->tableStructure($storedItems));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer);
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
                            'name' => 'retina.fulfilment.storage.stored-items.index'
                        ],
                        'label' => __("SKUs"),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
