<?php

namespace App\Actions\Accounting\SageInvoices\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\SageInvoiceResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSageInvoicesReport extends OrgAction
{
    private int $records;

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value)
                    ->orWhereWith('customers.name', $value)
                    ->orWhereWith('customers.company_name', $value)
                    ->orWhereWith('customers.accounting_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Invoice::class);
        $queryBuilder->where('invoices.organisation_id', $organisation->id)
            ->where('invoices.in_process', false)
            ->whereHas('customer', function ($query) {
                $query->where('is_credit_customer', true)
                    ->whereNotNull('accounting_reference');
            });

        $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id');
        $queryBuilder->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id');
        $queryBuilder->leftJoin('tax_categories', 'invoices.tax_category_id', '=', 'tax_categories.id');

        $this->records = $queryBuilder->count('invoices.id');

        $queryBuilder
            ->defaultSort('-date')
            ->allowedSorts(['date', 'reference', 'customer_name', 'net_amount', 'tax_amount', 'total_amount'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix)
            ->withQueryString();

        return $queryBuilder
            ->select([
                'invoices.id',
                'invoices.slug',
                'invoices.reference',
                'invoices.date',
                'invoices.type',
                'invoices.net_amount',
                'invoices.tax_amount',
                'invoices.total_amount',
                'invoices.pay_status',
                'invoices.created_at',
                'invoices.updated_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.company_name as customer_company',
                'customers.accounting_reference',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'tax_categories.name as tax_category_name',
            ])
            ->paginate(perPage: 50);
    }

    public function tableStructure(Organisation $organisation, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No Sage invoices'),
                        'description' => __('No credit customer invoices found for the selected period. Enable credit customers in customer settings.'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['date'])
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'reference', label: __('Reference'), sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), sortable: true, searchable: true)
                ->column(key: 'accounting_reference', label: __('Sage Ref'), sortable: false)
                ->column(key: 'type', label: __('Type'))
                ->column(key: 'net_amount', label: __('Net'), sortable: true, type: 'currency')
                ->column(key: 'tax_amount', label: __('Tax'), sortable: true, type: 'currency')
                ->column(key: 'total_amount', label: __('Total'), sortable: true, type: 'currency')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Reports/SageInvoicesReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Sage Invoices Report'),
                'pageHead'    => [
                    'title' => __('Sage Invoices Export Report'),
                    'icon'  => [
                        'title' => __('Sage Invoices'),
                        'icon'  => 'fal fa-file-invoice'
                    ],
                ],
                'data'        => SageInvoiceResource::collection($invoices),
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return SageInvoiceResource::collection($invoices);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-chart-line',
                    'label' => __('Reports'),
                    'route' => [
                        'name'       => 'grp.org.reports.index',
                        'parameters' => $routeParameters
                    ]
                ]
            ],
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-file-invoice',
                    'label' => __('Sage Invoices'),
                    'route' => [
                        'name'       => 'grp.org.reports.sage-invoices',
                        'parameters' => $routeParameters
                    ]
                ]
            ],
        ];
    }
}
