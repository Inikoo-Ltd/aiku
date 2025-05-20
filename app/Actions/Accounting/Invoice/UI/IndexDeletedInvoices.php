<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicesSubNavigation;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Http\Resources\Accounting\DeletedInvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeletedInvoices extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithCustomerSubNavigation;
    use WithInvoicesSubNavigation;


    private Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|Shop|InvoiceCategory $parent;

    public function handle(Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|Shop|Order|InvoiceCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Invoice::onlyTrashed()->withTrashed());

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
        } elseif ($parent instanceof CustomerClient) {
            $queryBuilder->where('invoices.customer_client_id', $parent->id);
        } elseif ($parent instanceof Order) {
            $queryBuilder->where('invoices.order_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('invoices.group_id', $parent->id);
        } else {
            abort(422);
        }

        $queryBuilder->leftJoin('users', 'invoices.deleted_by', '=', 'users.id');


        $queryBuilder->defaultSort('-date')
            ->select([
                'invoices.id',
                'invoices.shop_id',
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.date',
                'invoices.type',
                'invoices.slug',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'invoices.deleted_at',
                'invoices.deleted_note',
                'users.contact_name as deleted_by_name',
                'users.slug as deleted_by_slug',
                'invoices.customer_id',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');

        if ($parent instanceof Group || $parent instanceof Organisation || $parent instanceof Shop) {
            $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');
        }

        if ($parent instanceof Group || $parent instanceof Organisation) {
            $queryBuilder->leftJoin('shops', 'invoices.shop_id', '=', 'shops.id')
                ->addSelect('shops.name as shop_name', 'shops.code as shop_code', 'shops.slug as shop_slug');
            $queryBuilder->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id')
                ->addSelect('organisations.name as organisation_name', 'organisations.code as organisation_code', 'organisations.slug as organisation_slug');

        }


        if ($parent instanceof Fulfilment) {
            $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->leftJoin('fulfilment_customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
                ->addSelect('customers.name as customer_name', 'fulfilment_customers.slug as customer_slug');
        }


        return $queryBuilder->allowedSorts(['total_amount', 'deleted_at', 'customer_name', 'reference', 'deleted_by_name'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|Shop|Order|InvoiceCategory $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $noResults = __("No invoices found");
            if ($parent instanceof Customer) {
                $stats     = $parent->stats;
                $noResults = __("Customer hasn't been invoiced");
            } elseif ($parent instanceof CustomerClient) {
                $stats     = $parent->stats;
                $noResults = __("This customer client hasn't been invoiced");
            } elseif ($parent instanceof Group) {
                $stats     = $parent->orderingStats;
                $noResults = __("This group hasn't been invoiced");
            } elseif ($parent instanceof InvoiceCategory) {
                $stats     = $parent->stats;
                $noResults = __("This invoice category hasn't been invoiced");
            } else {
                $stats = $parent->salesStats;
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_invoices ?? 0,
                    ]
                );

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_code', label: __('org'), canBeHidden: false, searchable: true);
                $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, searchable: true);
            }
            if ($parent instanceof Organisation) {
                $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, searchable: true);
            }


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Shop || $parent instanceof Fulfilment || $parent instanceof Organisation) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'deleted_at', label: __('deleted at'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'deleted_by_name', label: __('deleted by'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            $table->column(key: 'deleted_note', label: __('note'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }


    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return DeletedInvoicesResource::collection($invoices);
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        $subNavigation = [];

        if ($this->parent instanceof CustomerClient) {
            /** @var CustomerSalesChannel $customerSalesChannel */
            $customerSalesChannel = $request->route()->parameter('customerHasPlatform');
            $subNavigation       = $this->getCustomerClientSubNavigation($this->parent, $customerSalesChannel);
        } elseif ($this->parent instanceof Customer) {
            if ($this->parent->is_dropshipping) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            }
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        } elseif ($this->parent instanceof Shop || $this->parent instanceof Fulfilment || $this->parent instanceof Organisation) {
            $subNavigation = $this->getInvoicesNavigation($this->parent);
        }


        $title = __('Deleted Invoices');

        $icon = [
            'icon'  => ['fal', 'fa-file-invoice-dollar'],
            'title' => __('deleted invoices')
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
                'label' => __('deleted invoices')
            ];
        } elseif ($this->parent instanceof CustomerClient) {
            $iconRight  = $icon;
            $afterTitle = [
                'label' => $title
            ];

            $title = $this->parent->name;
            $model = __('customer client');
            $icon  = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('customer client')
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
        } elseif ($this->parent instanceof InvoiceCategory) {
            $model = __('Invoices');
            $title = $this->parent->name;
            $icon  = [
                'icon'  => ['fal', 'fa-file-invoice-dollar'],
                'title' => __('invoice category')
            ];
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
        } elseif ($this->parent instanceof Group) {
            $afterTitle = [
                'label' => __('In group').': '.$this->parent->name,
            ];
        }

        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();


        $inertiaRender = Inertia::render(
            'Org/Accounting/DeletedInvoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
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
                    'actions'       => $actions,
                ],

                'data' => DeletedInvoicesResource::collection($invoices),
            ]
        );

        if ($this->tab) {
            $inertiaRender->table($this->tableStructure(parent: $this->parent, prefix: InvoicesTabsEnum::INVOICES->value))
                ->table(IndexRefunds::make()->tableStructure(parent: $this->parent, prefix: InvoicesTabsEnum::REFUNDS->value));
        } else {
            $inertiaRender = $inertiaRender->table($this->tableStructure(parent: $this->parent));
        }

        return $inertiaRender;
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {

        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {

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

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {

        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);
        return $this->handle(group());
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Deleted invoices'),
                        'icon'  => 'fal fa-bars',

                    ],
                    'suffix' => $suffix
                ]
            ];
        };
        return match ($routeName) {
            'grp.org.shops.show.dashboard.invoices.deleted.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.dashboard.invoices.deleted.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.accounting.deleted_invoices.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),

            'grp.overview.accounting.deleted_invoices.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),


            default => []
        };
    }
}
