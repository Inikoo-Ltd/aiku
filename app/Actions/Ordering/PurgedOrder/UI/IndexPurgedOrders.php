<?php

/*
 * author Arya Permana - Kirin
 * created on 07-11-2024-13h-26m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\PurgedOrder\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Ordering\PurgedOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use App\Models\Ordering\PurgedOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPurgedOrders extends OrgAction
{
    public function handle(Purge $purge, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PurgedOrder::class);
        $query->where('purge_id', $purge->id);

        $query->leftjoin('orders', 'purged_orders.order_id', '=', 'orders.id');

        return $query->defaultSort('purged_orders.id')
            ->select([
                'purged_orders.id',
                'purged_orders.status',
                'purged_orders.purged_at',
                'purged_orders.order_last_updated_at',
                'purged_orders.amount',
                'purged_orders.number_transactions',
                'purged_orders.note',
                'orders.reference as order_reference',
                'orders.id as order_id',
                'orders.slug as order_slug',
            ])
            ->allowedSorts(['id', 'status', 'purged_at', 'amount', 'number_transactions', 'order_reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $noResults = __("No purged orders found");

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                    ]
                );


            $table->column(key: 'order_reference', label: __('order'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'status', label: __('status'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'purged_at', label: __('purged at'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'amount', label: __('amount'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'number_transactions', label: __('transactions'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'note', label: __('note'), sortable: true, canBeHidden: false, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("orders.{$this->shop->id}.view");
    }


    public function jsonResponse(LengthAwarePaginator $purgedOrders): AnonymousResourceCollection
    {
        return PurgedOrdersResource::collection($purgedOrders);
    }

    public function asController(Organisation $organisation, Shop $shop, Purge $purge, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($purge);
    }
}
