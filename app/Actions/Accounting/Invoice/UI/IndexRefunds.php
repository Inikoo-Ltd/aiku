<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicesSubNavigation;
use App\Actions\Accounting\InvoiceCategory\UI\ShowInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\WithInvoiceCategorySubNavigation;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\Accounting\RefundsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRefunds extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithCustomerSubNavigation;
    use WithInvoicesSubNavigation;
    use WithInvoiceCategorySubNavigation;


    private Organisation|Fulfilment|Customer|FulfilmentCustomer|InvoiceCategory|Shop|Order|OrgPaymentServiceProvider|Invoice $parent;
    private string $bucket;

    public function handle(Organisation|Fulfilment|Customer|FulfilmentCustomer|InvoiceCategory|Shop|Order|OrgPaymentServiceProvider|Invoice $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Invoice::class);
        $queryBuilder->where('invoices.type', InvoiceTypeEnum::REFUND);


        if ($parent instanceof Organisation) {
            $queryBuilder->where('invoices.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('invoices.shop_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('invoices.shop_id', $parent->shop->id);
        } elseif ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('invoices.customer_id', $parent->customer->id);
        } elseif ($parent instanceof Customer) {
            $queryBuilder->where('invoices.customer_id', $parent->id);
        } elseif ($parent instanceof Order) {
            $queryBuilder->where('invoices.order_id', $parent->id);
        } elseif ($parent instanceof Invoice) {
            $queryBuilder->where('invoices.original_invoice_id', $parent->id);
        } elseif ($parent instanceof InvoiceCategory) {
            $queryBuilder->where('invoices.invoice_category_id', $parent->id);
        } else {
            abort(422);
        }

        $queryBuilder->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'invoices.shop_id', '=', 'shops.id');

        $queryBuilder->defaultSort('-date')
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.in_process',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.shop_id',
                'invoices.slug',
                'invoices.original_invoice_id',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id');


        if ($parent instanceof Shop || $parent instanceof Organisation) {
            $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');
        }

        if ($parent instanceof Fulfilment) {
            $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->leftJoin('fulfilment_customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
                ->addSelect('customers.name as customer_name', 'fulfilment_customers.slug as customer_slug');
        }

        return $queryBuilder->allowedSorts(['number', 'pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|Fulfilment|Customer|FulfilmentCustomer|InvoiceCategory|Shop|Order|OrgPaymentServiceProvider|Invoice $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $noResults = __("No refunds found");
            $stats     = null;
            if ($parent instanceof Customer || $parent instanceof FulfilmentCustomer || $parent instanceof InvoiceCategory) {
                $stats = $parent->stats;
            } elseif ($parent instanceof Invoice) {
                $stats     = $parent->stats;
                $noResults = __("Invoice has not been refunded");
            } elseif ($parent instanceof Organisation) {
                $stats = $parent->orderingStats;
            }
            $table->betweenDates(['date']);

            $table->withGlobalSearch();
            if ($stats) {
                $table->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_invoices ?? 0,
                    ]
                );
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'in_process', label: '', canBeHidden: false, type: 'icon');

            if ($parent instanceof Organisation) {
                $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Fulfilment || $parent instanceof Shop || $parent instanceof Organisation) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');


            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function getExportOptions(?string $filter): array
    {
        if (!Arr::get($this->organisation->settings, 'invoice_export.show_omega')) {
            return [];
        }

        if ($this->parent instanceof Organisation) {
            $route      = 'grp.org.accounting.invoices.index.omega';
            $parameters = array_filter([
                'organisation' => $this->organisation->slug,
                'filter'       => $filter,
                'type'         => 'refund',
            ]);
        } elseif ($this->parent instanceof Shop || $this->parent instanceof Fulfilment) {
            $route      = 'grp.org.shops.show.dashboard.invoices.index.omega';
            $parameters = array_filter([
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
                'filter'       => $filter,
                'type'         => 'refund',
            ]);
        } else {
            return [];
        }

        return [
            [
                'type'       => 'omega',
                'icon'       => 'fas fa-omega',
                'tooltip'    => __('Download Omega'),
                'label'      => 'Omega',
                'name'       => $route,
                'parameters' => $parameters,
            ]

        ];
    }

    public function jsonResponse(LengthAwarePaginator $refunds): AnonymousResourceCollection
    {
        return RefundsResource::collection($refunds);
    }

    public function htmlResponse(LengthAwarePaginator $refunds, ActionRequest $request): Response
    {
        $subNavigation = IndexInvoices::make()->getSubNavigation($this->parent, $request);

        $title = __('Refunds');

        $icon = [
            'icon'  => ['fal', 'fa-file-minus'],
            'title' => __('refunds')
        ];

        $afterTitle = null;
        $iconRight  = null;
        $model      = null;
        $actions    = null;


        if ($this->parent instanceof FulfilmentCustomer) {
            $icon       = ['fal', 'fa-user'];
            $title      = $this->parent->customer->name;
            $iconRight  = [
                'icon' => 'fal fa-file-invoice-dollar',
            ];
            $afterTitle = [

                'label' => __('invoices')
            ];
        } elseif ($this->parent instanceof Customer) {
            $iconRight  = $icon;
            $afterTitle = [
                'label' => $title
            ];
            $title      = $this->parent->name;
            $icon       = [
                'icon'  => ['fal', 'fa-user'],
                'title' => __('customer')
            ];
        } elseif ($this->parent instanceof Invoice) {
            $afterTitle = [
                'label' => __('Refunds')
            ];
            $iconRight  = [
                'icon' => 'fal fa-file-minus',
            ];
            $title      = $this->parent->reference;
        } elseif ($this->parent instanceof Organisation) {
            $afterTitle = [
                'label' => __('In organisation').': '.$this->parent->name
            ];
        } elseif ($this->parent instanceof Shop) {
            $afterTitle = [
                'label' => $this->parent->name
            ];
        } elseif ($this->parent instanceof Fulfilment) {
            $afterTitle = [
                'label' => $this->parent->shop->name,
            ];
        }

        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        $invoiceExportOptions = [];

        $filter                                       = request()->input('between')['date'] ?? null;
        $exportInvoiceOptions                         = $this->getExportOptions($filter);
        $invoiceExportOptions['invoiceExportOptions'] = $exportInvoiceOptions;


        return Inertia::render(
            'Org/Accounting/Refunds',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('invoices'),
                'pageHead'    => [

                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => RefundsResource::collection($refunds),
                ...$invoiceExportOptions

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'paid';
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    public function inInvoiceInOrganisation(Organisation $organisation, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'invoice';
        $this->parent = $invoice;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inInvoiceCategory(Organisation $organisation, InvoiceCategory $invoiceCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $invoiceCategory;
        $this->initialisation($organisation, $request);

        return $this->handle($invoiceCategory);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentInvoice(Organisation $organisation, Fulfilment $fulfilment, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($invoice);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inInvoiceInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $invoice;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($invoice);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $customer);
    }


    public function getBreadcrumbs(Invoice|Organisation|Fulfilment|Customer|FulfilmentCustomer|InvoiceCategory|Shop $parent, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Refunds'),
                        'icon'  => 'fal fa-bars',

                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.accounting.refunds.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                )
            ),
            'grp.org.shops.show.dashboard.invoices.refunds.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.dashboard.invoices.refunds.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),


            'grp.org.fulfilments.show.crm.customers.show.invoices.index' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),


            'grp.org.fulfilments.show.operations.invoices.refunds.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs(routeParameters: $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    ''
                )
            ),


            'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.index' =>
            array_merge(
                ShowInvoice::make()->getBreadcrumbs($parent, 'grp.org.fulfilments.show.crm.customers.show.invoices.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),

            'grp.org.accounting.invoice-categories.show.refunds.index' =>
            array_merge(
                ShowInvoiceCategory::make()->getBreadcrumbs($this->parent, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                )
            ),

            default => []
        };
    }
}
