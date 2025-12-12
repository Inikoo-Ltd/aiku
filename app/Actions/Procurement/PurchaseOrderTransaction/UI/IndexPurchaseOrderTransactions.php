<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 09 May 2023 09:25:51 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPurchaseOrderTransactions extends OrgAction
{
    public function handle(PurchaseOrder $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereHas('supplierProduct', function ($query) use ($value) {
                $query->where('code', 'ILIKE', "%$value%")
                ->orWhere('name', 'ILIKE', "%$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PurchaseOrderTransaction::class);

        if ($parent instanceof PurchaseOrder) {
            $query->where('purchase_order_transactions.purchase_order_id', $parent->id);
        }

        return $query->defaultSort('purchase_order_transactions.id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'supplier', label: __('supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unit_cost', label: __('unit price'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_ordered', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_cost', label: __('cost'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {

        $this->initialisation($purchaseOrder->organisation, $request);
        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(LengthAwarePaginator $purchaseOrders): AnonymousResourceCollection
    {
        return PurchaseOrderTransactionResource::collection($purchaseOrders);
    }


    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.procurement.purchase_orders.index'
                            ],
                            'label' => __('Purchase orders'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            );
    }
}
