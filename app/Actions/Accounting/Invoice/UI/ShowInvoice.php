<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicePayBox;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexItemizedInvoiceTransactions;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\InvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\Http\Resources\Accounting\ItemizedInvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Accounting\RefundResource;
use App\Http\Resources\Accounting\RefundsResource;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInvoice extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;

    private Organisation|Fulfilment|FulfilmentCustomer|Shop $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /**
     * Get structured invoice summary for display in the UI.
     *
     * NOTE: Used in deleted invoices as well ShowInvoiceDeleted.php
     *
     * Returns a multidimensional array with three sections:
     * 1. Product/service line items (conditionally showing services and rentals)
     * 2. Additional costs (charges, shipping, insurance, tax)
     * 3. Total amount
     *
     * @param  Invoice  $invoice  The invoice model to generate summary for
     *
     * @return array Structured array of invoice summary data for UI rendering
     */
    public function getInvoiceSummary(Invoice $invoice): array
    {
        return [
            array_values(
                array_filter(
                    [
                        $invoice->shop->fulfilment || $invoice->services_amount > 0 ? [
                            'label'       => __('Services'),
                            'price_total' => $invoice->services_amount
                        ] : null,

                        [
                            'label'       => __('Physical Goods'),
                            'price_total' => $invoice->goods_amount
                        ],

                        $invoice->shop->fulfilment || $invoice->rental_amount > 0 ? [
                            'label'       => __('Rentals'),
                            'price_total' => $invoice->rental_amount
                        ] : null
                    ]
                )
            ),
            [
                [
                    'label'       => __('Charges'),
                    // 'information'   => __('Shipping fee to your address using DHL service.'),
                    'price_total' => $invoice->charges_amount
                ],
                [
                    'label'       => __('Shipping'),
                    // 'information'   => __('Tax is based on 10% of total order.'),
                    'price_total' => $invoice->shipping_amount
                ],
                [
                    'label'       => __('Insurance'),
                    // 'information'   => __('Tax is based on 10% of total order.'),
                    'price_total' => $invoice->insurance_amount
                ],
                [
                    'label'       => __('Tax'),
                    'price_total' => $invoice->tax_amount
                ]
            ],
            [
                [
                    'label'       => __('Total'),
                    'price_total' => $invoice->total_amount
                ],
            ],
        ];
    }


    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $payBoxData = $this->getPayBoxData($invoice);

        $actions = [];

        if ($this->parent instanceof Fulfilment) {
            $actions[] =
                $this->isSupervisor
                    ? [
                    'supervisor' => true,
                    'type'       => 'button',
                    'style'      => 'red_outline',
                    'tooltip'    => __('delete'),
                    'icon'       => 'fal fa-trash-alt',
                    'key'        => 'delete_booked_in',
                    'ask_why'    => true,
                    'route'      => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ]
                    : [
                    'supervisor'        => false,
                    'supervisors_route' => [
                        'method'     => 'get',
                        'name'       => 'grp.json.fulfilment.supervisors.index',
                        'parameters' => [
                            'fulfilment' => $invoice->shop->fulfilment->slug
                        ]
                    ],
                    'type'              => 'button',
                    'style'             => 'red_outline',
                    'tooltip'           => __('Delete'),
                    'icon'              => 'fal fa-trash-alt',
                    'key'               => 'delete_booked_in',
                    'ask_why'           => true,
                    'route'             => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ];
        } else {
            $actions[] =
                [
                    'supervisor' => true,
                    'type'       => 'button',
                    'style'      => 'red_outline',
                    'tooltip'    => __('delete'),
                    'icon'       => 'fal fa-trash-alt',
                    'key'        => 'delete_booked_in',
                    'ask_why'    => true,
                    'route'      => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ];
        }

        if ($this->parent instanceof Organisation) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.accounting.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        }


        $actions[] =
            [
                'type'  => 'button',
                'style' => 'tertiary',
                'label' => __('send invoice'),
                'key'   => 'send-invoice',
                'route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.invoice.send_invoice',
                    'parameters' => [
                        'invoice' => $invoice->id
                    ]
                ]
            ];

        if ($payBoxData['invoice_pay']['total_refunds'] != $invoice->total_amount) {
            $actions[] =
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('create refund'),
                    'route' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.refund.create',
                        'parameters' => [
                            'invoice' => $invoice->id,

                        ],
                        'body'       => [
                            'referral_route' => [
                                'name'       => $request->route()->getName(),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ],
                ];
        }

        $exportInvoiceOptions=[
            [
                'type'       => 'pdf',
                'icon'       => 'fas fa-file-pdf',
                'label'      => 'PDF',
                'tooltip'    => __('Download PDF'),
                'name'       => 'grp.org.accounting.invoices.download',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'invoice'      => $invoice->slug
                ]
            ],
            [
                'type'       => 'isDoc',
                'icon'       => 'fas fa-hockey-puck',
                'tooltip'    => __('Download Doc'),
                'label'      => 'IsDoc',
                'name'       => 'grp.org.accounting.invoices.show.is_doc',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'invoice'      => $invoice->slug
                ]
            ]

        ];


        return Inertia::render(
            'Org/Accounting/Invoice',
            [
                'title'       => __('invoice'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $invoice,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($invoice, $request),
                    'next'     => $this->getNext($invoice, $request),
                ],
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'model'         => __('invoice'),
                    'title'         => $invoice->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ],
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => InvoiceTabsEnum::navigation()
                ],

                'order_summary' => $this->getInvoiceSummary($invoice),

                ...$payBoxData,

                'invoiceExportOptions' => $exportInvoiceOptions,


                'box_stats'      => $this->getBoxStats($invoice),
                'list_refunds'   => RefundResource::collection($invoice->refunds),
                'invoice'        => InvoiceResource::make($invoice),
                'outbox'         => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                InvoiceTabsEnum::REFUNDS->value => $this->tab == InvoiceTabsEnum::REFUNDS->value
                    ? fn () => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))
                    : Inertia::lazy(fn () => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))),

                InvoiceTabsEnum::GROUPED->value => $this->tab == InvoiceTabsEnum::GROUPED->value ?
                    fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::GROUPED->value))
                    : Inertia::lazy(fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::GROUPED->value))),

                InvoiceTabsEnum::ITEMIZED->value => $this->tab == InvoiceTabsEnum::ITEMIZED->value ?
                    fn () => ItemizedInvoiceTransactionsResource::collection(IndexItemizedInvoiceTransactions::run($invoice, InvoiceTabsEnum::ITEMIZED->value))
                    : Inertia::lazy(fn () => ItemizedInvoiceTransactionsResource::collection(IndexItemizedInvoiceTransactions::run($invoice, InvoiceTabsEnum::ITEMIZED->value))),

                InvoiceTabsEnum::EMAIL->value => $this->tab == InvoiceTabsEnum::EMAIL->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))),


                InvoiceTabsEnum::PAYMENTS->value => $this->tab == InvoiceTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexPayments::run($invoice))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run($invoice))),


            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], InvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexRefunds::make()->tableStructure(parent: $invoice, prefix: InvoiceTabsEnum::REFUNDS->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($invoice->customer, prefix: InvoiceTabsEnum::EMAIL->value))
            ->table(IndexInvoiceTransactions::make()->tableStructure($invoice, InvoiceTabsEnum::GROUPED->value))
            ->table(IndexItemizedInvoiceTransactions::make()->tableStructure($invoice, InvoiceTabsEnum::ITEMIZED->value));
    }


    public function jsonResponse(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice);
    }


    public function getBreadcrumbs(Invoice $invoice, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Invoice $invoice, array $routeParameters, string $suffix = null, $suffixIndex = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Invoices').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $invoice->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
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
                    $invoice,
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
                    $invoice,
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
                    $invoice,
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

            'grp.org.accounting.invoices.all_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
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
                    $invoice,
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
                    $invoice,
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

    private function getNavigation(?Invoice $invoice, string $routeName): ?array
    {
        if (!$invoice) {
            return null;
        }

        // $isInvoice = $invoice->type === InvoiceTypeEnum::INVOICE;

        return match ($routeName) {
            'grp.org.accounting.invoices.show', 'grp.org.accounting.invoices.all_invoices.show', 'grp.org.accounting.invoices.unpaid_invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'fulfilment'   => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.shops.show.dashboard.invoices.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'shop'         => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],

            //  'grp.org.fulfilments.show.crm.customers.show.refund.show'
            'grp.org.fulfilments.show.crm.customers.show.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $invoice->organisation->slug,
                        'fulfilment'         => $invoice->shop->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->slug,
                        'invoice'            => $invoice->slug
                    ]
                ]
            ],
        };
    }
}
