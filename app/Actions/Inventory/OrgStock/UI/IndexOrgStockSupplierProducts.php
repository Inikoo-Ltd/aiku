<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 15 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\OrgStockSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrgStockSupplierProducts extends OrgAction
{
    use WithInventoryAuthorisation;

    public function handle(OrgStock $orgStock, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(OrgStockHasOrgSupplierProduct::class);
        $query->where('org_stock_has_org_supplier_products.org_stock_id', $orgStock->id);
        $query->leftJoin('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id');
        $query->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id');
        $query->leftJoin('org_suppliers', 'org_suppliers.id', 'org_supplier_products.org_supplier_id');
        $query->leftJoin('suppliers', 'suppliers.id', 'org_suppliers.supplier_id');
        $query->leftJoin('currencies', 'currencies.id', 'supplier_products.currency_id');

        $paginator = $query
            ->defaultSort('-local_priority')
            ->select([
                'org_supplier_products.slug',
                'org_stock_has_org_supplier_products.org_stock_id',
                'org_stock_has_org_supplier_products.org_supplier_product_id',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.description',
                'supplier_products.units_per_pack',
                'supplier_products.units_per_carton',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'org_suppliers.slug as org_supplier_slug',
                'currencies.code as currency_code',
                'org_stock_has_org_supplier_products.local_priority',
            ])
            ->selectRaw('supplier_products.cost as unit_cost')
            ->selectRaw('round(supplier_products.cost * (1 + supplier_products.extra_costs), 4) as delivered_unit_cost')
            ->selectRaw('case when supplier_products.units_per_pack > 0 then supplier_products.units_per_carton / supplier_products.units_per_pack end as packages_per_carton')
            ->selectRaw('(org_stock_has_org_supplier_products.local_priority = max(org_stock_has_org_supplier_products.local_priority) over ()) as is_preferred')
            ->allowedSorts([
                'code',
                'local_priority',
                AllowedSort::field('supplier_name', 'suppliers.name'),
                AllowedSort::field('unit_cost', 'supplier_products.cost'),
                AllowedSort::field('units_per_carton', 'supplier_products.units_per_carton'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        return $this->convertDeliveredToOrgCurrency($paginator, $orgStock->organisation->currency);
    }

    private function convertDeliveredToOrgCurrency(LengthAwarePaginator $paginator, Currency $orgCurrency): LengthAwarePaginator
    {
        $rates = [];

        $paginator->getCollection()->transform(function ($row) use ($orgCurrency, &$rates) {
            $row->org_currency_code = $orgCurrency->code;

            $supplierCode = $row->currency_code;
            if ($supplierCode === null || $row->delivered_unit_cost === null) {
                $row->delivered_unit_cost = null;

                return $row;
            }

            if (!array_key_exists($supplierCode, $rates)) {
                $supplierCurrency = $supplierCode === $orgCurrency->code
                    ? $orgCurrency
                    : Currency::where('code', $supplierCode)->first();

                $rates[$supplierCode] = $supplierCurrency
                    ? GetCurrencyExchange::run($supplierCurrency, $orgCurrency)
                    : null;
            }

            $rate = $rates[$supplierCode];
            $row->delivered_unit_cost = $rate !== null
                ? round($row->delivered_unit_cost * $rate, 4)
                : null;

            return $row;
        });

        return $paginator;
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__("Supplier's product"), __("Supplier's products")])
                ->column(key: 'preferred', label: '', type: 'icon')
                ->column(key: 'supplier_name', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __("Supplier's code"), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'description', label: __("Supplier's unit description"), canBeHidden: false, searchable: true)
                ->column(key: 'unit_cost', label: __('Unit cost'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'delivered_unit_cost', label: __('Delivered unit cost'), canBeHidden: false, type: 'currency')
                ->column(key: 'units_per_carton', label: '', icon: 'fal fa-pallet', tooltip: __('Unit (SKOs) per carton'), canBeHidden: false, sortable: true)
                ->column(key: 'set_preferred', label: '', align: 'right')
                ->defaultSort('-local_priority');
        };
    }

    public function inOrgStock(Organisation $organisation, OrgStock $orgStock, ActionRequest $request, ?string $prefix = null): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgStock, $prefix);
    }

    public function jsonResponse(LengthAwarePaginator $orgStockSupplierProducts): AnonymousResourceCollection
    {
        return OrgStockSupplierProductsResource::collection($orgStockSupplierProducts);
    }
}
