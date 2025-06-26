<?php

/*
 * author Arya Permana - Kirin
 * created on 11-11-2024-15h-50m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\OrgSupplierProducts\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrgSupplierProducts extends OrgAction
{

    public function handle(Organisation|OrgAgent|OrgSupplier $parent, PurchaseOrder $purchaseOrder): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('supplier_products.code', $value)
                    ->orWhereStartWith('supplier_products.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
                        ->leftJoin('stock_has_supplier_products', 'stock_has_supplier_products.supplier_product_id', '=', 'supplier_products.id')
                        ->leftJoinSub(
                            DB::table('stocks')
                                ->where('available', true)
                                ->orderBy('created_at', 'asc')
                                ->limit(1),
                            'first_stock',
                            'first_stock.id',
                            '=',
                            'stock_has_supplier_products.stock_id'
                        )
                        ->leftJoin('org_stocks', 'org_stocks.stock_id', '=', 'first_stock.id');
        $queryBuilder->leftjoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id');
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
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.id as supplier_product_id',
                'supplier_products.name',
                'supplier_products.current_historic_supplier_product_id as historic_id',
                'purchase_order_transactions.quantity_ordered as quantity_ordered',
                'purchase_order_transactions.id as purchase_order_transaction_id',
                'purchase_orders.id as purchase_order_id',
                'org_stocks.id as org_stock_id',
                'first_stock.id as stock_id',
                'suppliers.name as supplier_name'
            ])
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function inOrgAgent(OrgAgent $orgAgent, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgAgent->organisation, $request);
        return $this->handle($orgAgent, $purchaseOrder);
    }

    public function inOrgSupplier(OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgSupplier->organisation, $request);
        return $this->handle($orgSupplier, $purchaseOrder);
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return PurchaseOrderOrgSupplierProductsResource::collection($orgSupplierProducts);
    }
}
