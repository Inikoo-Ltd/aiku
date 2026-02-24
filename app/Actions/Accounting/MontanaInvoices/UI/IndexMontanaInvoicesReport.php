<?php

namespace App\Actions\Accounting\MontanaInvoices\UI;

use App\Actions\Accounting\Invoice\WithInvoicesSubNavigation;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Http\Resources\Accounting\MontanaInvoiceResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMontanaInvoicesReport extends OrgAction
{
    use WithInvoicesSubNavigation;

    private int $records;
    private Organisation|Shop $parent;

    public function handle(Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value)
                    ->orWhereWith('customers.name', $value)
                    ->orWhereWith('customers.company_name', $value)
                    ->orWhereWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Invoice::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('invoices.organisation_id', $parent->id);
        } else {
            $queryBuilder->where('invoices.shop_id', $parent->id);
        }

        $queryBuilder->where('invoices.in_process', false);
        $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id');
        $queryBuilder->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id');
        $queryBuilder->leftJoin('addresses', 'customers.address_id', '=', 'addresses.id');

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
                'invoices.goods_amount',
                'invoices.services_amount',
                'invoices.shipping_amount',
                'invoices.charges_amount',
                'invoices.net_amount',
                'invoices.tax_amount',
                'invoices.total_amount',
                'invoices.pay_status',
                'invoices.created_at',
                'invoices.updated_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.company_name as customer_company',
                'customers.contact_name as customer_contact',
                'customers.identity_document_number as customer_identity_document',
                'customers.identity_document_type as customer_identity_document_type',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'addresses.address_line_1',
                'addresses.address_line_2',
                'addresses.locality',
                'addresses.administrative_area',
                'addresses.postal_code',
                'addresses.country_code',
            ])
            ->paginate(perPage: 50);
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No Montana invoices'),
                        'description' => __('No invoices found for the selected period.'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['date'])
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'reference', label: __('Reference'), sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), sortable: true, searchable: true)
                ->column(key: 'customer_contact', label: __('Contact'), sortable: false)
                ->column(key: 'customer_identity_document', label: __('ID Document'), sortable: false)
                ->column(key: 'type', label: __('Type'))
                ->column(key: 'net_amount', label: __('Net'), sortable: true, type: 'currency')
                ->column(key: 'tax_amount', label: __('Tax'), sortable: true, type: 'currency')
                ->column(key: 'total_amount', label: __('Total'), sortable: true, type: 'currency')
                ->defaultSort('-date');
        };
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        // todo: uncomment this when show between dates works on UI
        //
        //        if (!$request->has('between')) {
        //            $start   = Carbon::now()->startOfMonth()->format('Ymd');
        //            $end     = Carbon::now()->format('Ymd');
        //            $between = [
        //                'date' => "$start-$end",
        //            ];
        //
        //            // Keep action attributes in sync
        //            $this->set('between', $between);
        //
        //            // Also merge into request so request()->input('between') returns this value
        //            $request->merge(['between' => $between]);
        //            request()->merge(['between' => $between]);
        //        }
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inReports(Organisation|Shop $parent): int
    {
        return $this->handle($parent)->total();
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        if ($this->parent instanceof Shop) {
            return Inertia::render(
                'Org/Reports/MontanaInvoicesReport',
                [
                    'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                    'title'       => __('Montana Invoices Report'),
                    'pageHead'    => [
                        'title'         => __('Montana Invoices Export Report'),
                        'icon'          => [
                            'title' => __('Montana Invoices'),
                            'icon'  => 'fal fa-file-invoice'
                        ],
                        'subNavigation' => $this->getInvoicesNavigation($this->parent)
                    ],
                    'data'        => MontanaInvoiceResource::collection($invoices),
                ]
            )->table($this->tableStructure());
        }

        return Inertia::render(
            'Org/Reports/MontanaInvoicesReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Montana Invoices Report'),
                'pageHead'    => [
                    'title' => __('Montana Invoices Export Report'),
                    'icon'  => [
                        'title' => __('Montana Invoices'),
                        'icon'  => 'fal fa-file-invoice'
                    ],
                ],
                'data'        => MontanaInvoiceResource::collection($invoices),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return MontanaInvoiceResource::collection($invoices);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if ($this->parent instanceof Shop) {
            return array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'icon'  => 'fal fa-file-invoice',
                            'label' => __('Montana Invoices'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.invoices.montana.index',
                                'parameters' => $routeParameters
                            ]
                        ]
                    ],
                ],
            );
        }

        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-file-invoice',
                        'label' => __('Montana Invoices'),
                        'route' => [
                            'name'       => 'grp.org.reports.montana-invoices',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
