<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicePayBox;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexRefundInProcessTransactions;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexRefundTransactions;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Enums\UI\Accounting\RefundInProcessTabsEnum;
use App\Enums\UI\Accounting\RefundTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\RefundResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Accounting\RefundInProcessTransactionsResource;
use App\Http\Resources\Accounting\RefundTransactionsResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRefund extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;

    private Invoice|Organisation|Fulfilment|FulfilmentCustomer|Shop $parent;

    public function handle(Invoice $refund): Invoice
    {
        return $refund;
    }


    public function inInvoiceInOrganisation(Organisation $organisation, Invoice $invoice, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $invoice;
        $this->initialisation($organisation, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }

    public function inOrganisation(Organisation $organisation, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Invoice $refund, $request): Invoice
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentInvoice(Organisation $organisation, Fulfilment $fulfilment, Invoice $invoice, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inInvoiceInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, Invoice $refund, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab($refund->in_process ? RefundInProcessTabsEnum::values() : RefundTabsEnum::values());

        return $this->handle($refund);
    }


    public function htmlResponse(Invoice $refund, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }


        $actions = [];

        if ($refund->in_process) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'delete',
                'label' => __('Delete'),
                'key'   => 'delete_refund',
                'route' => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.refund.force_delete',
                    'parameters' => [
                        'refund' => $refund->id,
                    ]
                ]
            ];

            $actions[] = [
                'type'  => 'button',
                'style' => 'secondary',
                'label' => __('Refund All'),
                'key'   => 'refund_all',
                'route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.refund.refund_all',
                    'parameters' => [
                        'refund' => $refund->id,
                    ]
                ]
            ];

            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('Finalise refund'),
                'key'   => 'finalise_refund',
                'route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.refund.finalise',
                    'parameters' => [
                        'refund' => $refund->id,
                    ]
                ]
            ];
        }


        $props = [
            'title'       => __('refund'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $refund,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => [
                'previous' => $this->getPrevious($refund, $request),
                'next'     => $this->getNext($refund, $request),
            ],
            'pageHead'    => [
                'subNavigation' => $subNavigation,
                'model'         => __('refund'),
                'title'         => $refund->reference,
                'icon'          => [
                    'icon'  => 'fal fa-arrow-circle-left',
                    'title' => $refund->reference
                ],
                'iconRight'     => $refund->in_process ? [
                    'icon'    => 'fal fa-seedling',
                    'class'   => 'text-green-500',
                    'tooltip' => __('In process')
                ] : null,
                'actions'       => $actions,
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => $refund->in_process ? RefundInProcessTabsEnum::navigation() : RefundTabsEnum::navigation()
            ],


            'order_summary' => [
                array_filter([
                    $refund->shop->fulfilment || $refund->services_amount > 0 ? [
                        'label'       => __('Services'),
                        'price_total' => $refund->services_amount
                    ] : [],

                    [
                        'label'       => __('Physical Goods'),
                        'price_total' => $refund->goods_amount
                    ],

                    $refund->shop->fulfilment || $refund->rental_amount > 0 ? [
                        'label'       => __('Rentals'),
                        'price_total' => $refund->rental_amount
                    ] : [],
                ]),
                [
                    [
                        'label'       => __('Tax'),
                        'price_total' => $refund->tax_amount
                    ]
                ],
                [
                    [
                        'label'       => __('Total'),
                        'price_total' => $refund->total_amount
                    ],
                ],
            ],


            'box_stats'      => array_merge($this->getBoxStats($refund), [
                'refund_id' => $refund->id
            ]),
            ...$this->getPayBoxData($refund->originalInvoice),
            'invoice'        => InvoiceResource::make($refund->originalInvoice),
            'invoice_refund' => RefundResource::make($refund),


        ];

        if ($refund->in_process) {
            $props = array_merge(
                $props,
                [
                    RefundInProcessTabsEnum::ITEMS->value => $this->tab == RefundInProcessTabsEnum::ITEMS->value ?
                        fn () => RefundInProcessTransactionsResource::collection(IndexRefundInProcessTransactions::run($refund, $refund->originalInvoice, RefundInProcessTabsEnum::ITEMS->value))
                        : Inertia::lazy(fn () => RefundInProcessTransactionsResource::collection(IndexRefundInProcessTransactions::run($refund, RefundInProcessTabsEnum::ITEMS->value))),


                    RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value => $this->tab == RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value ?
                        fn () => RefundInProcessTransactionsResource::collection(IndexRefundInProcessTransactions::run($refund, $refund->originalInvoice, RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value))
                        : Inertia::lazy(fn () => RefundInProcessTransactionsResource::collection(IndexRefundInProcessTransactions::run($refund, $refund->originalInvoice, RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value))),


                ]
            );
        } else {
            $exportInvoiceOptions = ShowInvoice::make()->getExportOptions($refund);

            $props = array_merge(
                $props,
                [
                    RefundTabsEnum::ITEMS->value => $this->tab == RefundTabsEnum::ITEMS->value ?
                        fn () => RefundTransactionsResource::collection(IndexRefundTransactions::run($refund, RefundTabsEnum::ITEMS->value))
                        : Inertia::lazy(fn () => RefundTransactionsResource::collection(IndexRefundTransactions::run($refund, RefundTabsEnum::ITEMS->value))),


                    RefundTabsEnum::PAYMENTS->value => $this->tab == RefundTabsEnum::PAYMENTS->value ?
                        fn () => PaymentsResource::collection(IndexPayments::run($refund))
                        : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run($refund))),
                    'invoiceExportOptions'          => $exportInvoiceOptions
                ]
            );
        }


        $inertia = Inertia::render(
            'Org/Accounting/Refund',
            $props
        );

        if ($refund->in_process) {
            $inertia->table(IndexRefundTransactions::make()->tableStructure($refund, RefundInProcessTabsEnum::ITEMS->value))
                ->table(IndexRefundInProcessTransactions::make()->tableStructure($refund, RefundInProcessTabsEnum::ITEMS_IN_PROCESS->value));
        } else {
            $inertia->table(IndexPayments::make()->tableStructure($refund, [], RefundTabsEnum::PAYMENTS->value))
                ->table(IndexRefundTransactions::make()->tableStructure($refund, RefundTabsEnum::ITEMS->value));
        }


        return $inertia;
    }


    public function jsonResponse(Invoice $invoice): RefundResource
    {
        return new RefundResource($invoice);
    }


    public function getBreadcrumbs(Invoice $refund, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $originalInvoice = $refund->originalInvoice;

        $headCrumb = function (Invoice $refund, array $routeParameters, string $suffix = null, $suffixIndex = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Refunds').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $refund->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        return match ($routeName) {
            'grp.org.fulfilments.show.operations.invoices.show.refunds.show'
            => array_merge(
                ShowInvoice::make()->getBreadcrumbs($originalInvoice, 'grp.org.fulfilments.show.operations.invoices.show', Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.show.refunds.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.show.refunds.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice', 'refund'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
            => array_merge(
                ShowInvoice::make()->getBreadcrumbs($originalInvoice, 'grp.org.fulfilments.show.crm.customers.show.invoices.show', Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice', 'refund'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),

            'grp.org.fulfilments.show.crm.customers.show.invoices.show',
            => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.crm.customers.show.invoices.refund.show',
            => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.refund.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),

            'grp.org.accounting.invoices.all_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),

            'grp.org.accounting.invoices.unpaid_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),

            'grp.org.accounting.invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $refund,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),


            default => []
        };
    }

    public function getPrevious(Invoice $invoice, ActionRequest $request): ?array
    {
        $previous = Invoice::where('reference', '<', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Invoice $invoice, ActionRequest $request): ?array
    {
        $next = Invoice::where('reference', '>', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Invoice $refund, string $routeName): ?array
    {
        if (!$refund) {
            return null;
        }


        return match ($routeName) {
            'grp.org.accounting.invoices.show' => [
                'label' => $refund->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $refund->organisation->slug,
                        'invoice'      => $refund->slug
                    ]

                ]
            ],


            'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show' => [
                'label' => $refund->reference,
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
                    'parameters' => [
                        'organisation'       => $refund->organisation->slug,
                        'fulfilment'         => $refund->shop->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->customer->fulfilmentCustomer->slug,
                        'invoice'            => $this->parent->slug,
                        'refund'             => $refund->slug
                    ]
                ]
            ],
            default => null
        };
    }
}
