<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesInOrgStockFamily extends OrgAction
{
    use WithInventoryAuthorisation;

    private OrgStockFamily $orgStockFamily;

    public function handle(OrgStockFamily $orgStockFamily, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value)
                    ->orWhereWith('invoices.customer_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Invoice::class)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereNot('invoices.in_process', true)
            ->join('invoice_transactions', function ($join) {
                $join->on('invoices.id', '=', 'invoice_transactions.invoice_id')
                    ->whereNull('invoice_transactions.deleted_at');
            })
            ->join('invoice_transaction_has_org_stocks', 'invoice_transactions.id', '=', 'invoice_transaction_has_org_stocks.invoice_transaction_id')
            ->where('invoice_transaction_has_org_stocks.org_stock_family_id', $orgStockFamily->id)
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id')
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.slug',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.in_process',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.company_name as customer_company',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->distinct()
            ->defaultSort('-date')
            ->allowedSorts(['pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgStockFamily = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStockFamily);
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->betweenDates(['date'])
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, type: 'icon')
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'total_amount', label: __('Total'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/Invoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Invoices'),
                'pageHead'    => [
                    'title' => __('Invoices'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => __('Invoices'),
                    ],
                    'afterTitle' => [
                        'label' => $this->orgStockFamily->code,
                    ],
                ],
                'data' => InvoicesResource::collection($invoices),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'warehouse'    => $routeParameters['warehouse'],
                            ],
                        ],
                        'label' => __('SKU Families'),
                        'icon'  => 'fal fa-boxes-alt',
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->orgStockFamily->code,
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.invoices.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Invoices'),
                    ],
                ],
            ]
        );
    }
}
