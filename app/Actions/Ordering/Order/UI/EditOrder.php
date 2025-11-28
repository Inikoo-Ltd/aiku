<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Ordering\Purge\UI\ShowPurge;
use App\Actions\OrgAction;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrder extends OrgAction
{
    use IsOrder;
    use WithOrderingEditAuthorisation;

    private Shop|Customer|CustomerClient|Purge|CustomerSalesChannel $parent;
    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function inOrganisation(Organisation $organisation, Order $order, ActionRequest $request): Order
    {
        $this->initialisation($organisation, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerInShop(Organisation $organisation, Shop $shop, Customer $customer, Order $order, ActionRequest $request): Order
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPlatformInCustomer(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->parent = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, CustomerClient $customerClient, Order $order, ActionRequest $request): Order
    {
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomerClient(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerClient $customerClient, CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->parent               = $customerClient;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPurge(Organisation $organisation, Shop $shop, Purge $purge, Order $order, ActionRequest $request): Order
    {
        $this->parent = $purge;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $order,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title'    => $order->slug,
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('id'),
                            'fields' => [
                                'reference' => [
                                    'type'  => 'input',
                                    'label' => __('reference'),
                                    'value' => $order->reference
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.order.update',
                            'parameters' => [
                                'order'     => $order->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Order $order, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Orders')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $order->reference,
                        ],

                    ],
                    'suffix'         => $suffix . '(' . __('Editing') . ')'

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.orders.show',
            'grp.org.shops.show.ordering.orders.edit',
            'grp.org.shops.show.ordering.orders.show.delivery-note'
            => array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.orders.show',
            => array_merge(
                (new ShowCustomer())->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_clients.orders.show'
            => array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($order->customer, 'grp.org.shops.show.crm.customers.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.ordering.purges.order'
            => array_merge(
                (new ShowPurge())->getBreadcrumbs($this->parent, 'grp.org.shops.show.ordering.purges.order', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.show',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.order',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show'
            => array_merge(
                (new ShowCustomerSalesChannel())->getBreadcrumbs($order->customerSalesChannel, 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show'
            => array_merge(
                (new ShowCustomerClient())->getBreadcrumbs($this->customerSalesChannel, 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show'
            => array_merge(
                (new ShowCustomerClient())->getBreadcrumbs($this->customerSalesChannel, 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    $order,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.index',
                            'parameters' => Arr::except($routeParameters, ['order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
