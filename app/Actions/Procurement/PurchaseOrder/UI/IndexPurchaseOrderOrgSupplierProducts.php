<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
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

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $orgId = $purchaseOrder->organisation_id;

        $orgStockSub = "(select os.id from org_stocks os
            inner join stock_has_supplier_products shsp on shsp.stock_id = os.stock_id
            where shsp.supplier_product_id = supplier_products.id
                and os.organisation_id = {$orgId}
            limit 1)";

        $stockSub = "(select shsp.stock_id from stock_has_supplier_products shsp
            where shsp.supplier_product_id = supplier_products.id
            limit 1)";

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->leftJoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id')
            ->leftJoin('currencies as supplier_currency', 'supplier_currency.id', 'supplier_products.currency_id')
            ->leftJoin('organisations', 'organisations.id', 'org_supplier_products.organisation_id')
            ->leftJoin('currencies as org_currency', 'org_currency.id', 'organisations.currency_id');

        $queryBuilder->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder) {
            $join->on('purchase_order_transactions.org_supplier_product_id', '=', 'org_supplier_products.id')
                ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id);
        });

        if (class_basename($parent) == 'OrgAgent') {
            $queryBuilder->where('org_supplier_products.org_agent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $this->organisation->id);
        }

        $queryBuilder->where(function ($query) {
            $query->where('org_supplier_products.state', OrgSupplierProductStateEnum::ACTIVE)
                ->orWhereNotNull('purchase_order_transactions.id');
        });

        $paginator = $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.id as supplier_product_id',
                'supplier_products.name',
                'supplier_products.cost as unit_cost',
                'supplier_products.units_per_pack',
                'supplier_products.units_per_carton',
                'supplier_products.current_historic_supplier_product_id as historic_id',
                'supplier_currency.code as net_currency',
                'org_currency.code as org_currency',
                'purchase_order_transactions.quantity_ordered as quantity_ordered',
                'purchase_order_transactions.net_amount as net_amount',
                'purchase_order_transactions.org_net_amount as org_net_amount',
                'purchase_order_transactions.org_exchange as org_exchange',
                'purchase_order_transactions.id as purchase_order_transaction_id',
                'suppliers.name as supplier_name',
            ])
            ->selectRaw("{$orgStockSub} as org_stock_id")
            ->selectRaw("{$stockSub} as stock_id")
            ->selectRaw("{$purchaseOrder->id} as purchase_order_id")
            ->selectRaw(($purchaseOrder->org_exchange ?: 1).' as po_org_exchange')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attachImages($paginator);

        return $paginator;
    }

    private function attachImages(LengthAwarePaginator $paginator): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks = OrgStock::with('tradeUnits.image')->whereIn('id', $orgStockIds)->get()->keyBy('id');

        $paginator->getCollection()->transform(function ($row) use ($orgStocks) {
            $tradeUnit = $orgStocks->get($row->org_stock_id)?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $row->image_sources = $tradeUnit?->imageSources(64, 64);

            return $row;
        });
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

    public function tableStructure(PurchaseOrder $purchaseOrder, $prefix = null): Closure
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
                ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false)
                ->column(key: 'description', label: __('Description'), canBeHidden: false)
                ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right')
                ->defaultSort('code');
        };
    }
}
