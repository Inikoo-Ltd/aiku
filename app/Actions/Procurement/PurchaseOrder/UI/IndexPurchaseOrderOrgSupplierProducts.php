<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPurchaseOrderOrgSupplierProducts extends OrgAction
{
    private OrgSupplier|OrgAgent|Organisation $parent;

    public function handle(Organisation|OrgAgent|OrgSupplier $parent, PurchaseOrder $purchaseOrder, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('supplier_products.code', $value)
                    ->orWhereStartWith('supplier_products.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id');
        $queryBuilder->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder) {
            $join->on('purchase_order_transactions.org_supplier_product_id', '=', 'org_supplier_products.id')
                ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id);
        });
        $queryBuilder->leftJoin('purchase_orders', 'purchase_order_transactions.purchase_order_id', 'purchase_orders.id');

        if (class_basename($parent) == 'OrgAgent') {
            $queryBuilder->where('org_supplier_products.org_agent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $this->organisation->id);
        }

        $queryBuilder->where('org_supplier_products.is_available', true);

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            // ->addSelect([
            //     'purchase_order_transactions.quantity_ordered as quantity_ordered'
            // ])
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.id as supplier_product_id',
                'supplier_products.name',
                'supplier_products.current_historic_supplier_product_id as historic_id',
                'purchase_order_transactions.quantity_ordered as quantity_ordered',
                'purchase_order_transactions.id as purchase_order_transaction_id',
                'purchase_orders.id as purchase_order_id'
            ])
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return PurchaseOrderOrgSupplierProductsResource::collection($orgSupplierProducts);
    }

    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: __('image'), canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_ordered', label: __('quantity ordered'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }
}
