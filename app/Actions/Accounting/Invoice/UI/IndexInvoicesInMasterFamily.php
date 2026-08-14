<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\CRM\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesInMasterFamily extends OrgAction
{
    use WithMastersAuthorisation;

    private MasterProductCategory $parent;

    public function handle(MasterProductCategory $masterFamily, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Invoice::class);
        $query->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');
        $query->join('invoice_transactions', 'invoices.id', 'invoice_transactions.invoice_id');
        $query->where('invoice_transactions.master_family_id', $masterFamily->id);
        $query->where('invoice_transactions.is_refund', false);
        $query->whereNull('invoice_transactions.deleted_at');
        $query->groupBy('invoices.id', 'currencies.code', 'currencies.symbol');

        return $query
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.customer_name',
                'invoices.date',
                'invoices.pay_status',
                'invoices.net_amount',
                'invoices.total_amount',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->defaultSort('-date')
            ->allowedSorts(['reference', 'customer_name', 'date', 'pay_status', 'net_amount', 'total_amount'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->betweenDates(['date'])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'total_amount', label: __('Total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterFamily);
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        return Inertia::render(
            'InvoicesInProduct',
            [
                'title'       => __('Invoices'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => __('Invoices'),
                    'model'   => $this->parent->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('Master Family')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Back to Master Family'),
                            'route' => [
                                'name'       => preg_replace('/invoices$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'data'        => InvoicesResource::collection($invoices),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowMasterFamily::make()->getBreadcrumbs(
                $masterFamily,
                preg_replace('/invoices$/', 'show', $routeName),
                $routeParameters
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Invoices'),
                    ]
                ]
            ]
        );
    }
}
