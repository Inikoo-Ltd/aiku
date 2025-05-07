<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-09h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceCategory\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\InvoiceCategoriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoiceCategories extends OrgAction
{
    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('invoice_categories.name', $value)
                        ->orWhereStartWith('invoice_categories.slug', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(InvoiceCategory::class);

        $queryBuilder->where('invoice_categories.organisation_id', $parent->id);
        $queryBuilder->leftjoin('currencies', 'invoice_categories.currency_id', 'currencies.id');
        $queryBuilder->leftjoin('invoice_category_stats', 'invoice_categories.id', 'invoice_category_stats.invoice_category_id');
        $queryBuilder->leftjoin('invoice_category_sales_intervals', 'invoice_categories.id', 'invoice_category_sales_intervals.invoice_category_id');
        return $queryBuilder
            ->defaultSort('invoice_categories.id')
            ->select([
                'invoice_categories.id',
                'invoice_categories.slug',
                'invoice_categories.name',
                'invoice_categories.state',
                'currencies.code as currency_code',
                'invoice_category_sales_intervals.sales_all as amount',
                'invoice_category_stats.number_invoices_type_invoice as number_type_invoices',
                'invoice_category_stats.number_invoices_type_refund as number_type_refunds',
            ])
            ->allowedSorts(['name', 'id' ,'state', 'number_type_invoices', 'amount', 'number_type_refunds'])
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
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title'       => __('no invoice categories'),
                        'count'       => 0,
                    ]
                )
                ->column(key: 'state_icon', label: '', canBeHidden: false, type:'icon')
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_type_invoices', label: __('invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_type_refunds', label: __('refunds'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'amount', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->defaultSort('id');
        };
    }

    public function jsonResponse(LengthAwarePaginator $invoiceCategories): AnonymousResourceCollection
    {
        return InvoiceCategoriesResource::collection($invoiceCategories);
    }


    public function htmlResponse(LengthAwarePaginator $invoiceCategories, ActionRequest $request): Response
    {

        $title = __('Invoice Categories');

        return Inertia::render(
            'Org/Accounting/InvoiceCategories',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'      => ['fal', 'fa-sitemap'],
                    'title'     => $title,
                    'actions'   => [
                        [
                            'type'    =>    'button',
                                            'style'   => 'create',
                                            'tooltip' => __('new invoice category'),
                                            'label'   => __('invoice category'),
                                            'route'   => [
                                                'name'       => 'grp.org.accounting.invoice-categories.create',
                                                'parameters' => $request->route()->originalParameters()
                                            ]
                        ]
                    ]
                ],
                'data'             => InvoiceCategoriesResource::collection($invoiceCategories)
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }
    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Invoice Categories'),
                        'icon'  => 'fal fa-bars',

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.invoice-categories.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
