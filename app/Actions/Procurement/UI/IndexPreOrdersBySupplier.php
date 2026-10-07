<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-3432. The pre-orders still waiting for goods, grouped by the preferred supplier of what
 * they wait for, so the buying team can put them in one shipment: quantity and value per
 * supplier next to its minimum order and the order-by date. A pre-order waiting on two suppliers
 * is listed under both.
 */
class IndexPreOrdersBySupplier extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Organisation $organisation): Collection
    {
        $preferredLinks = DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->where('link.status', true)
            ->whereRaw('link.local_priority = (select max(l2.local_priority) from org_stock_has_org_supplier_products l2 where l2.org_stock_id = link.org_stock_id and l2.status = true)')
            ->select(['link.org_stock_id', 'osp.org_supplier_id', 'osp.supplier_product_id']);

        $rows = DB::table('pre_orders')
            ->join('orders', 'orders.id', 'pre_orders.order_id')
            ->join('customers', 'customers.id', 'orders.customer_id')
            ->join('shops', 'shops.id', 'orders.shop_id')
            ->join('transactions', 'transactions.order_id', 'orders.id')
            ->join('products', 'products.id', 'transactions.model_id')
            ->join('product_has_org_stocks as phos', 'phos.product_id', 'products.id')
            ->join('org_stocks', 'org_stocks.id', 'phos.org_stock_id')
            ->leftJoinSub($preferredLinks, 'preferred', 'preferred.org_stock_id', 'org_stocks.id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'preferred.org_supplier_id')
            ->leftJoin('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->leftJoin('supplier_products', 'supplier_products.id', 'preferred.supplier_product_id')
            ->leftJoin('currencies as supplier_currencies', 'supplier_currencies.id', 'suppliers.currency_id')
            ->where('org_stocks.organisation_id', $organisation->id)
            ->where('pre_orders.state', PreOrderStateEnum::WAITING_FOR_GOODS->value)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->whereNotNull(DB::raw("transactions.data->'pre_order'"))
            ->orderBy('pre_orders.id')
            ->get([
                'pre_orders.id as pre_order_id',
                'pre_orders.created_at',
                'pre_orders.supplier_ordered_at',
                'pre_orders.estimated_dispatch_to',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'products.code as product_code',
                'org_stocks.code as org_stock_code',
                DB::raw("coalesce((transactions.data->'pre_order'->>'pre_order_quantity')::numeric, transactions.quantity_ordered) * phos.quantity as quantity"),
                DB::raw("transactions.org_net_amount * coalesce((transactions.data->'pre_order'->>'pre_order_quantity')::numeric, transactions.quantity_ordered) / nullif(transactions.quantity_ordered, 0) as org_net_amount"),
                'supplier_products.cost as supplier_unit_cost',
                'org_suppliers.id as org_supplier_id',
                'org_suppliers.slug as org_supplier_slug',
                'suppliers.name as supplier_name',
                'suppliers.settings as supplier_settings',
                'supplier_currencies.code as supplier_currency_code',
            ]);

        return $rows->groupBy(fn ($row) => $row->org_supplier_id ?? 0)
            ->map(function (Collection $lines) {
                $first    = $lines->first();
                $settings = json_decode($first->supplier_settings ?? '{}', true) ?: [];
                $cost     = $lines->sum(fn ($line) => (float) $line->quantity * (float) $line->supplier_unit_cost);

                return [
                    'org_supplier_id'     => $first->org_supplier_id,
                    'org_supplier_slug'   => $first->org_supplier_slug,
                    'supplier_name'       => $first->supplier_name ?? __('No supplier'),
                    'supplier_currency'   => $first->supplier_currency_code,
                    'minimum_order'       => Arr::get($settings, 'minimum_order') !== null ? (float) Arr::get($settings, 'minimum_order') : null,
                    'order_by_date'       => Arr::get($settings, 'pre_order_order_by_date'),
                    'supplier_cost'       => round($cost, 2),
                    'meets_minimum'       => Arr::get($settings, 'minimum_order') === null || $cost >= (float) Arr::get($settings, 'minimum_order'),
                    'number_pre_orders'   => $lines->pluck('pre_order_id')->unique()->count(),
                    'quantity'            => round($lines->sum(fn ($line) => (float) $line->quantity), 3),
                    'sales_value'         => round($lines->sum(fn ($line) => (float) $line->org_net_amount), 2),
                    'pre_order_ids'       => $lines->pluck('pre_order_id')->unique()->values()->all(),
                    'not_ordered_ids'     => $lines->whereNull('supplier_ordered_at')->pluck('pre_order_id')->unique()->values()->all(),
                    'lines'               => $lines->map(fn ($line) => [
                        'pre_order_id'        => $line->pre_order_id,
                        'order_reference'     => $line->order_reference,
                        'order_slug'          => $line->order_slug,
                        'customer_name'       => $line->customer_name,
                        'customer_slug'       => $line->customer_slug,
                        'shop_code'           => $line->shop_code,
                        'shop_slug'           => $line->shop_slug,
                        'product_code'        => $line->product_code,
                        'org_stock_code'      => $line->org_stock_code,
                        'quantity'            => (float) $line->quantity,
                        'ordered_at'          => $line->created_at,
                        'supplier_ordered_at' => $line->supplier_ordered_at,
                        'dispatch_by'         => $line->estimated_dispatch_to,
                    ])->values()->all(),
                ];
            })
            ->sortBy(fn ($supplier) => $supplier['order_by_date'] ?? '9999')
            ->values();
    }

    public function asController(Organisation $organisation, ActionRequest $request): Collection
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Collection $suppliers, ActionRequest $request): Response
    {
        return Inertia::render('Procurement/PreOrdersBySupplier', [
            'breadcrumbs'  => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.pre_orders.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                            'label' => __('Pre-orders'),
                        ],
                    ],
                ]
            ),
            'title'        => __('Pre-orders by supplier'),
            'pageHead'     => [
                'title' => __('Pre-orders by supplier'),
                'icon'  => ['fal', 'fa-hourglass-half'],
            ],
            'suppliers'    => $suppliers,
            'currency'     => $this->organisation->currency->code,
            'can_edit'     => $this->canEdit,
            'update_route' => [
                'name'       => 'grp.org.procurement.pre_orders.update',
                'parameters' => $request->route()->originalParameters(),
                'method'     => 'patch',
            ],
        ]);
    }
}
